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
            
            Route::prefix('deliveries')->namespace('Delivery')->group(function () {
                Route::get('count', 'DeliveryController@countByOrder');
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
        Route::prefix('deliveries')->group(function () {
            Route::get('count', 'DeliveryController@count');
            Route::get('', 'DeliveryController@read');
        
            Route::prefix('{id}')->group(function () {
                Route::get('', 'DeliveryController@read');
                
                Route::prefix('shipments')->group(function () {
                    Route::get('count', 'ShipmentsController@countByDelivery');
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
    
                Route::prefix('items')->group(function () {
                    Route::get('count', 'ShipmentsController@countItems');
                    Route::get('', 'ShipmentsController@readItems');
                    
                    Route::prefix('{basketItemId}')->group(function () {
                        Route::get('', 'ShipmentsController@readItem');
                        Route::post('', 'ShipmentsController@createItem');
                        Route::delete('', 'ShipmentsController@deleteItem');
                    });
                });
                
                Route::prefix('shipment-packages')->group(function () {
                    Route::get('count', 'ShipmentPackagesController@countByShipment');
                    Route::get('', 'ShipmentPackagesController@readByShipment');
                    Route::post('', 'ShipmentPackagesController@create');
                });
    
                Route::put('','ShipmentsController@update');
                Route::delete('','ShipmentsController@delete');
            });
        });
        
        Route::prefix('shipment-packages')->group(function () {
            Route::prefix('{id}')->group(function () {
                Route::get('', 'ShipmentPackagesController@read');
                
                Route::prefix('items')->group(function () {
                    Route::get('count', 'ShipmentPackagesController@countItems');
                    Route::get('', 'ShipmentPackagesController@readItems');
    
                    Route::prefix('{basketItemId}')->group(function () {
                        Route::get('', 'ShipmentPackagesController@readItem');
                        Route::put('', 'ShipmentPackagesController@setItem');
                    });
                });
                
                Route::put('', 'ShipmentPackagesController@update');
                Route::delete('', 'ShipmentPackagesController@delete');
            });
        });
        
        Route::prefix('cargos')->group(function () {
            Route::get('count', 'CargoController@count');
            Route::get('', 'CargoController@read');
            Route::post('', 'CargoController@create');
    
            Route::prefix('{id}')->group(function () {
                Route::get('', 'CargoController@read');
                Route::put('','CargoController@update');
                Route::delete('','CargoController@delete');
            });
        });
    });
});
