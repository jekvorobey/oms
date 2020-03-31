<?php

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\DeliveryType;
use App\Models\Order\Order;
use App\Models\Order\OrderComment;
use App\Models\Order\OrderStatus;
use App\Services\OrderService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
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

        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);

        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);
        $restQuery = $customerService->newQuery();
        $restQuery->addFields(CustomerDto::entity(), 'id', 'user_id');
        $customers = $customerService->customers($restQuery)->keyBy('id');

        /** @var Collection|Basket[] $baskets */
        $baskets = collect();
        for ($i = 0; $i < self::ORDERS_COUNT; $i++) {
            $basket = new Basket();
            $basket->type = Basket::TYPE_PRODUCT;
            $basket->customer_id = $faker->randomElement($customers->pluck('id')->all());
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
        /** @var Collection|OfferDto[] $chunkedOffers */
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

        $basketsCost = [];
        $basketsPrice = [];
        foreach ($baskets as $basket) {
            $basketsCost[$basket->id] = 0;
            $basketsPrice[$basket->id] = 0;
            /** @var Collection|OfferDto[] $basketOffers */
            $basketOffers = $offers->random($faker->randomFloat(0, 3, 5));

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
                $basketItem->cost = $faker->numberBetween(100, 1000);
                $basketItem->price = $faker->numberBetween(0, intval($basketItem->cost / 2));
                $basketItem->product = [
                    'store_id' => $offerStock->store_id,
                    'weight' => $product->weight,
                    'width' => $product->width,
                    'height' => $product->height,
                    'length' => $product->length,
                ];
                $basketItem->save();

                $basketsCost[$basket->id] += $basketItem->cost;
                $basketsPrice[$basket->id] += $basketItem->price;
            }
        }

        foreach ($baskets as $basket) {
            $order = new Order();
            $order->basket_id = $basket->id;
            $order->customer_id = $basket->customer_id;
            $order->number = Order::makeNumber();
            $order->status = $faker->randomElement(OrderStatus::validValues());
            $order->created_at = $faker->dateTimeThisYear();
            $order->manager_comment = $faker->realText();

            $order->delivery_type = $faker->randomElement(DeliveryType::validValues());
            $order->delivery_cost = $faker->numberBetween(0, 500);
            $order->delivery_price = $faker->numberBetween(0, intval($order->delivery_cost / 2));
            $order->cost = $basketsCost[$basket->id] + $order->delivery_cost;
            $order->price = $basketsPrice[$basket->id] + $order->delivery_price;

            $order->is_require_check = $faker->boolean();

            $order->save();
            $basket->is_belongs_to_order = true;
            $basket->save();

            if ($faker->boolean()) {
                $orderService->cancel($order->id);
            }

            if ($faker->boolean()) {
                $comment = new OrderComment();
                $comment->order_id = $order->id;
                $comment->text = $faker->text(100);

                $comment->save();
            }
        }
    }
}
