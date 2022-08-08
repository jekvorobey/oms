<?php

namespace App\Console\Commands\OneTime;

use App\Models\Delivery\Delivery;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Class AddAddressString2Deliveries
 * @package App\Console\Commands\OneTime
 */
class AddAddressString2Deliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery:add_address_string';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавить поле address_string с полным адресом доставки одной строкой';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var Collection|Delivery[] $deliveries */
        $deliveries = Delivery::query()->where('delivery_method', DeliveryMethod::METHOD_DELIVERY)->get();
        foreach ($deliveries as $delivery) {
            try {
                $deliveryAddress = $delivery->delivery_address;
                $deliveryAddress['address_string'] = $delivery->getDeliveryAddressString();
                $delivery->delivery_address = $deliveryAddress;
                $delivery->save();
            } catch (\Throwable) {
                //
            }
        }
    }
}
