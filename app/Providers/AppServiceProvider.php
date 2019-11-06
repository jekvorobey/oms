<?php

namespace App\Providers;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\Order\Order;
use App\Models\Order\OrderComment;
use App\Models\Payment\Payment;
use App\Observers\Basket\BasketItemObserver;
use App\Observers\Basket\BasketObserver;
use App\Observers\Delivery\DeliveryObserver;
use App\Observers\Delivery\ShipmentItemObserver;
use App\Observers\Delivery\ShipmentObserver;
use App\Observers\Delivery\ShipmentPackageItemObserver;
use App\Observers\Delivery\ShipmentPackageObserver;
use App\Observers\Order\OrderCommentObserver;
use App\Observers\Order\OrderObserver;
use App\Observers\Payment\PaymentObserver;
use Illuminate\Support\ServiceProvider;
use L5Swagger\L5SwaggerServiceProvider;

/**
 * Class AppServiceProvider
 * @package App\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(L5SwaggerServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Basket::observe(BasketObserver::class);
        BasketItem::observe(BasketItemObserver::class);
    
        Order::observe(OrderObserver::class);
        OrderComment::observe(OrderCommentObserver::class);
    
        Delivery::observe(DeliveryObserver::class);
        Shipment::observe(ShipmentObserver::class);
        ShipmentItem::observe(ShipmentItemObserver::class);
        ShipmentPackage::observe(ShipmentPackageObserver::class);
        ShipmentPackageItem::observe(ShipmentPackageItemObserver::class);
        
        Payment::observe(PaymentObserver::class);
    }
}
