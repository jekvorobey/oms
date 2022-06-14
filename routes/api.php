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
    Route::prefix('checkout')->group(function () {
        Route::post('commit', 'CheckoutController@commit');
    });

    Route::prefix('baskets')->group(function () {
        Route::get('', 'Basket\ListBasketController@list');
        Route::get('count', 'Basket\ListBasketController@count');

        Route::post('notify-expired/{offer}', 'Basket\CustomerBasketController@notifyExpiredOffers');
        Route::get('by-customer/{customerId}', 'Basket\CustomerBasketController@getCurrentBasket');
        Route::get('qty-by-offer-ids', 'Basket\CustomerBasketController@qtyByOfferIds');

        Route::prefix('{basketId}')->group(function () {
            Route::put('items/{offerId}', 'Basket\CustomerBasketController@setItemByBasket');
            Route::put('commit', 'Basket\CustomerBasketController@commitItemsPrice');
            Route::get('', 'Basket\CustomerBasketController@getBasket');
            Route::delete('', 'Basket\CustomerBasketController@dropBasket');
            Route::put('', 'Basket\CustomerBasketController@update');
        });

        Route::prefix('guest')->group(function () {
            Route::get('by-customer/{customerId}', 'Basket\GuestBasketController@getCurrentBasket');
            Route::post('replace-to-customer/{guestId}', 'Basket\GuestBasketController@moveGuestBasketToCustomer');

            Route::prefix('{basketId}')->group(function () {
                Route::put('items/{offerId}', 'Basket\GuestBasketController@setItemByBasket');
                Route::get('', 'Basket\GuestBasketController@getBasket');
                Route::delete('', 'Basket\GuestBasketController@dropBasket');
            });
        });
    });

    Route::prefix('orders')->group(function () {
        Route::post('by-offers', 'OrdersController@getByOffers');

        Route::prefix('promo-codes')->group(function () {
            Route::get('{promoCodeId}/count', 'OrdersPromoCodesController@count');
        });

        Route::prefix('discounts')->group((function () {
            Route::get('{discountId}/kpi', 'OrderDiscountController@KPIForDiscount');
        }));

        Route::prefix('exports')->group(function () {
            Route::get('count', 'OrdersExportController@count');
            Route::get('', 'OrdersExportController@read');
        });
        Route::prefix('done')->group(function () {
            Route::get('referral', 'OrdersController@doneReferral');
            Route::get('merchant', 'OrdersController@doneMerchant');
        });

        Route::get('count', 'OrdersController@count');
        Route::prefix('{id}')->group(function () {
            Route::prefix('history')->group(function () {
                Route::get('count', 'HistoryController@countByOrder');
                Route::get('', 'HistoryController@readByOrder');
            });
            Route::put('payments', 'OrdersController@setPayments');
            Route::put('comment', 'OrdersController@setComment');

            Route::put('items/{offerId}', 'Basket\CustomerBasketController@setItemByOrder');

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

            Route::get('', 'OrdersController@readOne');
            Route::put('', 'OrdersController@update');
            Route::put('cancel', 'OrdersController@cancel');
            Route::put('return', 'OrdersController@returnCompleted');
            Route::put('refund-by-certificate', 'OrdersController@refundByCertificate');
            Route::put('pay', 'OrdersController@pay');
            Route::put('capture-payment', 'OrdersController@capturePayment');
            Route::delete('', 'OrdersController@delete');
            Route::get('tickets', 'OrdersController@tickets');
        });

        Route::prefix('return-reasons')->group(function () {
            Route::get('count', 'OrderReturnReasonController@count');
            Route::prefix('{id}')->group(function () {
                Route::get('', 'OrderReturnReasonController@read');
                Route::put('', 'OrderReturnReasonController@update');
                Route::delete('', 'OrderReturnReasonController@delete');
            });
            Route::get('', 'OrderReturnReasonController@read');
            Route::post('', 'OrderReturnReasonController@create');
        });

        Route::get('', 'OrdersController@read');
    });

    Route::prefix('payments')->group(function () {
        Route::prefix('methods')->group(function () {
            Route::get('', 'PaymentMethodsController@read');
            Route::prefix('{paymentMethod}')->group(function () {
                Route::get('', 'PaymentMethodsController@read');
                Route::put('', 'PaymentMethodsController@update');
            });
        });
        Route::prefix('handler')->group(function () {
            Route::post('local', 'PaymentsController@handlerLocal')->name('handler.localPayment');
            Route::post('yandex', 'PaymentsController@handlerYandex')->name('handler.yandexPayment');
        });

        Route::prefix('{id}')->group(function () {
            Route::post('start', 'PaymentsController@start');
        });
        Route::get('byOrder', 'PaymentsController@getByOrder');
        Route::get('', 'PaymentsController@payments');
    });

    Route::prefix('shipments')->group(function () {
        Route::prefix('{id}')->group(function () {
            Route::prefix('history')->group(function () {
                Route::get('count', 'HistoryController@countByShipment');
                Route::get('', 'HistoryController@readByShipment');
            });
        });
    });

    Route::prefix('cargos')->group(function () {
        Route::prefix('{id}')->group(function () {
            Route::prefix('history')->group(function () {
                Route::get('count', 'HistoryController@countByCargo');
                Route::get('', 'HistoryController@readByCargo');
            });
        });
    });

    Route::prefix('document-templates')->group(function () {
        Route::get('claim-act', 'DocumentTemplatesController@claimAct');
        Route::get('acceptance-act', 'DocumentTemplatesController@acceptanceAct');
        Route::get('inventory', 'DocumentTemplatesController@inventory');
        Route::get('assembling-card', 'DocumentTemplatesController@assemblingCard');
    });

    Route::prefix('merchant-analytics')->group(function () {
        Route::get('products-shipments', 'AnalyticsController@productsShipments')->name('analytics.productsShipments');
        Route::get('sales', 'AnalyticsController@sales')->name('analytics.sales');
        Route::prefix('top')->group(function () {
            Route::get('bestsellers', 'AnalyticsController@bestsellers')->name('analytics.bestsellers');
            Route::get('fastest', 'AnalyticsController@fastest')->name('analytics.fastest');
            Route::get('outsiders', 'AnalyticsController@outsiders')->name('analytics.outsiders');
        });
    });

    Route::namespace('Delivery')->group(function () {
        Route::prefix('deliveries')->group(function () {
            Route::get('count', 'DeliveryController@count');
            Route::get('count-today-by-delivery-services', 'DeliveryController@countTodayByDeliveryServices');
            Route::get('', 'DeliveryController@read');
            Route::put('update-delivery-status', 'DeliveryController@updateDeliveryStatusByXmlId');

            Route::prefix('{id}')->group(function () {
                Route::get('', 'DeliveryController@read');

                Route::prefix('shipments')->group(function () {
                    Route::get('count', 'ShipmentsController@countByDelivery');
                    Route::get('', 'ShipmentsController@readByDelivery');
                    Route::post('', 'ShipmentsController@create');
                });

                Route::prefix('delivery-order')->group(function () {
                    Route::put('', 'DeliveryController@saveDeliveryOrder');
                    Route::put('cancel', 'DeliveryController@cancelDeliveryOrder');
                });

                Route::put('', 'DeliveryController@update');
                Route::put('cancel', 'DeliveryController@cancel');
                Route::delete('', 'DeliveryController@delete');
            });
        });

        Route::prefix('shipments')->group(function () {
            Route::get('count', 'ShipmentsController@count');
            Route::get('active', 'ShipmentsController@getActiveIds');
            Route::get('delivered', 'ShipmentsController@getDeliveredIds');
            Route::get('', 'ShipmentsController@read');
            Route::get('similar-unshipped-shipments', 'ShipmentsController@similarUnshippedShipments');

            Route::prefix('exports')->group(function () {
                Route::get('new', 'ShipmentsController@readNew');
                Route::post('', 'ShipmentsController@createShipmentExport');
            });

            Route::prefix('{id}')->group(function () {
                Route::get('', 'ShipmentsController@read');

                Route::prefix('items')->group(function () {
                    Route::get('count', 'ShipmentsController@countItems');
                    Route::get('', 'ShipmentsController@readItems');

                    Route::prefix('{basketItemId}')->group(function () {
                        Route::get('', 'ShipmentsController@readItem');
                        Route::post('', 'ShipmentsController@createItem');
                        Route::delete('', 'ShipmentsController@deleteItem');
                        Route::put('', 'ShipmentsController@cancelItem');
                    });
                });

                Route::prefix('shipment-packages')->group(function () {
                    Route::get('count', 'ShipmentPackagesController@countByShipment');
                    Route::get('', 'ShipmentPackagesController@readByShipment');
                    Route::post('', 'ShipmentPackagesController@create');
                });

                Route::prefix('documents')->group(function () {
                    Route::get('acceptance-act', 'ShipmentDocumentsController@acceptanceAct');
                    Route::get('inventory', 'ShipmentDocumentsController@inventory');
                    Route::get('assembling-card', 'ShipmentDocumentsController@assemblingCard');
                });

                Route::put('', 'ShipmentsController@update');
                Route::put('mark-as-problem', 'ShipmentsController@markAsProblem');
                Route::put('mark-as-non-problem', 'ShipmentsController@markAsNonProblem');
                Route::put('cancel', 'ShipmentsController@cancel');
                Route::delete('', 'ShipmentsController@delete');
                Route::get('barcodes', 'ShipmentsController@barcodes');
                Route::get('cdek-receipt', 'ShipmentsController@cdekReceipt');
            });
        });

        Route::prefix('shipment-packages')->group(function () {
            Route::get('', 'ShipmentPackagesController@read');

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
                Route::put('', 'CargoController@update');
                Route::put('cancel', 'CargoController@cancel');
                Route::delete('', 'CargoController@delete');

                Route::prefix('courier-call')->group(function () {
                    Route::get('check', 'CargoController@checkExternalStatus');
                    Route::post('', 'CargoController@createCourierCall');
                    Route::put('cancel', 'CargoController@cancelCourierCall');
                });

                Route::prefix('documents')->group(function () {
                    Route::get('acceptance-act', 'CargoDocumentsController@acceptanceAct');
                });
            });
        });
    });
});
