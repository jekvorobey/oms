<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
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
    protected static $restIncludes = ['basket', 'basketItems'];
    
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
        $merchantFilter = $restQuery->getFilter('merchant_id');
        if($merchantFilter) {
            [$op, $value] = $merchantFilter[0];
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $restQuery = $offerService->newQuery();
            $restQuery->addFields(OfferDto::entity(), 'id')
                ->setFilter('merchant_id', $op, $value);
            $offersIds = $offerService->offers($restQuery)->pluck('id')->toArray();
            //todo Доделать получение списка заказов для офферов мерчанта
            $ordersIds = [];
            
            $restQuery->setFilter('id', $ordersIds);
            $restQuery->removeFilter('merchant_id');
        }
        
        //todo Доделать include товаров заказа
        
        return parent::modifyQuery($query, $restQuery);
    }
}
