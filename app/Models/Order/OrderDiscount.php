<?php

namespace App\Models\Order;

/**
 * Class OrderDiscount
 * @package App\Models\Order
 */
class OrderDiscount
{
    /**
     * ID скидки
     * @var int
     */
    public $id;

    /**
     * Название скидки
     * @var string
     */
    public $name;

    /**
     * Тип скидки (на что скидка)
     * @var int
     */
    public $type;

    /**
     * Размер скидки (всегда в рублях), которую она внесла в данный заказ
     * @var int
     */
    public $change;

    /**
     * Спонсор скидки (ID Мерчаната или null – если Маркетплейс)
     * @var int|null
     */
    public $merchant_id;

    /**
     * Видна ли была скидка в каталоге
     * @var bool
     */
    public $visible_in_catalog;

    /**
     * Скидка доступна только по промокоду
     * @var bool
     */
    public $promo_code_only;

    /**
     * Влияние скидки на офферы
     * Формат:
     * { offer_id: int, product_id: int, change: int }
     * @var array|null
     */
    public $items;

    /**
     * OrderDiscount constructor.
     * @param array $params
     */
    public function __construct($params)
    {
        $this->id = intval($params['id']);
        $this->name = $params['name'];
        $this->type = intval($params['type']);
        $this->change = intval($params['type']);
        $this->merchant_id = isset($params['merchant_id']) ? intval($params['merchant_id']) : null;
        $this->visible_in_catalog = isset($params['visible_in_catalog']) && $params['visible_in_catalog'];
        $this->promo_code_only = isset($params['promo_code_only']) && $params['promo_code_only'];
        $this->items = null;
        if (isset($params['items']) && !empty($params['items'])) {
            $this->items = [];
            foreach ($params['items'] as $item) {
                $this->items[] = [
                    'product_id' => intval($item['product_id']),
                    'offer_id' => intval($item['offer_id']),
                    'change' => intval($item['change']),
                ];
            }
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'change' => $this->change,
            'merchant_id' => $this->merchant_id,
            'visible_in_catalog' => $this->visible_in_catalog,
            'promo_code_only' => $this->promo_code_only,
            'items' => $this->items,
        ];
    }
}
