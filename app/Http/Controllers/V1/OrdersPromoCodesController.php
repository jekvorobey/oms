<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderPromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class OrdersPromoCodesController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/orders/promo-codes/{promoCodeId}/count",
     *     tags={"Заказы"},
     *     description="Возвращает сколько раз был применен промокод",
     *     @OA\Parameter(name="promoCodeId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={},
     *          @OA\Property(property="customer_id", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="promo_code_id", type="integer"),
     *             @OA\Property(property="count", type="integer"),
     *         )
     *     )
     * )
     *
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
                ->where('is_canceled', false)
                ->count();
        }

        return response()->json([
            'promo_code_id' => $promoCodeId,
            'count' => $count,
        ]);
    }
}
