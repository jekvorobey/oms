<?php

namespace App\Console\Commands;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

/**
 * Разовая команда! Удалить после использования на всех основных площадках (dev и master)
 * Class AddSaleAtToBasketItem
 * @package App\Console\Commands
 */
class AddSaleAtToBasketItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'basket_item:add_sale_at';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавить sale_at для элементов корзины, являющихся товарами';

    /**
     * Execute the console command.
     * @throws PimException
     */
    public function handle()
    {
        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = BasketItem::query()
            ->where('type', Basket::TYPE_PRODUCT)
            ->get();

        $offersIds = $basketItems->pluck('offer_id')->unique()->all();
        /** @var Collection|OfferDto[] $offers */
        $offers = collect();
        foreach (array_chunk($offersIds, 50) as $chunkedOffersIds) {
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $offersQuery = $offerService->newQuery()
                ->setFilter('id', $chunkedOffersIds)
                ->addFields(OfferDto::entity(), 'id', 'sale_at');
            $offers = $offers->merge($offerService->offers($offersQuery));
        }
        $offers = $offers->keyBy('id');

        foreach ($basketItems as $basketItem) {
            $product = $basketItem->product;
            if (!isset($product['sale_at']) && $offers->has($basketItem->offer_id)) {
                $product['sale_at'] = $offers[$basketItem->offer_id]->sale_at;
            }
            $basketItem->product = $product;
            $basketItem->save();
        }
    }
}
