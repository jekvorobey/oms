<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'LocalPaymentController@index')->name('paymentPage');
