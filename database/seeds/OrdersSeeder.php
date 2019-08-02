<?php

use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\DeliveryMethod;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\ReserveStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

/**
 * Class OrdersSeeder
 */
class OrdersSeeder extends Seeder
{
    /** @var int */
    const FAKER_SEED = 123456;
    
    /** @var int */
    const ORDERS_COUNT = 100;
    
    /**
     * @throws PimException
     */
    public function run()
    {
        $faker = Faker\Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);
    
        $orders = collect();
        for ($i = 0; $i < self::ORDERS_COUNT; $i++) {
            $order = new Order();
            $order->customer_id = $this->customerId($faker);
            $order->number = 'IBT' . $faker->dateTimeThisYear()->format('Ymdhis');
            $order->cost = $faker->numberBetween(1, 1000);
            $order->status = $faker->randomElement(OrderStatus::validValues());
            $order->reserve_status = $faker->randomElement(ReserveStatus::validValues());
            $order->delivery_type = $faker->randomElement(DeliveryType::validValues());
            $order->delivery_method = $faker->randomElement(DeliveryMethod::validValues());
            $order->created_at = $faker->dateTimeThisYear();
            $order->processing_time = $order->created_at->modify('+1 days');
            $order->delivery_time = $order->created_at->modify('+3 days');
            $order->comment = $faker->realText();
            $order->save();
            
            $orders->push($order);
        }
        
        foreach ($orders as $order) {
            $basket = new Basket();
            $basket->customer_id = $this->customerId($faker);
            $basket->order_id = $order->id;
            $basket->save();
        }
    
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $restQuery = $offerService->newQuery();
        $restQuery->addFields(OfferDto::entity(), 'id', 'product_id');
        $offers = $offerService->offers($restQuery);
    
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        $restQuery = $productService->newQuery();
        $restQuery->addFields(ProductDto::entity(), 'id', 'name')
            ->setFilter('id', $offers->pluck('product_id'));
        $products = $productService->products($restQuery)->keyBy('id');
        
        $baskets = Basket::query()->select('id')->get();
        foreach ($baskets as $basket) {
            /** @var Collection|OfferDto[] $basketOffers */
            $basketOffers = $offers->random(rand(3, 5));
            
            foreach ($basketOffers as $basketOffer) {
                $basketItem = new BasketItem();
                $basketItem->basket_id = $basket->id;
                $basketItem->offer_id = $basketOffer->id;
                $basketItem->name = $products[$basketOffer->product_id]->name;
                $basketItem->qty = $faker->randomDigit;
                $basketItem->price = rand(1, 10000);
                $basketItem->save();
            }
        }
    }
    
    /**
     * @param  \Faker\Generator  $faker
     * @return int
     */
    protected function customerId(Faker\Generator $faker): int
    {
        return $faker->randomElement([1, 2, 3, 4, 5]);
    }
}
