<?php

namespace App\Core\Order;

use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

class OrderReader
{
    const PAGE_SIZE = 10;

    public function byId(int $id): ?Order
    {
        /** @var Order $order */
        $order = Order::query()->where('id', $id)->first();
        return $order;
    }

    public function list(RestQuery $restQuery): Collection
    {
        $query = Order::query();

        $this->addInclude($query, $restQuery);
        $this->addSelect($query, $restQuery);
        $this->addFilter($query, $restQuery);
        $this->addPagination($query, $restQuery);

        foreach ($restQuery->sortIterator() as [$field, $dir]) {
            $query->orderBy($field, $dir);
        }

        return $query->get();
    }

    public function addInclude(Builder $query, RestQuery $restQuery): void
    {
        if ($restQuery->isIncluded('basket')) {
            $query->with('basket');
        }
        if ($restQuery->isIncluded('basketitem') || $restQuery->isIncluded('basket.items')) {
            $query->with('basket.items');
        }
        if ($restQuery->isIncluded('history')) {
            $query->with('history');
        }
        if ($restQuery->isIncluded('promoCodes')) {
            $query->with('promoCodes');
        }
        if ($restQuery->isIncluded('deliveries')) {
            $query->with('deliveries');
        }
        if ($restQuery->isIncluded('payments')) {
            $query->with('payments');
        }
        if ($restQuery->isIncluded('deliveries.shipments')) {
            $query->with('deliveries.shipments');
        }
        if ($restQuery->isIncluded('deliveries.shipments.basketItems')) {
            $query->with('deliveries.shipments.basketItems');
        }
        if ($restQuery->isIncluded('deliveries.shipments.packages')) {
            $query->with('deliveries.shipments.packages');
        }
        if ($restQuery->isIncluded('all')) {
            $query->with('history')
                ->with('basket.items')
                ->with('promoCodes')
                ->with('payments')
                ->with('deliveries.shipments.basketItems')
                ->with('deliveries.shipments.packages')
                ->with('deliveries.shipments.cargo');
        }
    }

    public function count(RestQuery $restQuery): array
    {
        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : self::PAGE_SIZE;

        $query = Order::query();
        $this->addFilter($query, $restQuery);
        $total = $query->count();
        $pages = ceil($total / $pageSize);

        return [
            'total' => $total,
            'pages' => $pages,
            'pageSize' => $pageSize,
        ];
    }

    /**
     * @param Builder $query
     * @param RestQuery $restQuery
     */
    protected function addSelect(Builder $query, RestQuery $restQuery): void
    {
        if ($fields = $restQuery->getFields('order')) {
            $query->select($fields);
        }
    }

