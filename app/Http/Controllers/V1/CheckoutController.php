<?php

namespace App\Http\Controllers\V1;

use App\Core\Checkout\CheckoutOrder;
use App\Http\Controllers\Controller;
use App\Services\BasketService\CustomerBasketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class CheckoutController
 * @package App\Http\Controllers\V1
 */
class CheckoutController extends Controller
{
    /**
     * @OA\Post (
     *     path="api/v1/checkout/commit",
     *     tags={"Checkout"},
     *     description="Создать комментрий",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="customerId", type="integer", format="text", example="0"),
     *          @OA\Property(property="basketId", type="integer", format="text", example="0"),
     *          @OA\Property(property="receiverName", type="string"),
     *          @OA\Property(property="receiverPhone", type="string"),
     *          @OA\Property(property="receiverEmail", type="string"),
     *          @OA\Property(property="cost", type="number"),
     *          @OA\Property(property="price", type="number"),
     *          @OA\Property(property="paymentMethodId", type="integer"),
     *          @OA\Property(property="confirmationTypeId", type="integer"),
     *          @OA\Property(property="spentBonus", type="integer"),
     *          @OA\Property(property="addedBonus", type="integer"),
     *
     *          @OA\Property(property="promoCodes[0]['basketItemId']", type="integer"),
     *          @OA\Property(property="promoCodes[0]['name']", type="string"),
     *          @OA\Property(property="promoCodes[0]['code']", type="string"),
     *          @OA\Property(property="promoCodes[0]['type']", type="integer"),
     *          @OA\Property(property="promoCodes[0]['status']", type="integer"),
     *          @OA\Property(property="promoCodes[0]['discount_id']", type="integer"),
     *          @OA\Property(property="promoCodes[0]['gift_id']", type="integer"),
     *          @OA\Property(property="promoCodes[0]['bonus_id']", type="integer"),
     *          @OA\Property(property="promoCodes[0]['owner_id']", type="integer"),
     *
     *          @OA\Property(property="certificates", type="string", example="[]"),
     *
     *          @OA\Property(property="prices[0]['promo_code_id']", type="integer"),
     *          @OA\Property(property="prices[0]['offerId']", type="integer"),
     *          @OA\Property(property="prices[0]['cost']", type="number"),
     *          @OA\Property(property="prices[0]['price']", type="number"),
     *          @OA\Property(property="prices[0]['bonusSpent']", type="integer"),
     *          @OA\Property(property="prices[0]['bonusDiscount']", type="integer"),
     *
     *          @OA\Property(property="discounts[0]['discount_id']", type="integer"),
     *          @OA\Property(property="discounts[0]['name']", type="string"),
     *          @OA\Property(property="discounts[0]['type']", type="integer"),
     *          @OA\Property(property="discounts[0]['change']", type="number"),
     *          @OA\Property(property="discounts[0]['merchant_id']", type="integer"),
     *          @OA\Property(property="discounts[0]['visible_in_catalog']", type="boolean"),
     *          @OA\Property(property="discounts[0]['promo_code_only']", type="boolean"),
     *          @OA\Property(property="discounts[0]['items'][0]['offer_id']", type="integer"),
     *          @OA\Property(property="discounts[0]['items'][0]['product_id']", type="integer"),
     *          @OA\Property(property="discounts[0]['items'][0]['change']", type="number"),
     *
     *          @OA\Property(property="bonuses[0]['bonus_id'][0]['change']", type="integer"),
     *          @OA\Property(property="bonuses[0]['name'][0]['change']", type="string"),
     *          @OA\Property(property="bonuses[0]['type'][0]['change']", type="integer"),
     *          @OA\Property(property="bonuses[0]['bonus'][0]['change']", type="integer"),
     *          @OA\Property(property="bonuses[0]['valid_period'][0]['change']", type="integer"),
     *          @OA\Property(property="bonuses[0]['items'][0]['offer_id']", type="integer"),
     *          @OA\Property(property="bonuses[0]['items'][0]['product_id']", type="integer"),
     *          @OA\Property(property="bonuses[0]['items'][0]['bonus']", type="integer"),
     *
     *          @OA\Property(property="deliveryTypeId", type="integer"),
     *          @OA\Property(property="deliveryPrice", type="number"),
     *          @OA\Property(property="deliveryCost'", type="number"),
     *
     *          @OA\Property(property="deliveries[0]['tariffId']", type="integer"),
     *          @OA\Property(property="deliveries[0]['deliveryMethod']", type="integer"),
     *          @OA\Property(property="deliveries[0]['deliveryService']", type="integer"),
     *          @OA\Property(property="deliveries[0]['pointId']", type="integer"),
     *          @OA\Property(property="deliveries[0]['selectedDate']", type="string"),
     *          @OA\Property(property="deliveries[0]['deliveryTimeStart']", type="string"),
     *          @OA\Property(property="deliveries[0]['deliveryTimeEnd']", type="string"),
     *          @OA\Property(property="deliveries[0]['deliveryTimeCode']", type="string"),
     *          @OA\Property(property="deliveries[0]['dt']", type="integer"),
     *          @OA\Property(property="deliveries[0]['pdd']", type="string"),
     *          @OA\Property(property="deliveries[0]['cost']", type="number"),
     *          @OA\Property(property="deliveries[0]['deliveryAddress']", type="string", example="[]"),
     *          @OA\Property(property="deliveries[0]['receiverName']", type="string"),
     *          @OA\Property(property="deliveries[0]['receiverPhone']", type="string"),
     *          @OA\Property(property="deliveries[0]['receiverEmail']", type="string"),
     *
     *          @OA\Property(property="deliveries[0]['shipments'][0]['merchantId']", type="integer"),
     *          @OA\Property(property="deliveries[0]['shipments'][0]['storeId']", type="integer"),
     *          @OA\Property(property="deliveries[0]['shipments'][0]['cost']", type="number"),
     *          @OA\Property(property="deliveries[0]['shipments'][0]['date']", type="string"),
     *          @OA\Property(property="deliveries[0]['shipments'][0]['psd']", type="string"),
     *          @OA\Property(property="deliveries[0]['shipments'][0]['items']", type="string", example="[]"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\Property(property="suggestions", type="json"),
     *     ),
     *     @OA\Response(response="400", description="Bad request"),
     * )
     */
    public function commit(Request $request, CustomerBasketService $basketService): JsonResponse
    {
        $basketId = $request->get('basketId');
        if (!$basketId) {
            throw new BadRequestHttpException('basketId is required');
        }
        $basket = $basketService->getBasket($basketId);

        $data = $this->validate($request, [
            'customerId' => ['required', 'integer'],
            'basketId' => ['required', 'integer'],

            'receiverName' => [Rule::requiredIf($basket->isPublicEventBasket()), 'string'],
            'receiverPhone' => [Rule::requiredIf($basket->isPublicEventBasket()), 'regex:/^\+7\d{10}$/'],
            'receiverEmail' => [Rule::requiredIf($basket->isPublicEventBasket()), 'email', 'nullable'],

            'cost' => ['required', 'numeric'],
            'price' => ['required', 'numeric'],
            'paymentMethodId' => ['required', 'integer'],
            'confirmationTypeId' => ['required', 'integer'],
            'spentBonus' => ['integer', 'nullable'],
            'addedBonus' => ['integer', 'nullable'],

            'promoCodes' => ['present', 'array'],
            'promoCodes.*.promo_code_id' => ['sometimes', 'integer'],
            'promoCodes.*.name' => ['sometimes', 'string'],
            'promoCodes.*.code' => ['sometimes', 'string'],
            'promoCodes.*.type' => ['sometimes', 'integer'],
            'promoCodes.*.status' => ['sometimes', 'integer'],
            'promoCodes.*.discount_id' => ['sometimes', 'nullable', 'integer'],
            'promoCodes.*.gift_id' => ['sometimes', 'nullable', 'integer'],
            'promoCodes.*.bonus_id' => ['sometimes', 'nullable', 'integer'],
            'promoCodes.*.owner_id' => ['sometimes', 'nullable', 'integer'],

            'certificates' => ['present', 'array'],
//            'certificates.*' => ['sometimes', 'string'],

            'prices' => ['required', 'array'],
            'prices.*.basketItemId' => ['required', 'integer'],
            'prices.*.offerId' => ['required', 'integer'],
            'prices.*.cost' => ['required', 'numeric'],
            'prices.*.price' => ['required', 'numeric'],
            'prices.*.bonusSpent' => ['integer', 'nullable'],
            'prices.*.bonusDiscount' => ['integer', 'nullable'],

            'discounts' => ['present', 'array'],
            'discounts.*.discount_id' => ['sometimes', 'integer'],
            'discounts.*.name' => ['sometimes', 'string'],
            'discounts.*.type' => ['sometimes', 'integer'],
            'discounts.*.change' => ['sometimes', 'numeric'],
            'discounts.*.merchant_id' => ['sometimes', 'integer', 'nullable'],
            'discounts.*.visible_in_catalog' => ['sometimes', 'boolean'],
            'discounts.*.promo_code_only' => ['sometimes', 'boolean'],
            'discounts.*.items' => ['sometimes', 'array', 'nullable'],
            'discounts.*.items.*.offer_id' => ['sometimes', 'integer'],
            'discounts.*.items.*.product_id' => ['sometimes', 'integer'],
            'discounts.*.items.*.change' => ['sometimes', 'numeric'],

            'bonuses' => ['present', 'array'],
            'bonuses.*.bonus_id' => ['sometimes', 'integer'],
            'bonuses.*.name' => ['sometimes', 'string'],
            'bonuses.*.type' => ['sometimes', 'integer'],
            'bonuses.*.bonus' => ['sometimes', 'integer'],
            'bonuses.*.valid_period' => ['sometimes', 'integer', 'nullable'],
            'bonuses.*.items' => ['present', 'array'],
            'bonuses.*.items.*.offer_id' => ['sometimes', 'integer'],
            'bonuses.*.items.*.product_id' => ['sometimes', 'integer'],
            'bonuses.*.items.*.bonus' => ['sometimes', 'integer'],

            'deliveryTypeId' => ['required', 'integer'],
            'deliveryPrice' => ['required', 'numeric'],
            'deliveryCost' => ['required', 'numeric'],

            'deliveries' => [Rule::requiredIf($basket->isProductBasket()), 'array'],
            'deliveries.*.tariffId' => ['required', 'integer'],
            'deliveries.*.deliveryMethod' => ['required', 'integer'],
            'deliveries.*.deliveryService' => ['required', 'integer'],
            'deliveries.*.pointId' => ['sometimes', 'integer', 'nullable'],
            'deliveries.*.selectedDate' => ['sometimes', 'string', 'nullable'],
            'deliveries.*.deliveryTimeStart' => ['sometimes', 'string', 'nullable'],
            'deliveries.*.deliveryTimeEnd' => ['sometimes', 'string', 'nullable'],
            'deliveries.*.deliveryTimeCode' => ['sometimes', 'string', 'nullable'],
            'deliveries.*.dt' => ['required', 'integer'],
            'deliveries.*.pdd' => ['required', 'string'],
            'deliveries.*.cost' => ['required', 'numeric'],
            'deliveries.*.deliveryAddress' => ['sometimes', 'array', 'nullable'],
            'deliveries.*.receiverName' => ['required', 'string'],
            'deliveries.*.receiverPhone' => ['required', 'regex:/^\+7\d{10}$/'],
            'deliveries.*.receiverEmail' => ['email', 'nullable'],

            'deliveries.*.shipments' => ['required', 'array'],
            'deliveries.*.shipments.*.merchantId' => ['required', 'integer'],
            'deliveries.*.shipments.*.storeId' => ['required', 'integer'],
            'deliveries.*.shipments.*.cost' => ['numeric', 'nullable'],
            'deliveries.*.shipments.*.date' => ['sometimes', 'string', 'nullable'],
            'deliveries.*.shipments.*.psd' => ['required', 'string'],
            'deliveries.*.shipments.*.items' => ['required', 'array'],
        ]);

        $checkoutOrder = CheckoutOrder::fromArray($data);

        [$orderId, $orderNumber] = $checkoutOrder->save();

        return response()->json([
            'item' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ],
        ]);
    }
}
