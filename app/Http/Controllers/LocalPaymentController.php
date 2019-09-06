<?php

namespace App\Http\Controllers;

use App\Models\Payment\Payment as ExternalPayment;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Контроллер, который эмулирует работу внешней системы оплаты.
 * Есть страница оплаты, которая редиректит обратно на платформу, при этом делая запрос к хэндлеру OMS.
 */
class LocalPaymentController extends Controller
{
    public function index(Request $request)
    {
        $paymentId = $request->get('paymentId');
        if (!$paymentId) {
            throw new NotFoundHttpException();
        }
        $payment = ExternalPayment::query()->where('data->paymentId', $paymentId)->first();
        if (!$payment) {
            throw new NotFoundHttpException();
        }
        
        $done = $request->get('done');
        
        if (!$done) {
            return view('payment', [
                'doneLink' => route('paymentPage', ['paymentId' => $paymentId, 'done' => 'sync']),
            ]);
        } else {
            $data = $payment->data;
            $data['done'] = true;
            $payment->data = $data;
            $payment->save();
            
            $client = new Client();
            $client->post($payment->data['handlerUrl'], [
                'json' => [
                    'paymentId' => $paymentId,
                    'status' => 'done'
                ]
            ]);
            
            return redirect($payment->data['returnLink']);
        }
    }
}
