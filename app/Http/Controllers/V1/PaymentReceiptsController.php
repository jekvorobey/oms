<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use OpenApi\Annotations as OA;

/**
 * Class PaymentsController
 * @package App\Http\Controllers\V1
 */
class PaymentReceiptsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/paymentReceipts",
     *     tags={"Платежи"},
     *     description="Получить список",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="id", type="json", example="[1,2,3]"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Payment"))
     *         )
     *     ),
     *     @OA\Response(response="404", description="payments not found"),
     * )
     */
    public function paymentReceipts(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'id' => 'required|array',
        ]);

        $paymentReceipts = PaymentReceipt::query()
            ->whereIn('id', $data['id'])
            ->get();

        if (!$paymentReceipts) {
            throw new NotFoundHttpException('payment receipts not found');
        }

        return response()->json(['items' => $paymentReceipts]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/paymentReceipts/byOrder",
     *     tags={"Платежи"},
     *     description="Получить чеки по заказу",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="orderId", type="integer", example="0"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/PaymentReceipt"))
     *         )
     *     ),
     *     @OA\Response(response="404", description="paymentReceipts not found"),
     * )
     */
    public function getByOrder(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'orderId' => 'required|integer',
        ]);

        $paymentReceipts = PaymentReceipt::query()
            ->where('order_id', $data['orderId'])
            ->get();

        return response()->json(['items' => $paymentReceipts]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/paymentReceipts/getByPayment",
     *     tags={"Платежи"},
     *     description="Получить чеки по оплате",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="paymentId", type="integer", example="0"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/PaymentReceipt"))
     *         )
     *     ),
     *     @OA\Response(response="404", description="paymentReceipts not found"),
     * )
     */
    public function getByPayment(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'paymentId' => 'required|integer',
        ]);

        $paymentReceipts = PaymentReceipt::query()
            ->where('payment_id', $data['paymentId'])
            ->get();

        return response()->json(['items' => $paymentReceipts]);
    }
}
