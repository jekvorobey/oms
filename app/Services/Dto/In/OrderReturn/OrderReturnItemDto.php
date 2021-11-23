<?php

namespace App\Services\Dto\In\OrderReturn;

use Illuminate\Support\Fluent;

/**
 * Class OrderReturnItemDto
 * @package App\Services\Dto\In\OrderReturn
 *
 * @property int $basket_item_id - id возвращаемого элемента корзины*
 * @property float $qty - кол-во товара к возврату (обязательно для товаров)
 * @property int[] $ticket_ids - id билетов к возврату (обязательно для мастер-классов)
 * @property float|null $price - цена для возврата
 */
class OrderReturnItemDto extends Fluent
{
}
