<?php

namespace App\Http\Controllers\V1;

use App\Core\Order\OrderReader;
use App\Core\Order\OrderWriter;
use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Models\ReserveStatus;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function create(Request $request)
    {
        // todo Добавить провеку прав
        $data = $request->all();
        $validator = Validator::make($data, [
            'customer_id' => 'required|integer',
            'cost' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $writer = new OrderWriter();
        $id = $writer->create($data['customer_id'], $data['cost']);
        if (!$id) {
            throw new HttpException(500, 'unable to save order');
        }
        return response()->json([
            'id' => $id
        ]);
    }
    
    public function update(int $id, Request $request)
    {
        // todo Добавить провеку прав
        /** @var Order $order */
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $data = $request->all();
        $validator = Validator::make($data, [
            'customer_id' => 'nullable|integer',
            'cost' => 'nullable|numeric',
            'status' => ['nullable', Rule::in(OrderStatus::validValues())],
            'delivery_time' => 'nullable|datetime',
            'processing_time' => 'nullable|datetime',
            'reserve_status' => ['nullable', Rule::in(ReserveStatus::validValues())],
            'delivery_method' => ['nullable', Rule::in(DeliveryMethod::validValues())],
            'delivery_type' => ['nullable', Rule::in(DeliveryType::validValues())],
            'payment_status' => ['nullable', Rule::in(PaymentStatus::validValues())],
            'comment' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $order->fill($data);
        $ok = $order->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save order');
        }
        
        return response('', 204);
    }
    
    public function delete(int $id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $ok = $order->delete();
        if (!$ok) {
            throw new HttpException(500, 'unable to save order');
        }
        return response('', 204);
    }
}
