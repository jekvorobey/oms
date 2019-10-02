<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Core\Payment\LocalPaymentSystem;
use App\Models\Payment\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentsController extends Controller
{
    /**
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
    public function start(int $id, Request $request)
    {
        $returnUrl = $request->get('returnUrl');
        if (!$returnUrl) {
            throw new BadRequestHttpException('missing returnUrl');
        }

        $payment = Payment::findById($id);
        if (!$payment) {
            throw new NotFoundHttpException();
        }
        $link = $payment->start($returnUrl);

        return response()->json([
            'paymentLink' => $link
        ]);
    }
    
    /**
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
    public function handlerLocal(Request $request)
    {
        $paymentSystem = new LocalPaymentSystem();
        $paymentSystem->handlePushPayment($request->all());
        return response('ok');
    }
}
