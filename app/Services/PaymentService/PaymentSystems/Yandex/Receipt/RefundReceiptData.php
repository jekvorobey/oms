<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Receipt;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\Tax;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\VatCode;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequest;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequestBuilder;
use MerchantManagement\Dto\MerchantDto;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\PublicEvent\PublicEventDto;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\Receipt\PaymentMode;
use YooKassa\Model\Receipt\PaymentSubject;
use YooKassa\Model\Receipt\SettlementType;
use YooKassa\Model\ReceiptCustomer;
use YooKassa\Model\ReceiptItem;
use YooKassa\Model\ReceiptType;
use YooKassa\Request\Refunds\CreateRefundRequest;
use YooKassa\Request\Refunds\CreateRefundRequestBuilder;

class RefundReceiptData extends ReceiptData
{
    /**
     * Формирование данных для возврата платежа
     */
    public function getRefundData(string $paymentId, OrderReturn $orderReturn): CreateRefundRequestBuilder
    {
        $builder = CreateRefundRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount($orderReturn->price))
            ->setCurrency(CurrencyCode::RUB)
            ->setPaymentId($paymentId)
            ->setReceiptPhone($orderReturn->order->customerPhone())
            ->setTaxSystemCode(Tax::SIMPLE_MINUS_INCOME);

