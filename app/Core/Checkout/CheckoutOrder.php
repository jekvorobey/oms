<?php

namespace App\Core\Checkout;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
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
    
    public static function fromArray(array $data): self
    {
        $order = new self();
        @([
            'customerId' => $order->customerId,
            'basketId' => $order->basketId,
            'cost' => $order->cost,
            'price' => $order->price,
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
        $order->basket_id = $this->basketId;
        $order->cost = $this->cost;
        $order->price = $this->price;
        $order->spent_bonus = $this->spentBonus;
        $order->added_bonus = $this->addedBonus;
        $order->promocode = $this->promocode;
    }
    
    private function createShipments(): void
    {
    }
    
    private function createPayment(): void
    {
    }
    
    private function basket(): ?Basket
    {
        static $basket = null;
        if (!$basket) {
            $basket = Basket::query()->where('id', $this->basketId)->first();
        }
        return $basket;
    }
}
