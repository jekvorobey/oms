<?php

namespace App\Core\Checkout;

use App\Core\Order\OrderWriter;
use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Order\Order;
use App\Models\Order\OrderBonus;
use App\Models\Order\OrderDiscount;
use App\Models\Order\OrderPromoCode;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use Carbon\Carbon;
use Exception;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Customer\Dto\CustomerBonusDto;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Store\Dto\StockDto;
use Greensight\Store\Services\StockService\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pim\Dto\PublicEvent\PublicEventTicketTypeDto;
use Pim\Dto\PublicEvent\TicketDto;
use Pim\Dto\PublicEvent\TicketStatus;
use Pim\Dto\Search\ProductQuery;
use Pim\Services\CertificateService\CertificateService;
use Pim\Services\PublicEventTicketService\PublicEventTicketService;
use Pim\Services\PublicEventTicketTypeService\RestPublicEventTicketTypeService;
use Pim\Services\SearchService\SearchService;
use Symfony\Component\Finder\Exception\AccessDeniedException;

class CheckoutOrder
{
    // primary data
    /** @var int */
    public $customerId;
    /** @var int */
    public $basketId;

    /** @var string */
    public $receiverName;
    /** @var string */
    public $receiverPhone;
    /** @var string */
    public $receiverEmail;

    // marketing data
    /** @var float */
    public $cost;
    /** @var float */
    public $price;
    /** @var int */
    public $paymentMethodId;
    /** @var int */
    public $confirmationTypeId;
    /** @var int */
    public $spentBonus;
    /** @var int */
    public $addedBonus;
    /** @var array */
    public $certificates;
    /** @var CheckoutItemPrice[] */
    public $prices;
    /** @var OrderPromoCode[] */
    public $promoCodes;
    /** @var OrderDiscount[] */
    public $discounts;
    /** @var OrderBonus[] */
    public $bonuses;

    // delivery data
    /** @var int */
    public $deliveryTypeId;
    /** @var int */
    public $deliveryCost;
    /** @var int */
    public $deliveryPrice;

    /** @var CheckoutDelivery[] */
    public $deliveries;

    /** @var Collection|CheckoutPublicEvent[] */
    public $publicEvents;

    public static function fromArray(array $data): self
    {
        $order = new self();
        @([
            'customerId' => $order->customerId,
            'basketId' => $order->basketId,
            'receiverName' => $order->receiverName,
            'receiverPhone' => $order->receiverPhone,
            'receiverEmail' => $order->receiverEmail,
            'cost' => $order->cost,
            'price' => $order->price,
            'paymentMethodId' => $order->paymentMethodId,
            'confirmationTypeId' => $order->confirmationTypeId,
            'spentBonus' => $order->spentBonus,
            'addedBonus' => $order->addedBonus,
            'promoCodes' => $promoCodes,
            'certificates' => $order->certificates,
            'prices' => $prices,
            'discounts' => $discounts,
            'bonuses' => $bonuses,

            'deliveryTypeId' => $order->deliveryTypeId,
            'deliveryPrice' => $order->deliveryPrice,
            'deliveryCost' => $order->deliveryCost,
            'deliveries' => $deliveries,

            'publicEvents' => $publicEvents,
        ] = $data);

        foreach ($prices as $priceData) {
            $checkoutItemPrice = CheckoutItemPrice::fromArray($priceData);
            $order->prices[$checkoutItemPrice->basketItemId] = $checkoutItemPrice;
        }

        foreach ($deliveries as $deliveryData) {
            $order->deliveries[] = CheckoutDelivery::fromArray($deliveryData);
        }

        $order->publicEvents = collect();
        foreach ($publicEvents as $publicEvent) {
            $order->publicEvents->push(CheckoutPublicEvent::fromArray($publicEvent));
        }

        $order->promoCodes = [];
        foreach ($promoCodes as $promoCode) {
            $order->promoCodes[] = new OrderPromoCode($promoCode);
        }

        $order->discounts = [];
        foreach ($discounts as $discount) {
            $order->discounts[] = new OrderDiscount($discount);
        }

        $order->bonuses = [];
        foreach ($bonuses as $bonus) {
            $order->bonuses[] = new OrderBonus($bonus);
        }

        return $order;
    }