        return $builder;
    }

    /**
     * Сформировать чек возврата всех позиций
     */
    public function getRefundReceiptAllItemsData(Order $order, string $paymentId): CreatePostReceiptRequestBuilder
    {
        $builder = CreatePostReceiptRequest::builder();

        $builder
            ->setType(ReceiptType::REFUND)
            ->setPaymentId($paymentId)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true);

        $builder->setItems($this->getReceiptItemsForFullRefund($order));
        $builder->setSettlements($this->getSettlements($order));

        return $builder;
    }

    public function getRefundReceiptPartiallyData(
        string $paymentId,
        OrderReturn $orderReturn
    ): CreatePostReceiptRequestBuilder {
        $builder = CreatePostReceiptRequest::builder();
        $order = $orderReturn->order;
        $builder
            ->setType(ReceiptType::REFUND)
            ->setPaymentId($paymentId)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true);

        $builder->setItems($this->getReceiptItemsForPartiallyRefund($orderReturn));
        $builder->setSettlements($this->getSettlements($order, $orderReturn));

        return $builder;
    }

    /**
     * Сформировать позиции для возврата всех позиций заказа
     */
    protected function getReceiptItemsForFullRefund(Order $order): array
    {
        $receiptItems = [];

        $merchantIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('product.merchant_id')->toArray();
        $merchants = collect();
        if (!empty($merchantIds)) {
            $merchants = $this->getMerchants($merchantIds);
        }

        $offers = collect();
        $offerIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('offer_id')->toArray();
        if ($offerIds) {
            $offers = $this->getOffers($offerIds, $order);
        }

        foreach ($order->basket->items as $item) {
            //$paymentMode = self::PAYMENT_MODE_FULL_PAYMENT; //TODO::Закомментировано до реализации IBT-433

            $itemValue = $item->price / $item->qty;
            $offer = $offers[$item->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;

            $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant);
            $receiptItems[] = new ReceiptItem([
                'description' => $item->name,
                'quantity' => $item->qty,
                'amount' => [
                    'value' => $itemValue,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => $receiptItemInfo['vat_code'],
                'payment_mode' => $receiptItemInfo['payment_mode'],
                'payment_subject' => $receiptItemInfo['payment_subject'],
//                    'agent_type' => $receiptItemInfo['agent_type'],
            ]);
        }
        if ((float) $order->delivery_price > 0) {
            $paymentMode = PaymentMode::FULL_PAYMENT;
            $deliveryPrice = $order->delivery_price;

            $receiptItems[] = new ReceiptItem([
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => $deliveryPrice,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => VatCode::CODE_DEFAULT,
                'payment_mode' => $paymentMode,
                'payment_subject' => PaymentSubject::SERVICE,
//                'agent_type' => false,
            ]);
        }

        return $receiptItems;
    }

    /**
     * Get receipt items from order
     */
    protected function getReceiptItemsForPartiallyRefund(OrderReturn $orderReturn): array
    {
        $receiptItems = [];
        $order = $orderReturn->order;

        $merchants = collect();
        $merchantIds = $orderReturn->items
            ->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])
            ->map(static function (OrderReturnItem $orderReturnItem) {
                return $orderReturnItem->basketItem->product['merchant_id'];
            });

        if (!empty($merchantIds)) {
            $merchantIds = $merchantIds->toArray();
            $merchantQuery = $this->merchantService->newQuery()
                ->addFields(MerchantDto::entity(), 'id')
                ->include('vats')
                ->setFilter('id', $merchantIds);
            $merchants = $this->merchantService->merchants($merchantQuery)->keyBy('id');
        }

        $offers = collect();
        $offerIds = $orderReturn->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('offer_id')->toArray();
        if ($offerIds) {
            if ($order->isPublicEventOrder()) {
                $publicEventQuery = $this->publicEventService->query()
                    ->addFields(PublicEventDto::class)
                    ->setFilter('offer_id', $offerIds)
                    ->include('organizer', 'sprints.ticketTypes.offer');
                $publicEvents = $this->publicEventService->findPublicEvents($publicEventQuery);

                if ($publicEvents) {
                    $offers = $publicEvents->map(function (PublicEventDto $publicEvent) {
                        $offerInfo = [];
                        collect($publicEvent->sprints)->map(function ($sprint) use ($publicEvent, &$offerInfo) {
                            array_map(function ($ticketType) use ($publicEvent, &$offerInfo) {
                                $offerInfo = new OfferDto([
                                    'id' => $ticketType['offer']['id'],
                                    'merchant_id' => $publicEvent->organizer->merchant_id,
                                ]);
                            }, $sprint['ticketTypes']);
                        });
                        return $offerInfo;
                    })->keyBy('id');
                }
            } else {
                $offersQuery = $this->offerService->newQuery()
                    ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
                    ->include('product')
                    ->setFilter('id', $offerIds);
                $offers = $this->offerService->offers($offersQuery)->keyBy('id');
            }
        }

        foreach ($orderReturn->items as $item) {
            //$paymentMode = self::PAYMENT_MODE_FULL_PAYMENT; //TODO::Закомментировано до реализации IBT-433
            $basketItem = $item->basketItem;
            $itemValue = $item->price / $item->qty;
            $offer = $offers[$basketItem->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;

            $receiptItemInfo = $this->getReceiptItemInfo($basketItem, $offer, $merchant);
            $receiptItems[] = new ReceiptItem([
                'description' => $basketItem->name,
                'quantity' => $basketItem->qty,
                'amount' => [
                    'value' => $itemValue,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => $receiptItemInfo['vat_code'],
                'payment_mode' => $receiptItemInfo['payment_mode'],
                'payment_subject' => $receiptItemInfo['payment_subject'],
//                    'agent_type' => $receiptItemInfo['agent_type'],
            ]);
        }
        if ($orderReturn->is_delivery) {
            $paymentMode = PaymentMode::FULL_PAYMENT;
            $receiptItems[] = new ReceiptItem([
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => $orderReturn->price,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => VatCode::CODE_DEFAULT,
                'payment_mode' => $paymentMode,
                'payment_subject' => PaymentSubject::SERVICE,
//                'agent_type' => false,
            ]);
        }

        return $receiptItems;
    }

    /**
     * Добавление признаков оплаты (чек зачета предоплаты и обычная оплата)
     */
    private function getSettlements(Order $order, ?OrderReturn $orderReturn = null): array
    {
        $settlements = [];

        if ($orderReturn) {
            $refundSum = $orderReturn->price > 0 ? $orderReturn->price : $orderReturn->items->sum('price');
        } else {
            $refundSum = 0;
        }

        if ($order->spent_certificate > 0) {
            $restReturnPrice = $order->price - $order->done_return_sum;
            $restCashlessReturnPrice = min(0, $restReturnPrice - $order->spent_certificate);
            $priceToReturn = $orderReturn->price ?? $order->price;
            $returnPrepayment = min(0, $priceToReturn - $restCashlessReturnPrice);
            $returnCashless = min(0, $priceToReturn - $returnPrepayment);

            if ($returnCashless > 0) {
                $settlements[] = [
                    'type' => SettlementType::CASHLESS,
                    'amount' => [
                        'value' => $returnCashless,
                        'currency' => CurrencyCode::RUB,
                    ],
                ];
            }

            $settlements[] = [
                'type' => SettlementType::PREPAYMENT,
                'amount' => [
                    'value' => $returnPrepayment,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        } else {
            $settlements[] = [
                'type' => SettlementType::CASHLESS,
                'amount' => [
                    'value' => $refundSum ?: $order->price,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        }

        return $settlements;
    }
}
