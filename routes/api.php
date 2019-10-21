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
    Route::prefix('baskets')->group(function () {
        Route::get('by-user/{userId}', 'BasketController@getCurrentBasket');
        
        Route::prefix('{basketId}')->group(function () {
            Route::put('items/{offerId}', 'BasketController@setItemByBasket');
            
            Route::get('', 'BasketController@getBasket');
            Route::delete('', 'BasketController@dropBasket');
        });
    });
    
    Route::prefix('orders')->group(function () {
        Route::prefix('history')->group(function () {
            Route::get('count', 'OrdersHistoryController@count');
            Route::get('', 'OrdersHistoryController@read');
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
            
            Route::prefix('delivery')->namespace('Delivery')->group(function () {
                Route::get('count', 'DeliveryController@count');
                Route::get('', 'DeliveryController@readByOrder');
                Route::post('', 'DeliveryController@create');
            });
            
            Route::put('', 'OrdersController@update');
            Route::delete('', 'OrdersController@delete');
        });

        Route::get('', 'OrdersController@read');
        Route::post('', 'OrdersController@create');
    });
    
    Route::prefix('payments')->group(function () {
        Route::prefix('handler')->group(function () {
            Route::post('local', 'PaymentsController@handlerLocal')->name('handler.localPayment');
        });
        
        Route::prefix('{id}')->group(function () {
            Route::post('start', 'PaymentsController@start');
        });
    });
    
    Route::namespace('Delivery')->group(function () {
        Route::prefix('delivery')->group(function () {
            Route::get('count', 'DeliveryController@count');
            Route::get('', 'DeliveryController@read');
            
            Route::get('', 'DeliveryController@info'); //todo Временный
            Route::get('pvz', 'DeliveryController@infoPvz');  //todo Временный
        
            Route::prefix('{id}')->group(function () {
                Route::get('', 'DeliveryController@read');
                
                Route::prefix('shipments')->group(function () {
                    Route::get('', 'ShipmentsController@count');
                    Route::get('', 'ShipmentsController@readByDelivery');
                    Route::post('', 'ShipmentsController@create');
                });
                
                Route::put('','DeliveryController@update');
                Route::delete('','DeliveryController@delete');
            });
        });
        
        Route::prefix('shipments')->group(function () {
            Route::get('count', 'ShipmentsController@count');
            Route::get('', 'ShipmentsController@read');
    
            Route::prefix('{id}')->group(function () {
                Route::get('', 'ShipmentsController@read');
    
                Route::put('items/{basketItemId}', 'ShipmentsController@setItem');
                
                Route::prefix('shipment-packages')->group(function () {
                    Route::get('', 'ShipmentPackageController@count');
                    Route::get('', 'ShipmentPackageController@readByShipment');
                    Route::post('', 'ShipmentPackageController@create');
                });
    
                Route::put('','ShipmentsController@update');
                Route::delete('','ShipmentsController@delete');
            });
        });
        
        Route::prefix('shipment-packages/{$id}')->group(function () {
            Route::put('wrapper', 'ShipmentPackageController@updateWrapper');
            Route::put('items/{basketItemId}', 'ShipmentPackageController@setItem');
            Route::delete('', 'ShipmentPackageController@delete');
        });
        
        Route::prefix('cargo')->group(function () {
            Route::get('count', 'CargoController@count');
            Route::post('', 'CargoController@create');
    
            Route::prefix('{id}')->group(function () {
                Route::get('', 'CargoController@read');
                Route::put('','CargoController@update');
                Route::delete('','CargoController@delete');
            });
        });
    });
});
