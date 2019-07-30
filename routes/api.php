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
    Route::prefix('orders')->group(function () {
        Route::get('count', 'OrdersController@count');
        Route::prefix('{id}')->group(function () {
            Route::get('', 'OrdersController@read');
            Route::put('', 'OrdersController@update');
            Route::delete('', 'OrdersController@delete');
        });
        
        Route::get('', 'OrdersController@read');
        Route::post('', 'OrdersController@create');
    });
});
