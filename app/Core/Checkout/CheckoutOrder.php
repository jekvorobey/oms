<?php

namespace App\Core\Checkout;

use App\Core\Order\OrderWriter;
use App\Models\Basket\Basket;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentSystem;
use Carbon\Carbon;
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
    public $spentBonus;
    /** @var int */
    public $addedBonus;
    /** @var string */
    public $promocode;
    /** @var string[] */
    public $certificates;
    /** @var CheckoutItemPrice[] */
    public $prices;
    
    // delivery data
    /** @var int */
    public $deliveryTypeId;
    /** @var int */
    public $deliveryMethodId;
    /** @var float */
    public $deliveryCost;
    /** @var array */
    public $deliveryAddress;
    /** @var string */
    public $receiverName;
    /** @var string */
    public $receiverPhone;
    /** @var string */
    public $receiverEmail;
    
    /** @var CheckoutDelivery[] */
    public $deliveries;
    
    // ========== runtime data
    /** @var Order */
    private $order;
    
    public static function fromArray(array $data): self
    {
        $order = new self();
        @([
            'customerId' => $order->customerId,
            'basketId' => $order->basketId,
            'cost' => $order->cost,
            'price' => $order->price,
            'paymentMethodId' => $order->paymentMethodId,
            'spentBonus' => $order->spentBonus,
            'addedBonus' => $order->addedBonus,
            'promocode' => $order->promocode,
            'certificates' => $order->certificates,
            'prices' => $prices,
            
            'deliveryTypeId' => $order->deliveryTypeId,
            'deliveryMethodId' => $order->deliveryMethodId,
            'deliveryCost' => $order->deliveryCost,
            'deliveryAddress' => $order->deliveryAddress,
            'receiverName' => $order->receiverName,
            'receiverPhone' => $order->receiverPhone,
            'receiverEmail' => $order->receiverEmail,
            'deliveries' => $deliveries
        ] = $data);
        
        foreach ($prices as $priceData) {
            $checkoutItemPrice = CheckoutItemPrice::fromArray($priceData);
            $order->prices[$checkoutItemPrice->offerId] = $checkoutItemPrice;
        }
        
        foreach ($deliveries as $deliveryData) {
            $order->deliveries[] = CheckoutDelivery::fromArray($deliveryData);
        }
        
        return $order;
    }
    
    public function save()
    {
        DB::transaction(function () {
            $this->commitPrices();
            $this->createOrder();
            $this->createShipments();
            $this->createPayment();
        });
    }
    
    private function commitPrices(): void
    {
        $basket = $this->basket();
        foreach ($basket->items as $item) {
            $priceItem = $this->prices[$item->offer_id] ?? null;
            if (!$priceItem) {
                throw new \Exception('price is not supplied for basket item');
            }
            $item->cost = $priceItem->cost;
            $item->price = $priceItem->price;
            $item->discount = $priceItem->cost - $priceItem->price;
            $item->save();
        }
    }
    
    private function createOrder(): void
    {
        $order = new Order();
        $order->customer_id = $this->customerId;
        $order->number = Order::makeNumber($this->customerId);
        $order->basket_id = $this->basketId;
        $order->cost = $this->cost;
        $order->price = $this->price;
        $order->spent_bonus = $this->spentBonus;
        $order->added_bonus = $this->addedBonus;
        $order->promocode = $this->promocode;
        // todo save certificates
        
        $order->delivery_method = $this->deliveryMethodId;
        $order->delivery_type = $this->deliveryTypeId;
        $order->delivery_cost = $this->deliveryCost;
        $order->delivery_address = $this->deliveryAddress;
        
        $order->receiver_name = $this->receiverName;
        $order->receiver_email = $this->receiverEmail;
        $order->receiver_phone = $this->receiverPhone;
        
        $order->save();
        $this->order = $order;
    }
    
    private function createShipments(): void
    {
        $offerToBasketMap = $this->offerToBasketMap();
        
        $shipmentNumber = 1;
        foreach ($this->deliveries as $i => $checkoutDelivery) {
            $delivery = new Delivery();
            $delivery->order_id = $this->order->id;
            $delivery->number = Delivery::makeNumber($this->order->number, ++$i);
            
            $delivery->delivery_method = $checkoutDelivery->deliveryMethod;
            $delivery->delivery_service = $checkoutDelivery->deliveryService;
            $delivery->tariff_id = $checkoutDelivery->tariffId;
            $delivery->cost = $checkoutDelivery->cost;
            
            $delivery->point_id = $checkoutDelivery->pointId;
            $delivery->delivery_at = $checkoutDelivery->selectedDate;
            
            $delivery->save();
            
            foreach ($checkoutDelivery->shipments as $checkoutShipment) {
                $shipment = new Shipment();
                $shipment->delivery_id = $delivery->id;
                $shipment->merchant_id = 1;// todo
                $shipment->required_shipping_at = Carbon::now()->addDays(5);
                $shipment->store_id = $checkoutShipment->storeId;
                $shipment->number = Shipment::makeNumber($delivery->number, $shipmentNumber++);
                $shipment->cost = $checkoutShipment->cost;
                
                $shipment->save();
                
                foreach ($checkoutShipment->items as $offerId) {
                    $basketItemId = $offerToBasketMap[$offerId] ?? null;
                    if (!$basketItemId) {
                        throw new \Exception('shipment has which not in basket');
                    }
                    $shipmentItem = new ShipmentItem();
                    $shipmentItem->shipment_id = $shipment->id;
                    $shipmentItem->basket_item_id = $basketItemId;
                    
                    $shipmentItem->save();
                }
            }
        }
    }
    
    private function createPayment(): void
    {
        $payment = new Payment();
        $payment->type = $this->paymentMethodId;
        $payment->payment_system = PaymentSystem::YANDEX;
        $payment->order_id = $this->order->id;
        $payment->sum = $this->price;
    
        $writer = new OrderWriter();
        $writer->setPayments($this->order, collect([$payment]));
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
