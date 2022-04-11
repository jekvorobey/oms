<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Models\Payment\PaymentStatus;
use App\Services\Dto\In\OrderReturn\OrderReturnDto;
use Carbon\Carbon;
use App\Services\Dto\In\OrderReturn\OrderReturnItemDto;
use Exception;
use Greensight\Customer\Services\ReferralService\Dto\ReturnReferralBillOperationDto;
use Greensight\Customer\Services\ReferralService\ReferralService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MerchantManagement\Dto\MerchantBillOperation\ShipmentStatusDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

/**
 * Class OrderReturnService
 * @package App\Services
 */
class OrderReturnService
{
    /**
     * Создать возврат по заказу
     * @throws Exception
     */
    public function create(OrderReturnDto $orderReturnDto): ?OrderReturn
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderReturnDto->order_id)->load('basket.items');

        $basketItemIds = $orderReturnDto->items->pluck('basket_item_id');

        if (!$this->needCreateOrderReturn($order, $orderReturnDto)) {
            return null;
        }

        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = BasketItem::query()->whereKey($basketItemIds)->with('shipmentItem.shipment')->get()->keyBy('id');

        $orderReturn = DB::transaction(function () use ($orderReturnDto, $order, $basketItems) {
            $orderReturn = $this->createOrderReturn($order, $orderReturnDto);

            foreach ($orderReturnDto->items as $item) {
                $basketItem = $basketItems->get($item->basket_item_id);
                if (!$basketItem) {
                    throw new Exception("BasketItem by id={$item->basket_item_id} not found");
                }

                $this->createOrderReturnItem($order, $orderReturn, $basketItem, $item);
            }

            $this->calcOrderReturnPrice($orderReturn, $orderReturnDto->price);

            return $orderReturn;
        });

        rescue(fn() => $this->returnBillingOperations($basketItems, $order, $orderReturn));
