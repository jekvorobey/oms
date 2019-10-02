<?php

namespace App\Http\Controllers\V1\Shipments;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Shipment;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShipmentsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/orders/{id}/shipments",
     *     tags={"shipment"},
     *     summary="Получить список отправлений заказа",
     *     operationId="listShipments",
     *     @OA\Parameter(description="ID заказа",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     ),
     * )
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(int $id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        
        return response()->json([
            'items' => $order->shipments
        ]);
    }
    
    /**
     * @OA\Schema(
     *     schema="CreateShipment",
     *     @OA\Property(
     *         property="items",
     *         type="array",
     *         @OA\Items(type="integer"),
     *     )
     * )
     *
     * @OA\Post(
     *     path="/api/v1/orders/{id}/shipments",
     *     tags={"shipment"},
     *     summary="Добавить отправления к заказу",
     *     operationId="addShipments",
     *     @OA\Parameter(
     *         description="ID заказа",
     *         in="path",
     *         name="id",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/CreateShipment"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="OK",
     *     ),
     * )
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function addShipments(int $id, Request $request)
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
            $shipment = new Shipment();
            $shipment->order_id = $id;
            $shipment->items = $item['items'];
            if (isset($item['delivery_at'])) {
                $shipment->delivery_at = $item['delivery_at'];
            }
            $ok = $shipment->save();
            if (!$ok) {
                throw new HttpException(500, 'unable to save shipment');
            }
        }
        return response('', 204);
    }
    
    /**
     * @OA\Put(
     *     path="/api/v1/shipments/{id}",
     *     tags={"shipment"},
     *     summary="Изменить отправление",
     *     operationId="updateShipment",
     *     @OA\Parameter(description="ID отправления",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="items",type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="delivery_at",type="string"),
     *                 @OA\Property(property="cargo_id",type="integer"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="OK",
     *     ),
     * )
     *
     * @param int $shipmentId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function editShipment(int $shipmentId, Request $request)
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
        $data = $request->all();
        $validator = Validator::make($data, [
            'items' => 'nullable|array',
            'delivery_at' => 'nullable|date',
            'cargo_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $shipment->fill($data);
        $ok = $shipment->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment');
        }
        
        return response('', 204);
    }
    
    /**
     * @OA\Delete(
     *     path="/api/v1/shipments/{id}",
     *     tags={"shipment"},
     *     summary="Удалить отправление",
     *     operationId="deleteShipment",
     *     @OA\Parameter(description="ID отправлкения",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=204,
     *         description="OK",
     *     ),
     * )
     *
     * @param int $shipmentId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deleteShipment(int $shipmentId)
    {
        $package = Shipment::find($shipmentId);
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