    /**
     * @param Builder $query
     * @param RestQuery $restQuery
     * @throws \Pim\Core\PimException
     */
    protected function addFilter(Builder $query, RestQuery $restQuery): void
    {
        $modifiedRestQuery = clone $restQuery;

        //Фильтр по мерчантам
        $merchantFilter = $restQuery->getFilter('merchant_id');
        if ($merchantFilter) {
            [$op, $value] = current($merchantFilter);
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $offerQuery = $offerService->newQuery();
            $offerQuery->addFields(OfferDto::entity(), 'id')
                ->setFilter('merchant_id', $op, $value);
            $offersIds = $offerService->offers($offerQuery)->pluck('id')->toArray();

            $query->whereHas('basket', function (Builder $query) use ($offersIds) {
                $query->whereHas('items', function (Builder $query) use ($offersIds) {
                    $query->whereIn('offer_id', $offersIds);
                });
            });
            $modifiedRestQuery->removeFilter('merchant_id');
        }

        //Фильтр по покупателю (ФИО, e-mail или телефон)
        $customerFilter = $restQuery->getFilter('customer');
        if ($customerFilter) {
            [$op, $value] = current($customerFilter);
            /** @var CustomerService $customerService */
            $customerService = resolve(CustomerService::class);
            $customerQuery = $customerService->newQuery()
                ->addFields(CustomerDto::entity(), 'id')
                ->setFilter('email_phone_full_name', $op, $value);
            $customerIds = $customerService->customers($customerQuery)->pluck('id')->toArray();

            $existCustomerIds = $restQuery->getFilter('customer_id') ?
                $restQuery->getFilter('customer_id')[0][1] : [];
            if ($existCustomerIds) {
                $customerIds = array_values(array_intersect($existCustomerIds, $customerIds));
                $restQuery->removeFilter('customer_id');
            }
            $modifiedRestQuery->setFilter('customer_id', $customerIds);
            $modifiedRestQuery->removeFilter('customer');
        }

        //Функция-фильтр id отправлений по id офферов
        $filterByOfferIds = function ($offerIds) {
            $orderIds = [];
            if ($offerIds) {
                $orderIds = Order::query()
                    ->select('id')
                    ->whereHas('basket', function (Builder $query) use ($offerIds) {
                        $query->whereHas('items', function (Builder $query) use ($offerIds) {
                            $query->whereIn('offer_id', $offerIds);
                        });
                    })
                    ->get()
                    ->pluck('id')
                    ->toArray();
                if (!$orderIds) {
                    $orderIds = [-1];
                }
            }

            return $orderIds;
        };
        //Фильтр по коду оффера мерчанта из ERP мерчанта
        $offerXmlIdFilter = $restQuery->getFilter('offer_xml_id');
        if($offerXmlIdFilter) {
            [$op, $value] = current($offerXmlIdFilter);
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $offerQuery = $offerService->newQuery()
                ->addFields(OfferDto::entity(), 'id')
                ->setFilter('xml_id', $op, $value);
            $offerIds = $offerService->offers($offerQuery)->pluck('id')->toArray();

            $orderIds = $filterByOfferIds($offerIds);
            $existOrderIds = $modifiedRestQuery->getFilter('id') ? $modifiedRestQuery->getFilter('id')[0][1] : [];
            if ($existOrderIds) {
                $orderIds = array_values(array_intersect($existOrderIds, $orderIds));
            }
            $modifiedRestQuery->setFilter('id', $orderIds);
            $modifiedRestQuery->removeFilter('offer_xml_id');
        }

        //Функция-фильтр по свойству товара
        $filterByProductField = function ($filterField, $productField) use (
            $restQuery,
            $modifiedRestQuery,
            $filterByOfferIds
        ) {
            $productVendorCodeFilter = $restQuery->getFilter($filterField);
            if($productVendorCodeFilter) {
                [$op, $value] = current($productVendorCodeFilter);

                /** @var ProductService $productService */
                $productService = resolve(ProductService::class);
                $productQuery = $productService->newQuery()
                    ->addFields(ProductDto::entity(), 'id')
                    ->setFilter($productField, $op, $value);
                $productIds = $productService->products($productQuery)->pluck('id')->toArray();

                $offerIds = [];
                if ($productIds) {
                    /** @var OfferService $offerService */
                    $offerService = resolve(OfferService::class);
                    $offerQuery = $offerService->newQuery()
                        ->addFields(OfferDto::entity(), 'id')
                        ->setFilter('product_id', $productIds);
                    $offerIds = $offerService->offers($offerQuery)->pluck('id')->toArray();
                }

                $orderIds = $filterByOfferIds($offerIds);
                $existOrderIds = $modifiedRestQuery->getFilter('id') ? $modifiedRestQuery->getFilter('id')[0][1] : [];
                if ($existOrderIds) {
                    $orderIds = array_values(array_intersect($existOrderIds, $orderIds));
                }
                $modifiedRestQuery->setFilter('id', $orderIds);
                $modifiedRestQuery->removeFilter($filterField);
            }
        };
        //Фильтр по артикулу товара
        $filterByProductField('product_vendor_code', 'vendor_code');
        //Фильтр по бренду товара
        $filterByProductField('brands', 'brand_id');

        //Фильтр по способу оплаты
        $paymentMethodFilter = $restQuery->getFilter('payment_method');
        if ($paymentMethodFilter) {
            [$op, $value] = current($paymentMethodFilter);
            $query->whereHas('payments', function (Builder $query) use ($value) {
                $query->whereIn('payment_method', (array)$value);
            });
            $modifiedRestQuery->removeFilter('payment_method');
        }

        //Фильтр по складу
        $storeFilter = $restQuery->getFilter('stores');
        if ($storeFilter) {
            [$op, $value] = current($storeFilter);
            $query->whereHas('deliveries', function (Builder $query) use ($value) {
                $query->whereHas('shipments', function (Builder $query) use ($value) {
                    $query->whereIn('store_id', (array)$value);
                });
            });
            $modifiedRestQuery->removeFilter('stores');
        }

        //Фильтр по службе доставки
        $deliveryServiceFilter = $restQuery->getFilter('delivery_service');
        if ($deliveryServiceFilter) {
            [$op, $value] = current($deliveryServiceFilter);
            $query->whereHas('deliveries', function (Builder $query) use ($value) {
                $query->whereIn('delivery_service', (array)$value);
            });
            $modifiedRestQuery->removeFilter('delivery_service');
        }

        //Фильтр по службе городу доставки
        $deliveryCityFilter = $restQuery->getFilter('delivery_city');
        if ($deliveryCityFilter) {
            [$op, $value] = current($deliveryCityFilter);
            $query->whereHas('deliveries', function (Builder $query) use ($value) {
                $query->where('delivery_address->city_guid', $value);
            });
            $modifiedRestQuery->removeFilter('delivery_city');
        }

        //Фильтр по PSD
        $psdFilter = $restQuery->getFilter('psd');
        if ($psdFilter) {
            [$op, $value] = current($psdFilter);
            $query->whereHas('deliveries', function (Builder $query) use ($value) {
                $query->whereHas('shipments', function (Builder $query) use ($value) {
                    $query->where('psd', '>=', $value[0]);
                    $query->where('psd', '<=', $value[1]);
                });
            });
            $modifiedRestQuery->removeFilter('psd');
        }
        //Фильтр по PDD
        $pddFilter = $restQuery->getFilter('pdd');
        if ($pddFilter) {
            [$op, $value] = current($pddFilter);
            $query->whereHas('deliveries', function (Builder $query) use ($value) {
                $query->where('pdd', '>=', $value[0]);
                $query->where('pdd', '<=', $value[1]);
            });
            $modifiedRestQuery->removeFilter('pdd');
        }

        foreach ($modifiedRestQuery->filterIterator() as [$field, $op, $value]) {
            if ($op == '=' && is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $op, $value);
            }
        }
    }

    /**
     * @param Builder $query
     * @param RestQuery $restQuery
     */
    protected function addPagination(Builder $query, RestQuery $restQuery): void
    {
        $pagination = $restQuery->getPage();
        if ($pagination) {
            $query->offset($pagination['offset'])->limit($pagination['limit']);
        }
    }
}
