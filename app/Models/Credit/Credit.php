<?php

namespace App\Models\Credit;

use App\Services\CreditService\CreditSystems\CreditLine\CreditLineSystem;
use App\Services\CreditService\CreditSystems\CreditSystemInterface;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Кредит",
 *     @OA\Property(
 *         property="order_id",
 *         type="integer",
 *         description="id заказа"
 *     ),
 *     @OA\Property(
 *         property="credit_system",
 *         type="integer",
 *         description="кредитная система"
 *     ),
 * )
 *
 * Class Credit
 * @package App\Models
 *
 * @property int $order_id
 * @property int $credit_system
 */
class Credit extends AbstractModel
{
    /**
     * Credit constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function creditSystem(): CreditSystemInterface
    {
        //switch ($this->credit_system) {
        //    case CreditSystem::CREDIT_LINE:
                return new CreditLineSystem();
        //}

        //return null;
    }
}
