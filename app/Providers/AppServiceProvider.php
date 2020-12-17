<?php

namespace App\Providers;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\Order\Order;
use App\Models\Order\OrderComment;
use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use App\Observers\Basket\BasketItemObserver;
use App\Observers\Basket\BasketObserver;
use App\Observers\Delivery\CargoObserver;
use App\Observers\Delivery\DeliveryObserver;
use App\Observers\Delivery\ShipmentItemObserver;
use App\Observers\Delivery\ShipmentObserver;
use App\Observers\Delivery\ShipmentPackageItemObserver;
use App\Observers\Delivery\ShipmentPackageObserver;
use App\Observers\Order\OrderCommentObserver;
use App\Observers\Order\OrderObserver;
use App\Observers\Order\OrderReturnObserver;
use App\Observers\Order\CertificateObserver;
use App\Observers\Payment\PaymentObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use L5Swagger\L5SwaggerServiceProvider;
use YandexCheckout\Client;

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
        $this->app->singleton(Client::class, function ($app) {
            $client = new Client();
            $client->setAuth(config('app.y_checkout_shop_id'), config('app.y_checkout_key'));
            return $client;
        });

        $this->addObservers();
        $this->addMorphForHistory();

        $this->loadViewsFrom(base_path('resources/views.pdf'), 'pdf');
    }

    protected function addObservers(): void
    {
        Basket::observe(BasketObserver::class);
        BasketItem::observe(BasketItemObserver::class);

        Order::observe(OrderObserver::class);
        Order::observe(CertificateObserver::class);
        OrderComment::observe(OrderCommentObserver::class);

        OrderReturn::observe(OrderReturnObserver::class);

        Delivery::observe(DeliveryObserver::class);
        Shipment::observe(ShipmentObserver::class);
        ShipmentItem::observe(ShipmentItemObserver::class);
        ShipmentPackage::observe(ShipmentPackageObserver::class);
        ShipmentPackageItem::observe(ShipmentPackageItemObserver::class);
        Cargo::observe(CargoObserver::class);

        Payment::observe(PaymentObserver::class);
    }

    protected function addMorphForHistory(): void
    {
        $entitiesWithHistory = [
            BasketItem::class,
            Cargo::class,
            Delivery::class,
            Order::class,
            OrderComment::class,
            OrderReturn::class,
            Payment::class,
            Shipment::class,
            ShipmentItem::class,
            ShipmentPackage::class,
            ShipmentPackageItem::class,
        ];
        $morphMap = [];
        foreach ($entitiesWithHistory as $entity) {
            $entityClass = explode('\\', $entity);
            $morphMap[end($entityClass)] = $entity;
        }
        Relation::morphMap($morphMap);
    }
}
