<?php

namespace App\Core\Order;

use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\RestQuery;
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
        if ($restQuery->isIncluded('deliveries')) {
            $query->with('deliveries');
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
        if($merchantFilter) {
            [$op, $value] = $merchantFilter[0];
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
