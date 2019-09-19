<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryPackage;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderDeliveryController extends Controller
{
    public function addPackages(int $id, Request $request)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $data = $request->all();
        $validator = Validator::make($data, [
            'items' => 'required|array',
            'items.*.items' => 'required|array',
            'items.*.delivery_at' => 'nullable|date'
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        foreach ($data['items'] as $item) {
            $package = new DeliveryPackage();
            $package->order_id = $id;
            $package->items = $item['items'];
            if (isset($item['delivery_at'])) {
                $package->delivery_at = $item['delivery_at'];
            }
            $ok = $package->save();
            if (!$ok) {
                throw new HttpException(500, 'unable to save delivery package');
            }
        }
        return response('', 204);
    }
    
    public function editPackage(int $packageId, Request $request)
    {
        $package = DeliveryPackage::find($packageId);
        if (!$package) {
            throw new NotFoundHttpException('delivery package not found');
        }
        $data = $request->all();
        $validator = Validator::make($data, [
            'items' => 'nullable|array',
            'delivery_at' => 'nullable|date'
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $package->fill($data);
        $ok = $package->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save delivery package');
        }
        
        return response('', 204);
    }
    
    public function deletePackage(int $packageId)
    {
        $package = DeliveryPackage::find($packageId);
        if (!$package) {
            throw new NotFoundHttpException('delivery package not found');
        }
        $ok = $package->delete();
        if (!$ok) {
            throw new HttpException(500, 'unable to delete delivery package');
        }
        
        return response('', 204);
    }
}
