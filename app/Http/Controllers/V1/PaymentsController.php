<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\PaymentSystems\LocalPaymentSystem;
use App\Services\PaymentService\PaymentSystems\Yandex\YandexPaymentSystem;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class PaymentsController
 * @package App\Http\Controllers\V1
 */
class PaymentsController extends Controller
{
    /**
     * @OA\Post (
     *     path="api/v1/payments/{id}/start",
     *     tags={"Платежи"},
     *     description="",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="returnUrl", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="paymentLink", type="string")
     *         )
     *     ),
     *     @OA\Response(response="400", description="missing returnUrl"),
     *     @OA\Response(response="404", description="not found"),
     *     @OA\Response(response="405", description="access denied"),
     * )
     * @throws Exception
     */
    public function start(int $id, Request $request, PaymentService $paymentService): JsonResponse
    {
        $returnUrl = $request->get('returnUrl');
        if (!$returnUrl) {
            throw new BadRequestHttpException('missing returnUrl');
        }

        $payment = $paymentService->getPayment($id);
        if (!$payment) {
            throw new NotFoundHttpException();
        }

        if ($payment->status != PaymentStatus::NOT_PAID) {
            throw new AccessDeniedHttpException();
        }

        $link = $payment->payment_link;
        if (!$link) {
            $link = $paymentService->start($payment->id, $returnUrl);
        }

        return response()->json([
            'paymentLink' => $link,
        ]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/payments/byOrder",
     *     tags={"Платежи"},
     *     description="Получить платежи по заказу",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="payment_method", type="integer", example="0"),
     *          @OA\Property(property="orderIds", type="integer", example="0"),
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
    public function getByOrder(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'payment_method' => 'required|integer',
            'orderId' => 'required|integer',
        ]);
        $payment = Payment::query()
            ->where('order_id', $data['orderId'])
            ->where('payment_method', $data['payment_method'])
            ->first();
        if (!$payment) {
            throw new NotFoundHttpException('payment not found');
        }

        return response()->json($payment);
    }

    /**
     * @OA\Get(
     *     path="api/v1/payments",
     *     tags={"Платежи"},
     *     description="Получить список",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="orderIds", type="json", example="[1,2,3]"),
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
    public function payments(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'orderIds' => 'required|array',
        ]);
        $payments = Payment::query()
            ->whereIn('order_id', $data['orderIds'])
            ->get();
        if (!$payments) {
            throw new NotFoundHttpException('payments not found');
        }

        return response()->json(['items' => $payments]);
    }

    /**
     * @OA\Post (
     *     path="api/v1/payments/handler/local",
     *     tags={"Платежи"},
     *     description="Handler Local",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          @OA\Property(property="paymentId", type="integer"),
     *          @OA\Property(property="status", type="string"),
     *      ),
     *     ),
     *     @OA\Response(response="200", description="ok"),
     *     @OA\Response(response="400", description="bad request"),
     * )
     */
    public function handlerLocal(Request $request): Response
    {
        $paymentSystem = new LocalPaymentSystem();
        $paymentSystem->handlePushPayment($request->all());

        return response('ok');
    }

    /**
     * @OA\Post (
     *     path="api/v1/payments/handler/yandex",
     *     tags={"Платежи"},
     *     description="Handler Yandex",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          @OA\Property(property="processed", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="processed", type="integer", example="1")
     *         )
     *     ),
     *     @OA\Response(response="400", description="bad request"),
     * )
     * @throws Exception
     */
    public function handlerYandex(Request $request): JsonResponse
    {
        $paymentSystem = new YandexPaymentSystem();
        $paymentSystem->handlePushPayment($request->all());

        return response()->json([
            'processed' => 1,
        ]);
    }
}
