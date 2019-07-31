<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

/**
 * Класс-модель для сущности "Заказы"
 * Class Order
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property string $number - номер
 * @property float $cost - стоимость
 * @property int $status - статус
 * @property int $reserve_status - статус резерва
 * @property int $delivery_type - тип доставки (одним отправлением, несколькими отправлениями)
 * @property int $delivery_method - способ доставки
 * @property Carbon $processing_time - срок обработки (укомплектовки)
 * @property Carbon $delivery_time - срок доставки
 * @property string $comment - комментарий
 *
 * @property Basket $basket - корзина
 * @property Collection|BasketItem[] $basketItems - элементы в корзине для заказа
 */
class Order extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['customer_id', 'number', 'cost', 'status', 'reserve_status', 'delivery_type', 'delivery_method', 'processing_time', 'delivery_time', 'comment'];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /**
     * @var array
     */
    protected static $restIncludes = ['basket'];
    
    /**
     * @return HasOne
     */
    public function basket(): HasOne
    {
        return $this->hasOne(Basket::class);
    }
    
    /**
     * @param  Builder  $query
     * @param  RestQuery  $restQuery
     * @return Builder
     * @throws \Pim\Core\PimException
     */
    public static function modifyQuery(Builder $query, RestQuery $restQuery): Builder
    {
        /** @var RestQuery $restQuery */
        //Фильтр заказов по мерчанту
        $merchantFilter = $restQuery->getFilter('merchant_id');
        if($merchantFilter) {
            [$op, $value] = $merchantFilter[0];
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $restQuery = $offerService->newQuery();
            $restQuery->addFields(OfferDto::entity(), 'id')
                ->setFilter('merchant_id', $op, $value);
            $offersIds = $offerService->offers($restQuery)->pluck('id')->toArray();
            
            $query->whereHas('basket', function (Builder $query) use ($offersIds) {
                $query->whereHas('items', function (Builder $query) use ($offersIds) {
                    $query->whereIn('offer_id', $offersIds);
                });
            });
            
            $restQuery->removeFilter('merchant_id');
        }
    
        //Получение элементов корзины для заказов
        if ($restQuery->isIncluded('basketItems')) {
            $basketFields = $restQuery->getFields('basket');
            $restQuery->removeField('basket');
            
            $basketItemsFields = $restQuery->getFields('basketItems');
            $restQuery->removeField('basketItems');
            
            $query->with([
                'basket' => function (Relation $query) use ($basketFields, $basketItemsFields) {
                    if ($basketFields) {
                        $query->select(array_merge($basketFields, ['order_id']));
                    } else {
                        $query->select(['*']);
                    }
    
                    $query->with([
                        'items' => function (Relation $query) use ($basketItemsFields) {
                            if ($basketItemsFields) {
                                $query->select(array_merge($basketItemsFields, ['basket_id']));
                            } else {
                                $query->select(['*']);
                            }
                        },
                    ]);
                },
            ]);
        }
        
        return parent::modifyQuery($query, $restQuery);
    }
}
