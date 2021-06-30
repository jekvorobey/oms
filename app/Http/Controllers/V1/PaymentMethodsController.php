<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentMethod;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentMethodsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/payments/payment-methods",
     *     tags={"Платежи"},
     *     description="Получить информацию о способе(-ах) оплаты",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"id"},
     *          @OA\Property(property="id", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="payment_methods", type="array", @OA\Items(ref="#/components/schemas/PaymentMethod"))
     *         )
     *     ),
     * )
     *
     * Получить информацию о способе(-ах) оплаты
     * @return JsonResponse
     */
    public function read()
    {
        $data = $this->validate(request(), [
            'id' => 'integer|nullable',
        ]);
        $query = PaymentMethod::query();
        if (isset($data['id'])) {
            $query->where('id', $data['id']);
        }

        return response()->json([
            'payment_methods' => $query->get(),
        ], 200);
    }

    /**
     * @OA\Put (
     *     path="api/v1/payments/payment-methods/{id}",
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
     * Обновить параметры для способа оплаты
     * @return Application|ResponseFactory|Response
     */
    public function update(int $id)
    {
        $data = $this->validate(request(), [
            'name' => 'required|string',
            'accept_prepaid' => 'required|boolean',
            'accept_virtual' => 'required|boolean',
            'accept_real' => 'required|boolean',
            'accept_postpaid' => 'required|boolean',
            'covers' => 'required|numeric',
            'max_limit' => 'required|numeric',
            'excluded_payment_methods' => 'nullable|json',
            'excluded_regions' => 'nullable|json',
            'excluded_delivery_services' => 'nullable|json',
            'excluded_offer_statuses' => 'nullable|json',
            'excluded_customers' => 'nullable|json',
            'active' => 'required|boolean',
        ]);

        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = PaymentMethod::query()->find($id);
        if (!$paymentMethod) {
            throw new NotFoundHttpException();
        }

        $paymentMethod->name = $data['name'];
        $paymentMethod->accept_prepaid = $data['accept_prepaid'];
        $paymentMethod->accept_virtual = $data['accept_virtual'];
        $paymentMethod->accept_real = $data['accept_real'];
        $paymentMethod->accept_postpaid = $data['accept_postpaid'];
        $paymentMethod->covers = $data['covers'];
        $paymentMethod->max_limit = $data['max_limit'];
        $paymentMethod->excluded_payment_methods = $data['excluded_payment_methods'] ?? null;
        $paymentMethod->excluded_regions = $data['excluded_regions'] ?? null;
        $paymentMethod->excluded_delivery_services = $data['excluded_delivery_services'] ?? null;
        $paymentMethod->excluded_offer_statuses = $data['excluded_offer_statuses'] ?? null;
        $paymentMethod->excluded_customers = $data['excluded_customers'] ?? null;
        $paymentMethod->active = $data['active'];

        $paymentMethod->save();
        return response('', 204);
    }
}
