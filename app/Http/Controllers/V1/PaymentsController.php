<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\PaymentService\PaymentService;
use App\Services\PaymentService\PaymentSystems\LocalPaymentSystem;
use App\Services\PaymentService\PaymentSystems\YandexPaymentSystem;
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
     * @param  int  $id
     * @param  Request  $request
     * @param  PaymentService  $paymentService
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/api/v1/payments/{id}/start",
     *     tags={"payment"},
     *     summary="Начать оплату",
     *     operationId="startPayment",
     *     @OA\Parameter(description="ID оплаты",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="paymentLink",type="string")
     *         )
     *     ),
     * )
     */
    public function start(int $id, Request $request, PaymentService $paymentService)
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

        $link = $payment->paymentSystem()->paymentLink($payment);
        if (!$link) {
            $link = $paymentService->start($payment->id, $returnUrl);
        }

        return response()->json([
            'paymentLink' => $link
        ]);
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
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
     * @param  Request  $request
     * @return JsonResponse
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
     * @return Response
     * @OA\Post(
     *     path="/api/v1/payments/handler/local",
     *     tags={"payment"},
     *     summary="Обработчик для уведомления об оплате для тестовой (local) системы оплаты",
     *     operationId="handlerLocal",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     ),
     * )
     */
    public function handlerLocal(Request $request): Response
    {
        $paymentSystem = new LocalPaymentSystem();
        $paymentSystem->handlePushPayment($request->all());

        return response('ok');
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     * @throws \YandexCheckout\Common\Exceptions\ApiException
     * @throws \YandexCheckout\Common\Exceptions\BadApiRequestException
     * @throws \YandexCheckout\Common\Exceptions\ExtensionNotFoundException
     * @throws \YandexCheckout\Common\Exceptions\ForbiddenException
     * @throws \YandexCheckout\Common\Exceptions\InternalServerError
     * @throws \YandexCheckout\Common\Exceptions\NotFoundException
     * @throws \YandexCheckout\Common\Exceptions\ResponseProcessingException
     * @throws \YandexCheckout\Common\Exceptions\TooManyRequestsException
     * @throws \YandexCheckout\Common\Exceptions\UnauthorizedException
     */
    public function handlerYandex(Request $request): JsonResponse
    {
        $paymentSystem = new YandexPaymentSystem();
        $paymentSystem->handlePushPayment($request->all());

        return response()->json([
            'processed' => 1
        ]);
    }
}
