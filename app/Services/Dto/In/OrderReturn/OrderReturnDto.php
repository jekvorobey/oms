<?php

namespace App\Services\Dto\In\OrderReturn;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * Class OrderReturnDto
 * @package App\Services\Dto\In\OrderReturn
 *
 * @property int $order_id - id заказа*
 * @property int $status - статус (см. константы App\Models\Order\OrderReturn)
 * @property Collection|OrderReturnItemDto[] $items - состав возврата*
 */
class OrderReturnDto extends Fluent
{
    /**
     * OrderReturnDto constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->items = collect();
    }
}
