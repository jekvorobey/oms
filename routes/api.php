<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::namespace('V1')->prefix('v1')->group(function () {
    Route::prefix('payments')->group(function () {
        Route::prefix('handler')->group(function () {
            Route::post('local', 'PaymentsController@handlerLocal')->name('handler.localPayment');
        });
        Route::prefix('{id}')->group(function () {
            Route::post('start', 'PaymentsController@start');
        });
    });
    Route::prefix('shipments/{shipmentId}')->namespace('Shipments')->group(function () {
        Route::prefix('packages')->group(function () {
            Route::post('', 'PackageController@create');
        });
        Route::put('','ShipmentsController@editPackage');
        Route::delete('','ShipmentsController@deletePackage');
    });
    
    Route::prefix('shipment-packages/{packageId}')->namespace('Shipments')->group(function () {
        Route::put('wrapper', 'PackageController@updateWrapper');
        Route::put('offers/{offerId}', 'PackageController@setItem');
    });
    
    Route::prefix('orders')->group(function () {
        Route::prefix('history')->group(function () {
            Route::get('count', 'OrdersHistoryController@count');
            Route::get('', 'OrdersHistoryController@list');
        });
    
        Route::prefix('exports')->group(function () {
            Route::get('count', 'OrdersExportController@count');
            Route::get('', 'OrdersExportController@read');
        });

        Route::get('count', 'OrdersController@count');
        Route::prefix('{id}')->group(function () {
            Route::put('payments', 'OrdersController@setPayments');
            Route::put('items/{offerId}', 'BasketController@setItemByOrder');
            Route::prefix('exports')->group(function () {
                Route::get('count', 'OrdersExportController@count');
                Route::get('', 'OrdersExportController@read');
                Route::post('', 'OrdersExportController@create');
                Route::prefix('{exportId}')->group(function () {
                    Route::get('', 'OrdersExportController@read');
                    Route::put('', 'OrdersExportController@update');
                    Route::delete('', 'OrdersExportController@delete');
                });
            });
            Route::prefix('shipments')->namespace('Shipments')->group(function () {
                Route::get('', 'ShipmentsController@list');
                Route::post('', 'ShipmentsController@addPackages');
            });
            Route::put('', 'OrdersController@update');
            Route::delete('', 'OrdersController@delete');
        });

        Route::get('', 'OrdersController@read');
        Route::post('', 'OrdersController@create');
    });

    Route::prefix('baskets')->group(function () {
        Route::get('by-user/{userId}', 'BasketController@getCurrentBasket');
        Route::prefix('{basketId}')->group(function () {
            Route::put('items/{offerId}', 'BasketController@setItemByBasket');
            Route::get('', 'BasketController@getBasket');
            Route::delete('', 'BasketController@dropBasket');
        });
    });

    Route::prefix('delivery')->group(function () {
        Route::get('', 'DeliveryController@info');
        Route::get('pvz', 'DeliveryController@infoPvz');
    });

});
