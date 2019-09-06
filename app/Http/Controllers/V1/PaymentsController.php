<?php

namespace App\Http\Controllers\V1;

use App\Core\Payment\PaymentProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentsController extends Controller
{
    public function start(int $id, Request $request)
    {
        $returnUrl = $request->get('returnUrl');
        if (!$returnUrl) {
            throw new BadRequestHttpException('missing returnUrl');
        }
        $processor = new PaymentProcessor();
        $payment = $processor->paymentById($id);
        if (!$payment) {
            throw new NotFoundHttpException();
        }
        $link = $processor->startPayment($payment, $returnUrl);
    
        return response()->json([
            'paymentLink' => $link
        ]);
    }
    
    public function handlerLocal()
    {
        return response('ok');
    }
}
