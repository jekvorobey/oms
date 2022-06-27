<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\Methods\UpdateRequest;
use App\Models\Payment\PaymentMethod;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentMethodsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/payments/methods",
     *     tags={"Заказы"},
     *     description="Получить список способов оплаты",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/PaymentMethod"))
     *         )
     *     )
     * )
     * Получить список способов оплаты
     */
    use ReadAction;

    public function modelClass(): string
    {
        return PaymentMethod::class;
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function readOne(PaymentMethod $paymentMethod, Request $request, RequestInitiator $client): JsonResponse
    {
        $restQuery = new RestQuery($request);

        return response()->json([
            'items' => $paymentMethod->toRest($restQuery),
        ]);
    }

    /**
     * @OA\Put (
     *     path="api/v1/payments/methods/{id}",
     *     tags={"Платежи"},
     *     description="Обновить параметры для способа оплаты",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(ref="#/components/schemas/PaymentMethod")
     *     ),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="400", description="bad request"),
     * )
     *
     * Обновить способ оплаты
     */
    public function update(PaymentMethod $paymentMethod, UpdateRequest $request): Response
    {
        $paymentMethod->fill($request->validated())->save();

        return response()->noContent();
    }
}
