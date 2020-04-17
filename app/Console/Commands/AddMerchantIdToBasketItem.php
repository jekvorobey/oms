<?php

namespace App\Console\Commands;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

/**
 * Разовая команда!
 * Class AddMerchantIdToBasketItem
 * @package App\Console\Commands
 */
class AddMerchantIdToBasketItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'basket_item:add_merchant_id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавить merchant_id для элементов корзины, являющихся товарами';

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

        $offerIds = $basketItems->pluck('offer_id')->unique()->values();
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        /** @var Collection|OfferDto[] $offers */
        $offers = collect();
        foreach ($offerIds->chunk(50) as $chunkedOffersIds) {
            $restQuery = $offerService->newQuery();
            $restQuery->addFields(OfferDto::entity(), 'id', 'merchant_id')
                ->setFilter('offer_id', $chunkedOffersIds);
            $offers = $offers->merge($offerService->offers($restQuery)->keyBy('id'));
        }

        foreach ($basketItems as $basketItem) {
            $product = $basketItem->product;
            if ((!isset($product['merchant_id']) || !$product['merchant_id']) && $offers->has($basketItem->offer_id)) {
                $product['merchant_id'] = $offers[$basketItem->offer_id]->merchant_id;
            }
            $basketItem->product = $product;
            $basketItem->save();
        }
    }
}
