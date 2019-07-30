<?php

use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\DeliveryMethod;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\ReserveStatus;
use EloquentPopulator\Populator;
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
    /** @var int */
    const BASKETS_COUNT = 100;
    
    /**
     * @throws PimException
     */
    public function run()
    {
        $faker = Faker\Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);
    
        $populator = new Populator($faker);
        $populator->add(Order::class, self::ORDERS_COUNT, [
            'customer_id' => function () use ($faker) {
                return $this->customerId($faker);
            },
            'number' => function () use ($faker) {
                return 'IBT' . $faker->dateTimeThisYear()->format('Ymdhis');
            },
            'cost' => function () use ($faker) {
                return $faker->numberBetween(1, 1000);
            },
            'status' => function () use ($faker) {
                return $faker->randomElement(OrderStatus::validValues());
            },
            'reserve_status' => function () use ($faker) {
                return $faker->randomElement(ReserveStatus::validValues());
            },
            'delivery_type' => function () use ($faker)  {
                return $faker->randomElement(DeliveryType::validValues());
            },
            'delivery_method' => function () use ($faker) {
                return $faker->randomElement(DeliveryMethod::validValues());
            },
            'processing_time' => function () use ($faker) {
                return $faker->dateTimeThisYear();
            },
            'delivery_time' => function () use ($faker) {
                return $faker->dateTimeThisYear()->modify('+3 days');
            },
            'comment' => function () use ($faker) {
                return $faker->realText();
            },
        ])->add(Basket::class, self::BASKETS_COUNT, [
            'customer_id' => function () use ($faker) {
                return $this->customerId($faker);
            },
        ]);
    
        $populator->execute();
    
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