    public function save(): array
    {
        return DB::transaction(function () {
            $this->replaceProductBasketItemsToNewBasket();
            $this->checkProducts();
            $this->checkOffersStocks();
            $this->commitPrices();

            $order = $this->createOrder();
            $this->spendCertificates($order);
            $this->debitingBonus($order);
            $this->createShipments($order);
            $this->createTickets($order);

            if ($order->paymentMethod->is_need_create_payment) {
                $this->createPayment($order);
            }
            $this->createOrderDiscounts($order);
            $this->createOrderPromoCodes($order);
            $this->createOrderBonuses($order);

            return [$order->id, $order->number];
        });
    }

    private function checkProducts(): void
    {
        if (!$this->basket()->isProductBasket()) {
            return;
        }
        $basket = $this->basket();
        $offerIds = $basket->items->pluck('offer_id');
        /** @var SearchService $searchService */
        $searchService = resolve(SearchService::class);
        $customerService = resolve(CustomerService::class);

        $searchProductQuery = (new ProductQuery());
        $searchProductQuery->active = true;
        $searchProductQuery->offer_id = $offerIds->toArray();
        $searchProductQuery->fields([ProductQuery::OFFER_ID, ProductQuery::FREE_BUY]);
        $offersInfo = collect(
            $searchService->products($searchProductQuery)->products
        )->keyBy(ProductQuery::OFFER_ID);

        /** @var CustomerDto $customer */
        $customer = $customerService->customers((new RestQuery())->setFilter('id', $basket->customer_id))->first();

        if ($customer->canBuy()) {
            return;
        }

        foreach ($offersInfo as $offerInfo) {
            if (!$offerInfo[ProductQuery::FREE_BUY]) {
                throw new AccessDeniedException(
                    'Товар с закрытой продажей доступен только для Профессионалов'
                );
            }
        }
    }

