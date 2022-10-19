<?php

namespace App\Http\Controllers;

use App\Models\Payment\Payment;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Redirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Foundation\Application;

/**
 * Контроллер, который эмулирует работу внешней системы оплаты.
 * Есть страница оплаты, которая редиректит обратно на платформу, при этом делая запрос к хэндлеру OMS.
 */
class LocalPaymentController extends Controller
{
    /**
     * @throws GuzzleException
     */
    public function index(Request $request): View|Factory|Redirector|RedirectResponse|Application
    {
        $paymentId = $request->get('paymentId');
        if (!$paymentId) {
            throw new NotFoundHttpException();
        }
        /** @var Payment $payment */
        $payment = Payment::byExternalPaymentId($paymentId)->firstOrFail();

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
                    'status' => 'done',
                ],
            ]);

            return redirect($payment->data['returnLink']);
        }
    }
}
