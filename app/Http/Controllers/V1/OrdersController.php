<?php

namespace App\Http\Controllers\V1;

use App\Core\Order\OrderReader;
use App\Core\Order\OrderWriter;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OrdersController
 * @package App\Http\Controllers\V1
 */
class OrdersController extends Controller
{
    public function read(Request $request)
    {
        $reader = new OrderReader();
        return response()->json([
            'items' => $reader->list(new RestQuery($request)),
        ]);
    }
    
    public function count(Request $request)
    {
        $reader = new OrderReader();
        return response()->json($reader->count(new RestQuery($request)));
    }
    
    public function setPayments(int $id, Request $request)
    {
        $reader = new OrderReader();
        $writer = new OrderWriter();
        
        $order = $reader->byId($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        /** @var \Illuminate\Validation\Validator $validator */
        $data = $request->all();
        $validator = Validator::make($data, [
            'payments' => 'required|array',
            'payments.*.id' => 'nullable|integer',
            'payments.*.sum' => 'nullable|numeric',
            'payments.*.status' => 'nullable|integer',
            'payments.*.type' => 'nullable|integer',
            'payments.*.payment_system'=> 'nullable|integer',
            'payments.*.data' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $payments = collect();
        foreach ($data['payments'] as $rawPayment) {
            $payments[] = new Payment($rawPayment);
        }
        $writer->setPayments($order, $payments);
        return response('', 204);
    }
}
