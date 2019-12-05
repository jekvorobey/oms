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
    
        /** @var Collection|Basket[] $baskets */
        $baskets = collect();
        for ($i = 0; $i < self::ORDERS_COUNT; $i++) {
            $basket = new Basket();
            $basket->type = Basket::TYPE_PRODUCT;
            $basket->customer_id = $this->customerId($faker);
            if ($basket->save()) {
                $baskets->push($basket);
            }
        }

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $restQuery = $offerService->newQuery();
        $restQuery->addFields(OfferDto::entity(), 'id', 'product_id');
        $offers = $offerService->offers($restQuery);
    
        /** @var StockService $stockService */
        $stockService = resolve(StockService::class);
        $stocks = collect();
        foreach ($offers->chunk(50) as $chunkedOffers) {
            $restQuery = $stockService->newQuery();
            $restQuery->addFields(StockDto::entity(), 'store_id', 'offer_id')
                ->setFilter('offer_id', $chunkedOffers->pluck('id')->toArray());
            /** @var Collection|StockDto[] $stocks */
            $chunkedStocks = $stockService->stocks($restQuery)->groupBy('offer_id');
            
            //Мержим коллекции $stocks и $chunkedStocks, метод $stocks->merge() не работает для многомерных коллекций
            foreach ($chunkedStocks as $key => $stock) {
                $stocks->put($key, $stock);
            }
        }

        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        $restQuery = $productService->newQuery();
        $restQuery->addFields(ProductDto::entity(), 'id', 'name', 'weight', 'width', 'height', 'length')
            ->setFilter('id', $offers->pluck('product_id'));
        /** @var Collection|ProductDto[] $products */
        $products = $productService->products($restQuery)->keyBy('id');

        foreach ($baskets as $basket) {
            /** @var Collection|OfferDto[] $basketOffers */
            $basketOffers = $offers->random(rand(3, 5));

            foreach ($basketOffers as $basketOffer) {
                if (!$stocks->has($basketOffer->id)) {
                    continue;
                }
                /** @var Collection|StockDto[] $offerStocks */
                $offerStocks = $stocks[$basketOffer->id];
                /** @var StockDto $offerStock */
                $offerStock = $offerStocks->random();
                $product = $products[$basketOffer->product_id];
                
                $basketItem = new BasketItem();
                $basketItem->type = Basket::TYPE_PRODUCT;
                $basketItem->basket_id = $basket->id;
                $basketItem->offer_id = $basketOffer->id;
                $basketItem->name = $product->name;
                $basketItem->qty = $faker->randomDigitNotNull;
                $basketItem->price = $faker->randomFloat(2, 100, 1000);
                $basketItem->discount = $faker->randomFloat(2, 0, $basketItem->price/3);
                $basketItem->product = [
                    'store_id' => $offerStock->store_id,
                    'weight' => $product->weight,
                    'width' => $product->width,
                    'height' => $product->height,
                    'length' => $product->length,
                ];
                $basketItem->save();
            }
        }

        foreach ($baskets as $basket) {
            $order = new Order();
            $order->basket_id = $basket->id;
            $order->customer_id = $this->customerId($faker);
            $order->number = 'IBT' . $faker->dateTimeThisYear()->format('Ymdhis');
            $order->status = $faker->randomElement(OrderStatus::validValues());
            $order->created_at = $faker->dateTimeThisYear();
            $order->manager_comment = $faker->realText();

            $order->delivery_type = $faker->randomElement(DeliveryType::validValues());
            $order->delivery_method = $faker->randomElement(DeliveryMethod::validValues());
            $region = $faker->randomElement([
                'Москва г',
                'Московская обл',
                'Тверская обл',
                'Калужская обл',
                'Рязанская обл',
            ]);
            $order->delivery_address = [
                'country_code' => 'RU',
                'post_index' => $faker->postcode,
                'region' => $region,
                'region_guid' => $faker->uuid,
                'area' => '',
                'area_guid' => '',
                'city' => 'г. ' . $faker->city,
                'city_guid' => $faker->uuid,
                'street' => 'ул. ' . explode(' ', $faker->streetName)[0],
                'house' => 'д. ' . $faker->buildingNumber,
                'block' => '',
                'flat' => '',
            ];
            $order->delivery_cost = $faker->randomFloat(2, 0, 500);

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