//        rescue(fn() => $this->returnReferralBillingOperations($basketItems, $order, $orderReturn));

        return $orderReturn;
    }

    private function needCreateOrderReturn(Order $order, OrderReturnDto $orderReturnDto): bool
    {
        if (!in_array((int) $order->payment_status, [PaymentStatus::PAID, PaymentStatus::HOLD])) {
            return false;
        }

        if ($orderReturnDto->is_delivery) {
            $existOrderReturnDelivery = OrderReturn::query()
                ->where('order_id', $order->id)
                ->where('is_delivery', true)
                ->exists();

            if ($existOrderReturnDelivery) {
                return false;
            }
        }

        return true;
    }

    private function createOrderReturn(Order $order, OrderReturnDto $orderReturnDto): OrderReturn
    {
        $orderReturn = new OrderReturn();
        $orderReturn->order_id = $order->id;
        $orderReturn->customer_id = $order->customer_id;
        $orderReturn->type = $order->type;
        $orderReturn->number = OrderReturn::makeNumber($order->id);
        $orderReturn->status = $orderReturnDto->status;
        $orderReturn->price = 0;
        $orderReturn->is_delivery = $orderReturnDto->is_delivery;
        $orderReturn->save();

        return $orderReturn;
    }

    /**
     * @throws Exception
     */
    private function createOrderReturnItem(
        Order $order,
        OrderReturn $orderReturn,
        BasketItem $basketItem,
        OrderReturnItemDto $item
    ): OrderReturnItem {
        $orderReturnItem = new OrderReturnItem();
        $orderReturnItem->order_return_id = $orderReturn->id;
        $orderReturnItem->basket_item_id = $basketItem->id;
        $orderReturnItem->offer_id = $basketItem->offer_id;
        $orderReturnItem->referrer_id = $basketItem->referrer_id;
        $orderReturnItem->bundle_id = $basketItem->bundle_id;
        $orderReturnItem->type = $basketItem->type;

        if ($order->type == Basket::TYPE_MASTER) {
            /**
             * Проверяем, что указаны id билетов для возврата
             */
            if (!$item->ticket_ids) {
                throw new Exception("Returning ticket_ids for BasketItem with id={$basketItem->id} not specified");
            }
            /**
             * Проверяем, что id билетов для возврата указаны у BasketItem корзины заказа
             */
            if ($item->ticket_ids != array_intersect($basketItem->getTicketIds(), $item->ticket_ids)) {
                throw new Exception("Returning ticket_ids for BasketItem with id={$basketItem->id} not contained at order");
            }
            $basketItem->setTicketIds($item->ticket_ids);
        }
        $orderReturnItem->product = $basketItem->product;
        $orderReturnItem->name = $basketItem->name;
        $orderReturnItem->qty = $order->type == Basket::TYPE_MASTER ? count($item->ticket_ids) : $item->qty;
        /**
         * Проверяем, что кол-во возвращаемого товара не больше, чем в корзине
         */
        // Сколько уже создано возвратов
        $qtyCanceled = OrderReturnItem::query()->where('basket_item_id', $orderReturnItem->id)->sum('qty');
        if ($orderReturnItem->qty + $qtyCanceled > $basketItem->qty + $basketItem->qty_canceled) {
            throw new Exception("Returning qty for BasketItem with id={$basketItem->id} more than at order");
        }
        $orderReturnItem->price = $item->price; // $basketItem->price / $basketItem->qty * $orderReturnItem->qty;
        $orderReturnItem->commission = 0; //todo Доделать расчет суммы удержанной комиссии
        $orderReturnItem->save();

        return $orderReturnItem;
    }

    private function calcOrderReturnPrice(OrderReturn $orderReturn, ?int $price = null): void
    {
        if ($price) {
            $orderReturn->price = $price;
        } else {
            $orderReturn->priceRecalc(false);
        }
        $orderReturn->commissionRecalc();
        $orderReturn->save();
    }

    private function returnBillingOperations(Collection $basketItems, Order $order, OrderReturn $orderReturn): void
    {
        $billingListByMerchants = $this->getMerchantBillings($basketItems, $order);

        foreach ($orderReturn->items as $orderReturnItem) {
            if (!isset($billingListByMerchants[$orderReturnItem->offer_id])) {
                continue;
            }

            /** @var MerchantService $merchantService */
            $merchantService = resolve(MerchantService::class);
            $merchantService->addReturn(
                $billingListByMerchants[$orderReturnItem->offer_id]['merchant_id'],
                $billingListByMerchants[$orderReturnItem->offer_id]['id']
            );
        }
    }

    /**
     * Получить записи биллинга элементов корзины
     */
    private function getMerchantBillings(Collection $basketItems, Order $order): array
    {
        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $basketItemsByMerchants = [];
        $billingListByMerchants = [];
        foreach ($basketItems as $basketItem) {
            if ($basketItem->shipmentItem->shipment->merchant_id) {
                $basketItemsByMerchants[$basketItem->shipmentItem->shipment->merchant_id][] = $basketItem;
            }
        }

        foreach ($basketItemsByMerchants as $merchantId => $merchantBasketItems) {
            $restQuery = $merchantService->newQuery()
                ->setFilter('merchant_id', $merchantId)
                ->setFilter('offer_id', array_column($merchantBasketItems, 'offer_id'))
                ->setFilter('order_id', $order->id)
                ->setFilter('shipment_status', ShipmentStatusDto::SHIPMENT_STATUS_DONE);
            $billingList = $merchantService->merchantBillingList($restQuery, $merchantId);

            if (!isset($billingList['items'])) {
                continue;
            }

            foreach ($billingList['items'] as $billingItem) {
                $billingListByMerchants[$billingItem['offer_id']] = [
                    'id' => $billingItem['id'],
                    'merchant_id' => $merchantId,
                ];
            }
        }

        return $billingListByMerchants;
    }

    /**
     * Отправка данных о возврате в ibt-cm-ms
     * @throws \Pim\Core\PimException
     */
    private function returnReferralBillingOperations(
        Collection $basketItems,
        Order $order,
        OrderReturn $orderReturn
    ): void {
        $basketItemsOfReferral = $basketItems->filter(fn(BasketItem $basketItem) => $basketItem->referrer_id !== null);
        if ($basketItemsOfReferral->isEmpty()) {
            return;
        }

        /** @var ReferralService $referralService */
        $referralService = resolve(ReferralService::class);
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);

        $offerIds = $basketItems->pluck('offer_id')->toArray();
        $offersQuery = $offerService->newQuery();
        $offersQuery->addFields(OfferDto::entity(), 'id', 'product_id')
            ->setFilter('id', $offerIds);
        $offersInfo = $offerService->offers($offersQuery)->keyBy('id');

        if ($offersInfo->isEmpty()) {
            return;
        }

        $basketItemsOfReferral->each(function (BasketItem $basketItem) use ($orderReturn, $referralService, $order, $offersInfo) {
            $returnReferralBillOperationsDto = new ReturnReferralBillOperationDto();
            $returnReferralBillOperationsDto->setCustomerId($orderReturn->customer_id);
            $returnReferralBillOperationsDto->setOrderNumber($order->number);
            $returnReferralBillOperationsDto->setReturnDate(new Carbon($orderReturn->created_at));
            $returnReferralBillOperationsDto->setReturnNumber($orderReturn->number);
            $returnReferralBillOperationsDto->setProductId($offersInfo[$basketItem->offer_id]->product_id);

            $referralService->returnBillOperation($basketItem->referrer_id, $returnReferralBillOperationsDto);
        });
    }
}
