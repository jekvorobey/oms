<?php

use App\Http\Controllers\V1\Analytics\AnalyticsController;
use App\Http\Controllers\V1\Analytics\DashboardsAnalyticsController;
use App\Http\Controllers\V1\Analytics\MerchantAnalyticsController;
use App\Http\Controllers\V1\Basket\CustomerBasketController;
use App\Http\Controllers\V1\Basket\GuestBasketController;
use App\Http\Controllers\V1\CheckoutController;
use App\Http\Controllers\V1\Delivery\CargoController;
use App\Http\Controllers\V1\Delivery\CargoDocumentsController;
use App\Http\Controllers\V1\Delivery\DeliveryController;
use App\Http\Controllers\V1\Delivery\ShipmentDocumentsController;
use App\Http\Controllers\V1\Delivery\ShipmentPackagesController;
use App\Http\Controllers\V1\Delivery\ShipmentsController;
use App\Http\Controllers\V1\DocumentTemplatesController;
use App\Http\Controllers\V1\HistoryController;
use App\Http\Controllers\V1\OrderDiscountController;
use App\Http\Controllers\V1\OrderDocumentsController;
use App\Http\Controllers\V1\OrderReturnReasonController;
use App\Http\Controllers\V1\OrdersController;
use App\Http\Controllers\V1\OrdersExportController;
use App\Http\Controllers\V1\OrdersPromoCodesController;
use App\Http\Controllers\V1\PaymentMethodsController;
use App\Http\Controllers\V1\PaymentsController;
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
        Route::post('commit', [CheckoutController::class, 'commit']);
    });

    Route::prefix('baskets')->group(function () {
        Route::get('', [CustomerBasketController::class, 'list']);
        Route::get('count', [CustomerBasketController::class, 'count']);

        Route::post('notify-expired/{offer}', [CustomerBasketController::class, 'notifyExpiredOffers']);
        Route::get('by-customer/{customerId}', [CustomerBasketController::class, 'getCurrentBasket']);
        Route::get('qty-by-offer-ids', [CustomerBasketController::class, 'qtyByOfferIds']);

        Route::prefix('{basketId}')->group(function () {
            Route::put('items/{offerId}', [CustomerBasketController::class, 'setItemByBasket']);
            Route::put('commit', [CustomerBasketController::class, 'commitItemsPrice']);
            Route::get('', [CustomerBasketController::class, 'getBasket']);
            Route::delete('', [CustomerBasketController::class, 'dropBasket']);
            Route::put('', [CustomerBasketController::class, 'update']);
        });

        Route::prefix('guest')->group(function () {
            Route::get('by-customer/{customerId}', [GuestBasketController::class, 'getCurrentBasket']);
            Route::post('replace-to-customer/{guestId}', [GuestBasketController::class, 'moveGuestBasketToCustomer']);

            Route::prefix('{basketId}')->group(function () {
                Route::put('items/{offerId}', [GuestBasketController::class, 'setItemByBasket']);
                Route::get('', [GuestBasketController::class, 'getBasket']);
                Route::delete('', [GuestBasketController::class, 'dropBasket']);
            });
        });
    });

    Route::prefix('orders')->group(function () {
        Route::post('by-offers', [OrdersController::class, 'getByOffers']);

        Route::prefix('promo-codes')->group(function () {
            Route::get('{promoCodeId}/count', [OrdersPromoCodesController::class, 'count']);
        });

        Route::prefix('discounts')->group((function () {
            Route::get('{discountId}/kpi', [OrderDiscountController::class, 'KPIForDiscount']);
        }));

        Route::prefix('exports')->group(function () {
            Route::get('count', [OrdersExportController::class, 'count']);
            Route::get('', [OrdersExportController::class, 'read']);
        });
        Route::prefix('done')->group(function () {
            Route::get('referral', [OrdersController::class, 'doneReferral']);
            Route::get('merchant', [OrdersController::class, 'doneMerchant']);
        });

        Route::get('count', [OrdersController::class, 'count']);
        Route::prefix('{id}')->group(function () {
            Route::prefix('history')->group(function () {
                Route::get('count', [HistoryController::class, 'countByOrder']);
                Route::get('', [HistoryController::class, 'readByOrder']);
            });

            Route::put('payments', [OrdersController::class, 'setPayments']);
            Route::get('payments/check-credit-status', [OrdersController::class, 'paymentCheckCreditStatus']);
            Route::put('payments/create-credit-payment-receipt', [OrdersController::class, 'paymentCreateCreditPaymentReceipt']);
            Route::put('comment', [OrdersController::class, 'setComment']);

            Route::put('items/{offerId}', [CustomerBasketController::class, 'setItemByOrder']);

            Route::prefix('exports')->group(function () {
                Route::get('count', [OrdersExportController::class, 'count']);
                Route::get('', [OrdersExportController::class, 'read']);
                Route::post('', [OrdersExportController::class, 'create']);
                Route::prefix('{exportId}')->group(function () {
                    Route::get('', [OrdersExportController::class, 'read']);
                    Route::put('', [OrdersExportController::class, 'update']);
                    Route::delete('', [OrdersExportController::class, 'delete']);
                });
            });

            Route::prefix('deliveries')->namespace('Delivery')->group(function () {
                Route::get('count', [DeliveryController::class, 'countByOrder']);
                Route::get('', [DeliveryController::class, 'readByOrder']);
                Route::post('', [DeliveryController::class, 'create']);
            });

            Route::get('', [OrdersController::class, 'readOne']);
            Route::put('', [OrdersController::class, 'update']);
            Route::put('cancel', [OrdersController::class, 'cancel']);
            Route::put('return', [OrdersController::class, 'returnCompleted']);
            Route::put('refund-by-certificate', [OrdersController::class, 'refundByCertificate']);
            Route::put('pay', [OrdersController::class, 'pay']);
            Route::put('capture-payment', [OrdersController::class, 'capturePayment']);
            Route::delete('', [OrdersController::class, 'delete']);
            Route::get('tickets', [OrdersController::class, 'tickets']);
            Route::prefix('documents')->group(function () {
                Route::get('generate-invoice-offer', [OrderDocumentsController::class, 'generateInvoiceOffer']);
                Route::get('generate-upd', [OrderDocumentsController::class, 'generateUPD']);
                Route::get('upd', [OrderDocumentsController::class, 'upd']);
                Route::get('invoice-offer', [OrderDocumentsController::class, 'invoiceOffer']);
            });
        });

        Route::prefix('return-reasons')->group(function () {
            Route::get('count', [OrderReturnReasonController::class, 'count']);
            Route::prefix('{id}')->group(function () {
                Route::get('', [OrderReturnReasonController::class, 'read']);
                Route::put('', [OrderReturnReasonController::class, 'update']);
                Route::delete('', [OrderReturnReasonController::class, 'delete']);
            });
            Route::get('', [OrderReturnReasonController::class, 'read']);
            Route::post('', [OrderReturnReasonController::class, 'create']);
        });

        Route::get('', [OrdersController::class, 'read']);
    });

    Route::prefix('payments')->group(function () {
        Route::prefix('methods')->group(function () {
            Route::get('', [PaymentMethodsController::class, 'read']);
            Route::prefix('{paymentMethod}')->group(function () {
                Route::get('', [PaymentMethodsController::class, 'readOne']);
                Route::put('', [PaymentMethodsController::class, 'update']);
            });
        });
        Route::prefix('handler')->group(function () {
            Route::post('local', [PaymentsController::class, 'handlerLocal'])->name('handler.localPayment');
            Route::post('yandex', [PaymentsController::class, 'handlerYandex'])->name('handler.yandexPayment');
        });

        Route::prefix('{id}')->group(function () {
            Route::post('start', [PaymentsController::class, 'start']);
        });
        Route::get('byOrder', [PaymentsController::class, 'getByOrder']);
        Route::get('', [PaymentsController::class, 'payments']);
    });

    Route::prefix('shipments')->group(function () {
        Route::prefix('{id}')->group(function () {
            Route::prefix('history')->group(function () {
                Route::get('count', [HistoryController::class, 'countByShipment']);
                Route::get('', [HistoryController::class, 'readByShipment']);
            });
        });
        Route::prefix('documents')->group(function () {
            Route::post('receipt-invoice', 'Delivery\ShipmentDocumentsController@receiptInvoice');
        });
    });

    Route::prefix('cargos')->group(function () {
        Route::prefix('{id}')->group(function () {
            Route::prefix('history')->group(function () {
                Route::get('count', [HistoryController::class, 'countByCargo']);
                Route::get('', [HistoryController::class, 'readByCargo']);
            });
        });
    });

    Route::prefix('document-templates')->group(function () {
        Route::get('claim-act', [DocumentTemplatesController::class, 'claimAct']);
        Route::get('acceptance-act', [DocumentTemplatesController::class, 'acceptanceAct']);
        Route::get('inventory', [DocumentTemplatesController::class, 'inventory']);
        Route::get('assembling-card', [DocumentTemplatesController::class, 'assemblingCard']);
    });

    Route::prefix('merchant-analytics')->group(function () {
        Route::get('products-shipments', [MerchantAnalyticsController::class, 'productsShipments'])->name('analytics.productsShipments');
        Route::get('sales', [MerchantAnalyticsController::class, 'sales'])->name('analytics.sales');
        Route::prefix('top')->group(function () {
            Route::get('bestsellers', [MerchantAnalyticsController::class, 'bestsellers'])->name('analytics.bestsellers');
            Route::get('fastest', [MerchantAnalyticsController::class, 'fastest'])->name('analytics.fastest');
            Route::get('outsiders', [MerchantAnalyticsController::class, 'outsiders'])->name('analytics.outsiders');
        });
    });

    Route::prefix('analytics')->group(function () {
        Route::get('dump-orders', [AnalyticsController::class, 'dumpOrders'])->name('analytics.dumpOrders');
        Route::prefix('dashboard')->group(function () {
            Route::prefix('sales')->group(function () {
                Route::get('day-by-hour', [DashboardsAnalyticsController::class, 'salesDayByHour'])->name('dashboardsAnalytics.salesDayByHour');
                Route::get('month-by-day', [DashboardsAnalyticsController::class, 'salesMonthByDay'])->name('dashboardsAnalytics.salesMonthByDay');
                Route::get('year-by-month', [DashboardsAnalyticsController::class, 'salesYearByMonth'])->name('dashboardsAnalytics.salesYearByMonth');
                Route::get('all-period-by-day', [DashboardsAnalyticsController::class, 'salesAllPeriodByDay'])->name('dashboardsAnalytics.salesAllPeriodByDay');
            });
        });
    });

    Route::namespace('Delivery')->group(function () {
        Route::prefix('deliveries')->group(function () {
            Route::get('count', [DeliveryController::class, 'count']);
            Route::get('count-today-by-delivery-services', [DeliveryController::class, 'countTodayByDeliveryServices']);
            Route::get('', [DeliveryController::class, 'read']);
            Route::put('update-delivery-status', [DeliveryController::class, 'updateDeliveryStatusByXmlId']);

            Route::prefix('{id}')->group(function () {
                Route::get('', [DeliveryController::class, 'read']);

                Route::prefix('shipments')->group(function () {
                    Route::get('count', [ShipmentsController::class, 'countByDelivery']);
                    Route::get('', [ShipmentsController::class, 'readByDelivery']);
                    Route::post('', [ShipmentsController::class, 'create']);
                });

                Route::prefix('delivery-order')->group(function () {
                    Route::put('', [DeliveryController::class, 'saveDeliveryOrder']);
                    Route::put('cancel', [DeliveryController::class, 'cancelDeliveryOrder']);
                });

                Route::put('', [DeliveryController::class, 'update']);
                Route::put('cancel', [DeliveryController::class, 'cancel']);
                Route::delete('', [DeliveryController::class, 'delete']);
            });
        });

        Route::prefix('shipments')->group(function () {
            Route::get('count', [ShipmentsController::class, 'count']);
            Route::get('active', [ShipmentsController::class, 'getActiveIds']);
            Route::get('delivered', [ShipmentsController::class, 'getDeliveredIds']);
            Route::get('', [ShipmentsController::class, 'read']);
            Route::get('similar-unshipped-shipments', [ShipmentsController::class, 'similarUnshippedShipments']);

            Route::prefix('exports')->group(function () {
                Route::get('new', [ShipmentsController::class, 'readNew']);
                Route::post('', [ShipmentsController::class, 'createShipmentExport']);
            });

            Route::prefix('{id}')->group(function () {
                Route::get('', [ShipmentsController::class, 'read']);

                Route::prefix('items')->group(function () {
                    Route::get('count', [ShipmentsController::class, 'countItems']);
                    Route::get('', [ShipmentsController::class, 'readItems']);

                    Route::prefix('{basketItemId}')->group(function () {
                        Route::get('', [ShipmentsController::class, 'readItem']);
                        Route::post('', [ShipmentsController::class, 'createItem']);
                        Route::delete('', [ShipmentsController::class, 'deleteItem']);
                        Route::put('', [ShipmentsController::class, 'cancelItem']);
                    });
                });

                Route::prefix('shipment-packages')->group(function () {
                    Route::get('count', [ShipmentPackagesController::class, 'countByShipment']);
                    Route::get('', [ShipmentPackagesController::class, 'readByShipment']);
                    Route::post('', [ShipmentPackagesController::class, 'create']);
                });

                Route::prefix('documents')->group(function () {
                    Route::get('acceptance-act', [ShipmentDocumentsController::class, 'acceptanceAct']);
                    Route::get('inventory', [ShipmentDocumentsController::class, 'inventory']);
                    Route::get('assembling-card', [ShipmentDocumentsController::class, 'assemblingCard']);
                    Route::get('generate-upd', [ShipmentDocumentsController::class, 'generateUPD']);
                    Route::get('upd', [ShipmentDocumentsController::class, 'upd']);
                });

                Route::put('', [ShipmentsController::class, 'update']);
                Route::put('mark-as-problem', [ShipmentsController::class, 'markAsProblem']);
                Route::put('mark-as-non-problem', [ShipmentsController::class, 'markAsNonProblem']);
                Route::put('cancel', [ShipmentsController::class, 'cancel']);
                Route::delete('', [ShipmentsController::class, 'delete']);
                Route::get('barcodes', [ShipmentsController::class, 'barcodes']);
                Route::get('cdek-receipt', [ShipmentsController::class, 'cdekReceipt']);
            });
        });

        Route::prefix('shipment-packages')->group(function () {
            Route::get('', [ShipmentPackagesController::class, 'read']);

            Route::prefix('{id}')->group(function () {
                Route::get('', [ShipmentPackagesController::class, 'read']);

                Route::prefix('items')->group(function () {
                    Route::get('count', [ShipmentPackagesController::class, 'countItems']);
                    Route::get('', [ShipmentPackagesController::class, 'readItems']);

                    Route::prefix('{basketItemId}')->group(function () {
                        Route::get('', [ShipmentPackagesController::class, 'readItem']);
                        Route::put('', [ShipmentPackagesController::class, 'setItem']);
                    });
                });

                Route::put('', [ShipmentPackagesController::class, 'update']);
                Route::delete('', [ShipmentPackagesController::class, 'delete']);
            });
        });

        Route::prefix('cargos')->group(function () {
            Route::get('count', [CargoController::class, 'count']);
            Route::get('', [CargoController::class, 'read']);
            Route::post('', [CargoController::class, 'create']);

            Route::prefix('{id}')->group(function () {
                Route::get('', [CargoController::class, 'read']);
                Route::put('', [CargoController::class, 'update']);
                Route::put('cancel', [CargoController::class, 'cancel']);
                Route::delete('', [CargoController::class, 'delete']);

                Route::prefix('courier-call')->group(function () {
                    Route::get('check', [CargoController::class, 'checkExternalStatus']);
                    Route::post('', [CargoController::class, 'createCourierCall']);
                    Route::put('cancel', [CargoController::class, 'cancelCourierCall']);
                });

                Route::prefix('documents')->group(function () {
                    Route::get('acceptance-act', [CargoDocumentsController::class, 'acceptanceAct']);
                });
            });
        });
    });
});