    /**
     * Проверить, что кол-во товаров в корзине еще доступно к покупке
     * @throws Exception
     */
    private function checkOffersStocks(): void
    {
        $basket = $this->basket();
        if (!$basket->isProductBasket() && !$basket->isPublicEventBasket()) {
            return;
        }

        $offerIds = [];
        $storeIds = [];
        $ticketTypeIds = [];
        foreach ($basket->items as $item) {
            $offerIds[] = $item->offer_id;
            if ($basket->isProductBasket()) {
                if (!in_array($item->getStoreId(), $storeIds)) {
                    $storeIds[] = $item->getStoreId();
                }
            } elseif ($basket->isPublicEventBasket()) {
                $ticketTypeIds[] = $item->getTicketTypeId();
            }
        }

        $stocks = collect();
        $ticketTypes = collect();
        if ($basket->isProductBasket()) {
            if ($offerIds && $storeIds) {
                /** @var StockService $stockService */
                $stockService = resolve(StockService::class);
                $stocksQuery = $stockService->newQuery()
                    ->setFilter('offer_id', $offerIds)
                    ->setFilter('store_id', $storeIds);
                $stocks = $stockService->stocks($stocksQuery);
            }
        } elseif ($basket->isPublicEventBasket()) {
            if ($ticketTypeIds) {
                /** @var RestPublicEventTicketTypeService $ticketTypeService */
                $ticketTypeService = resolve(RestPublicEventTicketTypeService::class);
                $ticketTypes = $ticketTypeService->query()
                    ->setFilter('id', $ticketTypeIds)
                    ->addFields('tickettype', 'id', 'qty', 'placesOccupied')
                    ->get();
            }
        }

        foreach ($basket->items as $item) {
            if ($basket->isProductBasket()) {
                /** @var StockDto $stock */
                $stock = $stocks
                    ->where('store_id', $item->getStoreId())
                    ->where('offer_id', $item->offer_id)
                    ->first();
                if (!$stock || $item->qty > $stock->qty) {
                    throw new Exception("Qty product offer by id {$item->offer_id} more than available at store with id {$item->getStoreId()}", 400);
                }
            } elseif ($basket->isPublicEventBasket()) {
                /** @var PublicEventTicketTypeDto $ticketType */
                $ticketType = $ticketTypes->where('id', $item->getTicketTypeId())->first();
                if (!$ticketType) {
                    throw new Exception("TicketType by id={$item->getTicketTypeId()} not found", 404);
                }
                $qtyFree = $ticketType->qty - $ticketType->placesOccupied;
                if ($item->qty > $qtyFree) {
                    throw new Exception("Qty public event offer by id {$item->offer_id} more than available", 400);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function commitPrices(): void
    {
        $basket = $this->basket();
        foreach ($basket->items as $item) {
            $priceItem = $this->prices[$item->id] ?? null;
            if (!$priceItem) {
                throw new Exception('price is not supplied for basket item');
            }
            $item->cost = $priceItem->cost;
            $item->price = $priceItem->price;
            $item->unit_price = $item->qty != 0 ? (float) $priceItem->price / $item->qty : 0;
            $item->bonus_spent = $priceItem->bonusSpent ?? 0;
            $item->bonus_discount = $priceItem->bonusDiscount ?? 0;
            $item->save();
        }
    }

    private function createOrder(): Order
    {
        $order = new Order();
        $order->customer_id = $this->customerId;
        $order->basket_id = $this->basketId;
        $order->type = $this->basket()->type;
        $order->receiver_name = $this->receiverName;
        $order->receiver_email = $this->receiverEmail;
        $order->receiver_phone = $this->receiverPhone;
        $order->confirmation_type = $this->confirmationTypeId;
        $order->cost = $this->cost;
        $order->price = $this->price;
        $order->spent_bonus = $this->spentBonus;
        $order->added_bonus = $this->addedBonus;
        $order->certificates = $this->certificates;
        if ($order->certificates) {
            $order->spent_certificate = array_sum(array_column((array) $order->certificates, 'amount'));
            // Т.к. цена приходит с фронта, с учетом всех примененных скидок и подарочных сертификатов, номинал использованного сертификата нужно вернуть в цену
            $order->price += $order->spent_certificate;
        }

        $order->delivery_type = $this->deliveryTypeId;
        $order->delivery_cost = $this->deliveryCost;
        $order->delivery_price = $this->deliveryPrice;

        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = PaymentMethod::findOrFail($this->paymentMethodId);

        $order->is_postpaid = $paymentMethod->is_postpaid;
        $order->status = OrderStatus::defaultValue();
        $order->payment_status = PaymentStatus::NOT_PAID;
        $order->payment_method_id = $this->paymentMethodId;

        $order->save();

        return $order;
    }

    private function replaceProductBasketItemsToNewBasket(): void
    {
        $basket = $this->basket();

        if ($basket && $basket->isProductBasket()) {
            $savedBasketItems = $basket->items;
            $basketItemsFromRequest = $this->getBasketItemsFromRequest();
            $basketItemsToReplace = collect();

            foreach ($savedBasketItems as $basketItem) {
                $basketItemFromRequest = $basketItemsFromRequest
                    ->where('offer_id', $basketItem->offer_id)
                    ->where('bundle_id', $basketItem->bundle_id)
                    ->where('bundle_item_id', $basketItem->bundle_item_id)
                    ->first();

                if (!$basketItemFromRequest) {
                    $basketItemsToReplace->push($basketItem);
                }
            }

            if ($basketItemsToReplace->isNotEmpty()) {
                $basketForReplacing = new Basket();
                $basketForReplacing->customer_id = $basket->customer_id;
                $basketForReplacing->type = $basket->type;
                $basketForReplacing->is_belongs_to_order = false;
                $basketForReplacing->save();

                $basketItemsToReplace->each(function (BasketItem $basketItem) use ($basketForReplacing) {
                    $basketItem->basket_id = $basketForReplacing->id;
                    $basketItem->save();
                });
                $basket->load('items');
            }
        }
    }

    private function getBasketItemsFromRequest(): Collection
    {
        $result = collect();
        $basket = $this->basket();

        if ($basket) {
            $savedBasketItems = $basket->items;
            $shipmentItems = collect($this->deliveries)
                ->pluck('shipments.*.items')
                ->flatten(2);

            foreach ($shipmentItems as [$offerId, $bundleId, $bundleItemId]) {
                $savedBasketItem = $savedBasketItems
                    ->where('offer_id', $offerId)
                    ->where('bundle_id', $bundleId)
                    ->where('bundle_item_id', $bundleItemId);

                if ($savedBasketItem->isNotEmpty()) {
                    $result->push($savedBasketItem->first());
                }
            }
        }

        return $result;
    }

    private function debitingBonus(Order $order)
    {
        $totalBonusSpent = 0;
        $basket = $this->basket();
        foreach ($basket->items as $item) {
            $totalBonusSpent += $item->bonus_spent;
        }

        if ($totalBonusSpent > 0) {
            $customerService = resolve(CustomerService::class);
            $customerService->debitingBonus($this->customerId, $order->id, (string) $order->id, $totalBonusSpent);
        }
    }

    private function createOrderDiscounts(Order $order)
    {
        foreach ($this->discounts as $discount) {
            $discount->order_id = $order->id;
            $discount->save();
        }
    }

    private function createOrderPromoCodes(Order $order)
    {
        foreach ($this->promoCodes as $promoCode) {
            $promoCode->order_id = $order->id;
            $promoCode->save();
        }
    }

    private function createOrderBonuses(Order $order)
    {
        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);

        foreach ($this->bonuses as $bonus) {
            $customerBonus = new CustomerBonusDto();
            $customerBonus->customer_id = $this->customerId;
            $customerBonus->name = (string) $order->id;
            $customerBonus->value = $bonus->bonus;
            $customerBonus->status = CustomerBonusDto::STATUS_ON_HOLD;
            $customerBonus->type = CustomerBonusDto::TYPE_ORDER;
            $customerBonus->expiration_date = null; // Без ограничений для статуса STATUS_ON_HOLD
            $customerBonus->order_id = $order->id;
            $customerBonusId = $customerService->createBonus($customerBonus);

            $bonus->status = OrderBonus::STATUS_ON_HOLD;
            $bonus->customer_bonus_id = $customerBonusId;
            $bonus->order_id = $order->id;
            $bonus->save();
        }
    }

    /**
     * @throws Exception
     */
    private function createShipments(Order $order): void
    {
        if (!$this->deliveries) {
            return;
        }

        $offerToBasketMap = $this->offerToBasketMap();

        $shipmentNumber = 1;
        foreach ($this->deliveries as $i => $checkoutDelivery) {
            $delivery = new Delivery();
            $delivery->order_id = $order->id;
            $delivery->number = Delivery::makeNumber($order->number, ++$i);

            $delivery->delivery_method = $checkoutDelivery->deliveryMethod;
            $delivery->delivery_service = $checkoutDelivery->deliveryService;
            $delivery->tariff_id = $checkoutDelivery->tariffId;
            $delivery->cost = $checkoutDelivery->cost;

            $delivery->receiver_name = $checkoutDelivery->receiverName;
            $delivery->receiver_email = $checkoutDelivery->receiverEmail;
            $delivery->receiver_phone = $checkoutDelivery->receiverPhone;
            $delivery->delivery_address = $checkoutDelivery->deliveryAddress;

            $delivery->point_id = $checkoutDelivery->pointId;
            $delivery->delivery_at = $checkoutDelivery->selectedDate;
            $delivery->delivery_time_start = $checkoutDelivery->deliveryTimeStart ?
                Carbon::createFromFormat('H', $checkoutDelivery->deliveryTimeStart) :
                null;
            $delivery->delivery_time_end = $checkoutDelivery->deliveryTimeEnd ?
                Carbon::createFromFormat('H', $checkoutDelivery->deliveryTimeEnd) :
                null;
            $delivery->delivery_time_code = $checkoutDelivery->deliveryTimeCode;
            $delivery->dt = $checkoutDelivery->dt;
            $delivery->pdd = $checkoutDelivery->pdd;
            if ($order->isProductOrder()) {
                $delivery->payment_status = $order->paymentMethod->is_need_create_payment
                    ? PaymentStatus::NOT_PAID
                    : PaymentStatus::WAITING;
            }

            $delivery->save();

            foreach ($checkoutDelivery->shipments as $checkoutShipment) {
                $shipment = new Shipment();
                $shipment->delivery_id = $delivery->id;
                $shipment->merchant_id = $checkoutShipment->merchantId;
                $shipment->psd = $checkoutShipment->psd;
                $shipment->required_shipping_at = $checkoutShipment->psd;
                $shipment->store_id = $checkoutShipment->storeId;
                $shipment->number = Shipment::makeNumber($order->number, $i, $shipmentNumber++);
                if ($order->isProductOrder()) {
                    $shipment->payment_status = $order->paymentMethod->is_need_create_payment
                        ? PaymentStatus::NOT_PAID
                        : PaymentStatus::WAITING;
                }
                $shipment->save();

                foreach ($checkoutShipment->items as [$offerId, $bundleId, $bundleItemId]) {
                    $key = $bundleId ?
                        $offerId . ':' . $bundleId . ':' . $bundleItemId :
                        $offerId;
                    $basketItemId = $offerToBasketMap[$key] ?? null;
                    if (!$basketItemId) {
                        throw new Exception('shipment has which not in basket');
                    }
                    $shipmentItem = new ShipmentItem();
                    $shipmentItem->shipment_id = $shipment->id;
                    $shipmentItem->basket_item_id = $basketItemId;

                    $shipmentItem->save();
                }
            }
        }

        if (!$order->paymentMethod->is_need_create_payment) {
            $order->payment_status = PaymentStatus::WAITING;
            $order->save();
        }
    }

    /**
     * @throws Exception
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    private function createTickets(Order $order): void
    {
        if ($this->publicEvents->isEmpty()) {
            return;
        }

        /** @var PublicEventTicketService $ticketService */
        $ticketService = resolve(PublicEventTicketService::class);

        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = BasketItem::query()->where('basket_id', $this->basketId)->get();
        /** @var Collection|CheckoutPublicEvent[] $publicEvents */
        $publicEvents = $this->publicEvents->keyBy('offerId');
        foreach ($basketItems as $basketItem) {
            $product = $basketItem->product;

            if (!$publicEvents->has($basketItem->offer_id)) {
                throw new Exception("Не найдено билетов для offer_id={$basketItem->offer_id}");
            }

            $tickets = $publicEvents[$basketItem->offer_id]->tickets;
            if ($basketItem->qty != $tickets->count()) {
                throw new Exception(
                    "Кол-во билетов для offer_id={$basketItem->offer_id} из корзины не совпадает с чекаутом"
                );
            }

            $ticketIds = [];
            try {
                foreach ($tickets as $ticket) {
                    $ticketDto = new TicketDto();
                    $ticketDto->sprint_id = $product['sprint_id'];
                    $ticketDto->status_id = TicketStatus::STATUS_ACTIVE;
                    $ticketDto->type_id = $product['ticket_type_id'];
                    $ticketDto->first_name = $ticket->firstName;
                    $ticketDto->middle_name = $ticket->middleName;
                    $ticketDto->last_name = $ticket->lastName;
                    $ticketDto->phone = $ticket->phone;
                    $ticketDto->email = $ticket->email;
                    $ticketDto->profession_id = $ticket->professionId;

                    $ticketIds[] = $ticketService->createTicket($ticketDto);
                }


                $basketItem->setTicketIds($ticketIds);
                $basketItem->save();
            } catch (\Throwable $e) {
                foreach ($ticketIds as $ticketId) {
                    $ticketService->deleteTicket($ticketId);
                }

                throw new Exception("Ошибка при сохранении билета: {$e->getMessage()}");
            }
        }
    }

    private function spendCertificates(Order $order)
    {
        $amount = 0;
        $certificates = (array) $order->certificates;
        foreach ($certificates as $certificate) {
            $amount += $certificate['amount'];
        }
        if ($amount > 0) {
            resolve(CertificateService::class)->spend($amount, $this->customerId, $order->id, $order->number);
        }
    }

    /**
     * @throws Exception
     */
    private function createPayment(Order $order): void
    {
        $payment = new Payment();
        $payment->payment_method = $this->paymentMethodId;
        $payment->payment_system = PaymentSystem::YANDEX;
        $payment->order_id = $order->id;
        $payment->sum = $this->price;

        $writer = new OrderWriter();
        $writer->setPayments($order, collect([$payment]));
    }

    private function basket(): ?Basket
    {
        static $basket = null;
        if (!$basket) {
            /** @var Basket $basket */
            $basket = Basket::query()
                ->where('id', $this->basketId)
                ->with('items')
                ->first();
        }

        return $basket ?? null;
    }

    private function offerToBasketMap(): array
    {
        $result = [];
        $basket = $this->basket();
        foreach ($basket->items as $basketItem) {
            $key = $basketItem->bundle_id ?
                $basketItem->offer_id . ':' . $basketItem->bundle_id . ':' . $basketItem->bundle_item_id :
                $basketItem->offer_id;
            $result[$key] = $basketItem->id;
        }
        return $result;
    }
}
