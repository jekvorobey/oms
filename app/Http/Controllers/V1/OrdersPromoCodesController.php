<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderPromoCode;


/**
 * Class OrdersPromoCodesController
 * @package App\Http\Controllers\V1
 */
class OrdersPromoCodesController extends Controller
{
    /**
     * Возвращает сколько раз был применен промокод
     *
     * @param int $promoCodeId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(int $promoCodeId)
    {
        return response()->json([
            'promo_code_id' => $promoCodeId,
            'count'         => OrderPromoCode::query()->where('promo_code_id', $promoCodeId)->count()
        ]);
    }
}
