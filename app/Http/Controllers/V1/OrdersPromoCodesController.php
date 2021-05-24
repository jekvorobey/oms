<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderPromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Class OrdersPromoCodesController
 * @package App\Http\Controllers\V1
 */

class OrdersPromoCodesController extends Controller
{
    /**
     * Возвращает сколько раз был применен промокод
     */
    public function count(int $promoCodeId): JsonResponse
    {
        $data = $this->validate(request(), [
            'customer_id' => 'nullable|integer',
        ]);

        if (!isset($data['customer_id'])) {
            $count = OrderPromoCode::query()->where('promo_code_id', $promoCodeId)->count();
        } else {
            $count = Order::query()
                ->where('customer_id', $data['customer_id'])
                ->whereHas('promoCodes', function (Builder $query) use ($promoCodeId) {
                    $query->where('promo_code_id', $promoCodeId);
                })
                ->count();
        }

        return response()->json([
            'promo_code_id' => $promoCodeId,
            'count' => $count,
        ]);
    }
}
