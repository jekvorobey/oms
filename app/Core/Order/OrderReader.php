<?php

namespace App\Core\Order;

use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

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

        if ($restQuery->isIncluded('basket') || $restQuery->isIncluded('basketitem')) {
            $query->with([
                'basket' => function (Relation $query) use ($restQuery) {
                    if ($basketFields = $restQuery->getFields('basket')) {
                        $query->select(array_merge($basketFields, ['order_id']));
                    } else {
                        $query->select(['*']);
                    }
                    if ($restQuery->isIncluded('basketitem')) {
                        $query->with([
                            'items' => function (Relation $query) use ($restQuery) {
                                if ($basketItemsFields = $restQuery->getFields('basketitem')) {
                                    $query->select(array_merge($basketItemsFields, ['basket_id']));
                                } else {
                                    $query->select(['*']);
                                }
                            }
                        ]);
                    }
                }
            ]);
        }
    }

    /**
     * @param Builder $query
     * @param RestQuery $restQuery
     * @throws \Pim\Core\PimException
     */
    protected function addFilter(Builder $query, RestQuery $restQuery): void
    {
        //Фильтр заказов по мерчанту
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

            $restQuery->removeFilter('merchant_id');
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
            $query->whereIn('customer_id', $customerIds);
            $restQuery->removeFilter('customer');
        }

        foreach ($restQuery->filterIterator() as [$field, $op, $value]) {
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
