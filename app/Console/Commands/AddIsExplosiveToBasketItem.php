<?php

namespace App\Console\Commands;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Pim\Dto\Product\ProductDto;
use Pim\Services\ProductService\ProductService;

/**
 * Разовая команда! Удалить после использования на всех основных площадках (dev и master)
 * Class AddIsExplosiveToBasketItem
 * @package App\Console\Commands
 */
class AddIsExplosiveToBasketItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'basket_item:add_is_explosive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавить is_explosive для элементов корзины, являющихся товарами';

    /**
     * Execute the console command.
     * @throws \Pim\Core\PimException
     */
    public function handle()
    {
        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = BasketItem::query()
            ->where('type', Basket::TYPE_PRODUCT)
            ->get();

        $offerIds = $basketItems->pluck('offer_id')->unique()->all();
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        $productQuery = $productService
            ->newQuery()
            ->addFields(
                ProductDto::entity(),
                'id',
                'explosive'
            );
        $productsByOffers = $productService->productsByOffers($productQuery, $offerIds);

        foreach ($basketItems as $basketItem) {
            $product = $basketItem->product;
            if (!isset($product['is_explosive']) && $productsByOffers->has($basketItem->offer_id)) {
                $product['is_explosive'] = $productsByOffers[$basketItem->offer_id]->product->explosive;
            }
            $basketItem->product = $product;
            $basketItem->save();
        }
    }
}
