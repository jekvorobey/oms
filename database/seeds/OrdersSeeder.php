<?php

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\DeliveryMethod;
use App\Models\Delivery\DeliveryService;
use App\Models\Delivery\DeliveryType;
use App\Models\Order\Order;
use App\Models\Order\OrderComment;
use App\Models\Order\OrderStatus;
use Greensight\Store\Dto\StockDto;
use Greensight\Store\Services\StockService\StockService;
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

        for ($i = 0; $i < self::ORDERS_COUNT; $i++) {
            $basket = new Basket();
            $basket->customer_id = $this->customerId($faker);
            $basket->save();
        }

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $restQuery = $offerService->newQuery();
        $restQuery->addFields(OfferDto::entity(), 'id', 'product_id');
        $offers = $offerService->offers($restQuery);
    
        /** @var StockService $stockService */
        /*$stockService = resolve(StockService::class);
        $stocks = collect();
        foreach ($offers->chunk(20) as $chunkedOffers) {
            $restQuery = $stockService->newQuery();
            $restQuery->addFields(StockDto::entity(), 'store_id', 'offer_id')
                ->setFilter('offer_id', $chunkedOffers->pluck('id')->toArray());
            /** @var Collection|StockDto[] $stocks /
            $chunkedStocks = $stockService->stocks($restQuery)->groupBy('offer_id');
            
            $stocks->merge($chunkedStocks);
        }*/

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
                /*if (!$stocks->has($basketOffer->id)) {
                    continue;
                }
                /** @var Collection|StockDto[] $offerStocks /
                $offerStocks = $stocks[$basketOffer->id];
                /** @var StockDto $offerStock /
                $offerStock = $offerStocks->random();*/
                
                $basketItem = new BasketItem();
                $basketItem->basket_id = $basket->id;
                //$basketItem->store_id = $offerStock->store_id;
                $basketItem->store_id = rand(1, 8);
                $basketItem->offer_id = $basketOffer->id;
                $basketItem->name = $products[$basketOffer->product_id]->name;
                $basketItem->qty = $faker->randomDigitNotNull;
                $basketItem->price = $faker->randomFloat(2, 100, 1000);
                $basketItem->save();
            }
        }

        foreach ($baskets as $basket) {
            $order = new Order();
            $order->basket_id = $basket->id;
            $order->customer_id = $this->customerId($faker);
            $order->number = 'IBT' . $faker->dateTimeThisYear()->format('Ymdhis');
            $order->cost = $faker->numberBetween(1, 1000);
            $order->status = $faker->randomElement(OrderStatus::validValues());
            $order->created_at = $faker->dateTimeThisYear();
            $order->manager_comment = $faker->realText();

            $order->delivery_service = $faker->randomElement(DeliveryService::validValues());
            $order->delivery_type = $faker->randomElement(DeliveryType::validValues());
            $order->delivery_method = $faker->randomElement(DeliveryMethod::validValues());
            $order->delivery_address = [];

            $order->receiver_name = $faker->name;
            $order->receiver_phone = $faker->phoneNumber;
            $order->receiver_email = $faker->email;

            $order->save();
            $basket->is_belongs_to_order = true;
            $basket->save();

            if (rand(0,1)) {
                $comment = new OrderComment();
                $comment->order_id = $order->id;
                $comment->text = $faker->text(100);

                $comment->save();
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
