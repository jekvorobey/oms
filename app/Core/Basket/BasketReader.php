<?php

namespace App\Core\Basket;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

class BasketReader
{
    public const PAGE_SIZE = 10;

    public function list(RestQuery $restQuery): Collection
    {
        $query = Basket::query();

        $this->addInclude($query, $restQuery);
        $this->addFilter($query, $restQuery);
        $this->addPagination($query, $restQuery);

        foreach ($restQuery->sortIterator() as [$field, $dir]) {
            $query->orderBy($field, $dir);
        }

        return $query->get();
    }

    public function addInclude(Builder $query, RestQuery $restQuery): void
    {
        if ($restQuery->isIncluded('items')) {
            $query->with('items');
        }
        if ($restQuery->isIncluded('all')) {
            $query->with('items');
        }
    }

    public function count(RestQuery $restQuery): array
    {
        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : self::PAGE_SIZE;

        $query = Basket::query();
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

            $query->whereHas('items', function (Builder $query) use ($offersIds) {
                $query->whereIn('offer_id', $offersIds);
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

        //Функция-фильтр id корзин по id офферов
        $filterByOfferIds = function ($offerIds) {
            $basketIds = [];
            if ($offerIds) {
                $basketIds = Basket::query()
                    ->select('id')
                    ->whereHas('items', function (Builder $query) use ($offerIds) {
                        $query->whereIn('offer_id', $offerIds);
                    })
                    ->get()
                    ->pluck('id')
                    ->toArray();
                if (!$basketIds) {
                    $basketIds = [-1];
                }
            }

            return $basketIds;
        };

        //Функция-фильтр по свойству товара
        $filterByProductField = function (
            $filterField,
            $productField
        ) use (
            $restQuery,
            $modifiedRestQuery,
            $filterByOfferIds
        ) {
            $productVendorCodeFilter = $restQuery->getFilter($filterField);
            if ($productVendorCodeFilter) {
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

                $basketIds = $filterByOfferIds($offerIds);
                $existBasketIds = $modifiedRestQuery->getFilter('id') ? $modifiedRestQuery->getFilter('id')[0][1] : [];
                if ($existBasketIds) {
                    $basketIds = array_values(array_intersect($existBasketIds, $basketIds));
                }
                $modifiedRestQuery->setFilter('id', $basketIds);
                $modifiedRestQuery->removeFilter($filterField);
            }
        };

        //Фильтр по артикулу товара
        $filterByProductField('product_vendor_code', 'vendor_code');
        //Фильтр по бренду товара
        $filterByProductField('brands', 'brand_id');

        //фильтр по сумме корзины
        $totalPriceFilter = $restQuery->getFilter('price');
        if (is_array($totalPriceFilter) && count($totalPriceFilter) > 0) {
            $query->whereExists(function ($subquery) use ($totalPriceFilter) {
                $subquery
                    ->from(with(new BasketItem())->getTable())
                    ->selectRaw('SUM(price) AS basket_total_price')
                    ->whereRaw('basket_id = baskets.id')
                    ->groupBy('basket_id');

                foreach ($totalPriceFilter as $totalPriceFilterItem) {
                    [$op, $value] = $totalPriceFilterItem;
                    $subquery->having('basket_total_price', $op, $value);
                }
            });

            $modifiedRestQuery->removeFilter('price');
        }

        foreach ($modifiedRestQuery->filterIterator() as [$field, $op, $value]) {
            if ($op == '=' && is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $op, $value);
            }
        }

        //исключаем пустые корзины
        $query->whereHas('items');
    }

    protected function addPagination(Builder $query, RestQuery $restQuery): void
    {
        $pagination = $restQuery->getPage();
        if ($pagination) {
            $query->offset($pagination['offset'])->limit($pagination['limit']);
        }
    }
}
