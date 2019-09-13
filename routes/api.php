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
    Route::prefix('orders')->group(function () {
        Route::prefix('history')->group(function () {
            Route::get('', 'OrdersHistoryController@read');
            Route::post('', 'OrdersHistoryController@create');
        });

        Route::get('count', 'OrdersController@count');
        Route::prefix('{id}')->group(function () {
            Route::put('payments', 'OrdersController@setPayments');
            //Route::get('', 'OrdersController@read');
            //Route::put('', 'OrdersController@update');
            //Route::delete('', 'OrdersController@delete');
        });

        Route::get('', 'OrdersController@read');
        //Route::post('', 'OrdersController@create');
    });

    Route::prefix('baskets')->group(function () {
        Route::get('', 'BasketController@read');
        Route::post('', 'BasketController@create');

        Route::prefix('{id}')->group(function () {
            Route::get('items', 'BasketController@items');
            Route::delete('', 'BasketController@delete');
            Route::put('additem', 'BasketController@additem');

        });

    });

    Route::prefix('delivery')->group(function () {
        Route::get('', 'DeliveryController@info');
        Route::get('pvz', 'DeliveryController@infoPvz');
    });

});
