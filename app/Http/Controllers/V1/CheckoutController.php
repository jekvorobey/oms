<?php

namespace App\Http\Controllers\V1;

use App\Core\Checkout\CheckoutOrder;
use App\Http\Controllers\Controller;
use App\Models\Basket\Basket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class CheckoutController
 * @package App\Http\Controllers\V1
 */
class CheckoutController extends Controller
{
    public function commit(Request $request): JsonResponse
    {
        $basketId = $request->get('basketId');
        if (!$basketId) {
            throw new BadRequestHttpException('basketId is required');
        }
        $basket = Basket::find($basketId);
        if (!$basket) {
            throw new BadRequestHttpException('Basket not found');
        }

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
        try {
            [$orderId, $orderNumber] = $checkoutOrder->save();
        } catch (\Throwable $e) {
            throw new HttpException($e->getCode() ? : 500, $e->getMessage());
        }

        return response()->json([
            'item' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ],
        ]);
    }
}
