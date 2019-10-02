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
    /**
     * @OA\Post(
     *     path="/api/v1/shipments/packages",
     *     tags={"package"},
     *     summary="Создать новую коробку",
     *     operationId="createPackage",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="width",type="integer"),
     *                 @OA\Property(property="height",type="integer"),
     *                 @OA\Property(property="length",type="integer"),
     *                 @OA\Property(property="wrapper_weight",type="integer"),
     *                 @OA\Property(
     *                      property="items",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(property="offer_id",type="integer"),
     *                          @OA\Property(property="qty",type="integer"),
     *                          @OA\Property(property="width",type="integer"),
     *                          @OA\Property(property="height",type="integer"),
     *                          @OA\Property(property="length",type="integer"),
     *                          @OA\Property(property="weight",type="integer"),
     *                      )
     *                  ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="OK"
     *     ),
     * )
     */
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
    /**
     * @OA\Put(
     *     path="/api/v1/shipments/packages/{id}/wrapper",
     *     tags={"package"},
     *     summary="Изменить парамеры упаковки",
     *     operationId="updatePackageWrapper",
     *     @OA\Parameter(
     *         description="ID коробки",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             format="int32",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="width",type="integer"),
     *                 @OA\Property(property="height",type="integer"),
     *                 @OA\Property(property="length",type="integer"),
     *                 @OA\Property( property="wrapper_weight",type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="OK"
     *     ),
     * )
     */
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
    /**
     * @OA\Put(
     *     path="/api/v1/shipments/packages/{id}/offers/{offerId}",
     *     tags={"package"},
     *     summary="Изменить парамеры упаковки",
     *     operationId="setPackageItem",
     *     @OA\Parameter(description="ID коробки",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *     @OA\Parameter(description="ID торгового предложения",in="path",name="offerId",required=true,
     *          @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="qty",type="integer"),
     *                 @OA\Property(property="width", type="integer"),
     *                 @OA\Property(property="height",type="integer"),
     *                 @OA\Property(property="length",type="integer"),
     *                 @OA\Property(property="weight",type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *          description="OK"
     *     ),
     * )
     */
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
    /**
     * @OA\Delete(
     *     path="/api/v1/shipments/packages/{id}",
     *     tags={"package"},
     *     summary="Удалить коробку",
     *     operationId="deletePackage",
     *     @OA\Parameter(description="ID коробки",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=204,
     *          description="OK"
     *     ),
     * )
     */
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
