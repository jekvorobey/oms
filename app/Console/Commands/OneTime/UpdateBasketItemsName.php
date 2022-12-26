<?php

namespace App\Console\Commands\OneTime;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pim\Services\OfferService\OfferService;

/**
 *
 */
class UpdateBasketItemsName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'basket:update_items_names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавить к названиям товаров в корзине значения опций офферов';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = BasketItem::query()
            ->where('created_at', '>=', Carbon::createFromDate(2022, 12, 15))
            ->get();

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);

        foreach ($basketItems as $basketItem) {
            $offerInfo = $offerService->offerInfo($basketItem->offer_id);
            if ($offerInfo->merchant_id === 26 && $basketItem->name != $offerInfo->full_name) {
                $basketItem->name = $offerInfo->full_name;
                $basketItem->save();

                $this->output->text([$offerInfo->offer_id, $offerInfo->full_name]);
            }
        }
    }
}
