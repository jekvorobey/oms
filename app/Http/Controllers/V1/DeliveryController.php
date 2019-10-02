<?php
namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;


/**
 * Class DeliveryController
 * @package App\Http\Controllers\V1
 */
class DeliveryController extends Controller
{
    /**
     * todo заменить на настоящие данные
     *
     * @OA\Get(
     *     path="/api/v1/delivery",
     *     tags={"delivery"},
     *     summary="Непонятно что про доставку",
     *     operationId="deliveryInfo",
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     ),
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function info(Request $request)
    {
        // $request->basket
        // $request->deliveryAddress
        // $request->deliveryMethod (1 - самовывоз, 2 - доставка)
        $data =  [
            'cost' => (float) rand(100, 999),
            'dateFrom' => Carbon::now()->addDays(1)->toDateTimeString(),
            'dateTo' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
            'parcels' => [
                [
                    'date' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
                    'timeFrom' => '10:00',
                    'timeTo' => '15:00'
                ],
                [
                    'date' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
                    'timeFrom' => '10:00',
                    'timeTo' => '15:00'
                ]
            ]

        ];

        return response()->json($data, 200);
    }

    /**
     * todo заменить на настоящие данные
     *
     *  @OA\Get(
     *     path="/api/v1/delivery/pvz",
     *     tags={"delivery"},
     *     summary="Непонятно что про пункты самовывоза",
     *     operationId="deliveryPvz",
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     ),
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function infoPvz(Request $request)
    {
        $data =  [
            'cost' => (float) rand(100, 999),
            'dateFrom' => Carbon::now()->addDays(1)->toDateTimeString(),
            'dateTo' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
            'address' => 'Одинцово, ул Русаков, 124'

        ];

        return response()->json($data, 200);
    }
}
