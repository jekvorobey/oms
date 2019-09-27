<?php

namespace App\Http\Controllers\V1\Shipments;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackage;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PackageController extends Controller
{
    public function create(int $shipmentId, Request $request)
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
        
        $data = $this->validate($request, [
            'width' =>'required|integer',
            'height' =>'required|integer',
            'length' =>'required|integer',
            'wrapper_weight' =>'required|integer',
            
            'items' => 'required|array',
            'items.*.offer_id' => 'required|integer',
            'items.*.qty' => 'required|integer',
            'items.*.width' => 'required|integer',
            'items.*.height' => 'required|integer',
            'items.*.length' => 'required|integer',
            'items.*.weight' => 'required|integer',
        ]);
    
        $package = new ShipmentPackage();
        $package->shipment_id = $shipmentId;
        $package->setWrapper($data['wrapper_weight'], $data['width'], $data['height'], $data['length']);
        foreach ($data['items'] as $item) {
            $package->setProduct($item['offer_id'], $item);
        }
        $ok = $package->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment package');
        }
        return response('', 204);
    }
    
    public function updateWrapper(int $packageId, Request $request)
    {
        $package = ShipmentPackage::find($packageId);
        if (!$package) {
            throw new NotFoundHttpException('shipment package not found');
        }
        
        $data = $this->validate($request, [
            'width' =>'nullable|integer',
            'height' =>'nullable|integer',
            'length' =>'nullable|integer',
            'wrapper_weight' =>'required|integer',
        ]);
        $package->setWrapper($data['wrapper_weight'], $data['width'], $data['height'], $data['length']);
        $ok = $package->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment package');
        }
        return response('', 204);
    }
    
    public function setItem(int $packageId, int $offerId, Request $request)
    {
        $package = ShipmentPackage::find($packageId);
        if (!$package) {
            throw new NotFoundHttpException('shipment package not found');
        }
        $data = $this->validate($request, [
            'qty' => 'required|integer',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'length' => 'nullable|integer',
            'weight' => 'nullable|integer',
        ]);
        
        $package->setProduct($offerId, $data);
        
        $ok = $package->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment package');
        }
        return response('', 204);
    }
    
    public function delete(int $packageId)
    {
        $package = ShipmentPackage::find($packageId);
        if (!$package) {
            throw new NotFoundHttpException('shipment package not found');
        }
        $ok = $package->delete();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment package');
        }
        return response('', 204);
    }
}
