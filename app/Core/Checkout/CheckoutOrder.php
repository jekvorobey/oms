<?php

namespace App\Core\Checkout;

use App\Core\Order\OrderWriter;
use App\Models\Basket\Basket;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Order\Order;
use App\Models\Order\OrderBonus;
use App\Models\Order\OrderDiscount;
use App\Models\Order\OrderPromoCode;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentSystem;
use Exception;
use Greensight\Customer\Dto\CustomerBonusDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Support\Facades\DB;

class CheckoutOrder
{
    // primary data
    /** @var int */
    public $customerId;
    /** @var int */
    public $basketId;

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
    /** @var string[] */
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

    public static function fromArray(array $data): self
    {
        $order = new self();
        @([
            'customerId' => $order->customerId,
            'basketId' => $order->basketId,
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
            'deliveries' => $deliveries
        ] = $data);

        foreach ($prices as $priceData) {
            $checkoutItemPrice = CheckoutItemPrice::fromArray($priceData);
            $order->prices[$checkoutItemPrice->offerId] = $checkoutItemPrice;
        }

        foreach ($deliveries as $deliveryData) {
            $order->deliveries[] = CheckoutDelivery::fromArray($deliveryData);
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

    /**
     * @throws Exception
     * @return int
     */
    public function save(): int
    {
        return DB::transaction(function () {
            $this->commitPrices();
            $order = $this->createOrder();
            $this->debitingBonus($order);
            $this->createShipments($order);
            $this->createPayment($order);
            $this->createOrderDiscounts($order);
            $this->createOrderPromoCodes($order);
            $this->createOrderBonuses($order);

            return $order->id;
        });
    }

    /**
     * @throws Exception
     */
    private function commitPrices(): void
    {
        $basket = $this->basket();
        foreach ($basket->items as $item) {
            $priceItem = $this->prices[$item->offer_id] ?? null;
            if (!$priceItem) {
                throw new Exception('price is not supplied for basket item');
            }
            $item->cost = $priceItem->cost;
            $item->price = $priceItem->price;
            $item->bonus_spent = $priceItem->bonusSpent ?? 0;
            $item->bonus_discount = $priceItem->bonusDiscount ?? 0;
            $item->save();
        }
    }

    private function createOrder(): Order
    {
        $order = new Order();
        $order->customer_id = $this->customerId;
        $order->number = Order::makeNumber();
        $order->basket_id = $this->basketId;
        $order->confirmation_type = $this->confirmationTypeId;
        $order->cost = $this->cost;
        $order->price = $this->price;
        $order->spent_bonus = $this->spentBonus;
        $order->added_bonus = $this->addedBonus;
        $order->certificates = $this->certificates;

        $order->delivery_type = $this->deliveryTypeId;
        $order->delivery_cost = $this->deliveryCost;
        $order->delivery_price = $this->deliveryPrice;

        $order->save();
        return $order;
    }

    /**
     * @param Order $order
     */
    private function debitingBonus(Order $order)
    {
        $totalBonusSpent = 0;
        $basket = $this->basket();
        foreach ($basket->items as $item) {
            $totalBonusSpent += $item->bonus_spent;
        }

        if ($totalBonusSpent > 0) {
            $customerService = resolve(CustomerService::class);
            $customerService->debitingBonus($this->customerId, $order->id, (string)$order->id, $totalBonusSpent);
        }
    }

    /**
     * @param Order $order
     */
    private function createOrderDiscounts(Order $order)
    {
        /** @var OrderDiscount $discount */
        foreach ($this->discounts as $discount) {
            $discount->order_id = $order->id;
            $discount->save();
        }
    }

    /**
     * @param Order $order
     */
    private function createOrderPromoCodes(Order $order)
    {
        /** @var OrderPromoCode $promoCode */
        foreach ($this->promoCodes as $promoCode) {
            $promoCode->order_id = $order->id;
            $promoCode->save();
        }
    }

    /**
     * @param Order $order
     */
    private function createOrderBonuses(Order $order)
    {
        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);

        /** @var OrderBonus $bonus */
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
     * @param Order $order
     * @throws Exception
     */
    private function createShipments(Order $order): void
    {
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
            $delivery->delivery_time_start = $checkoutDelivery->deliveryTimeStart;
            $delivery->delivery_time_end = $checkoutDelivery->deliveryTimeEnd;
            $delivery->delivery_time_code = $checkoutDelivery->deliveryTimeCode;
            $delivery->dt = $checkoutDelivery->dt;
            $delivery->pdd = $checkoutDelivery->pdd;

            $delivery->save();

            foreach ($checkoutDelivery->shipments as $checkoutShipment) {
                $shipment = new Shipment();
                $shipment->delivery_id = $delivery->id;
                $shipment->merchant_id = $checkoutShipment->merchantId;
                $shipment->psd = $checkoutShipment->psd;
                $shipment->required_shipping_at = $checkoutShipment->psd;
                $shipment->store_id = $checkoutShipment->storeId;
                $shipment->number = Shipment::makeNumber($order->number, $i, $shipmentNumber++);
                $shipment->save();

                foreach ($checkoutShipment->items as $offerId) {
                    $basketItemId = $offerToBasketMap[$offerId] ?? null;
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
    }

    /**
     * @param Order $order
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
            $basket = Basket::query()->where('id', $this->basketId)->first();
        }
        return $basket;
    }

    private function offerToBasketMap(): array
    {
        $result = [];
        $basket = $this->basket();
        foreach ($basket->items as $basketItem) {
            $result[$basketItem->offer_id] = $basketItem->id;
        }
        return $result;
    }
}
