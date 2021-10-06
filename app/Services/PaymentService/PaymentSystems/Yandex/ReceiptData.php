<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\VatCode;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequest;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequestBuilder;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Dto\VatDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\PublicEvent\PublicEventDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\PublicEventService\PublicEventService;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Receipt\AgentType;
use YooKassa\Model\Receipt\PaymentMode;
use YooKassa\Model\Receipt\PaymentSubject;
use YooKassa\Model\Receipt\SettlementType;
use YooKassa\Model\ReceiptCustomer;
use YooKassa\Model\ReceiptItem;
use YooKassa\Model\ReceiptType;

class ReceiptData
{
    private MerchantService $merchantService;
    private OfferService $offerService;
    private PublicEventService $publicEventService;
    private CreatePostReceiptRequestBuilder $builder;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->merchantService = resolve(MerchantService::class);
        $this->offerService = resolve(OfferService::class);
        $this->publicEventService = resolve(PublicEventService::class);
        $this->builder = CreatePostReceiptRequest::builder();
    }

    public function getReceiptData(Order $order, string $paymentId): CreatePostReceiptRequestBuilder
    {
        $this->builder
            ->setType(ReceiptType::PAYMENT)
            ->setPaymentId($paymentId)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true);
        $this->addReceiptItems($order);
        $this->addSettlements($order);

        return $this->builder;
    }

    /**
     * Get receipt items from order
     */
    protected function addReceiptItems(Order $order): void
    {
        $certificatesDiscount = 0;

        if ($order->spent_certificate > 0) {
            $certificatesDiscount = $order->spent_certificate;
        }
        $itemsForReturn = OrderReturnItem::query()
            ->whereIn('basket_item_id', $order->basket->items->pluck('id'))
            ->pluck('basket_item_id')
            ->toArray();
        $deliveryForReturn = OrderReturn::query()
            ->where('order_id', $order->id)
            ->where('is_delivery', true)
            ->exists();

        $merchants = collect();
        $merchantIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('product.merchant_id');
        if (!empty($merchantIds)) {
            $merchantIds = $merchantIds->toArray();
            $merchantQuery = $this->merchantService->newQuery()
                ->addFields(MerchantDto::entity(), 'id')
                ->include('vats')
                ->setFilter('id', $merchantIds);
            $merchants = $this->merchantService->merchants($merchantQuery)->keyBy('id');
        }

        $offers = collect();
        $offerIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('offer_id')->toArray();
        if ($offerIds) {
            if ($order->isPublicEventOrder()) {
                $publicEventQuery = $this->publicEventService->query()
                    ->addFields(PublicEventDto::class)
                    ->setFilter('offer_id', $offerIds)
                    ->include('organizer', 'sprints.ticketTypes.offer');
                $publicEvents = $this->publicEventService->findPublicEvents($publicEventQuery);

                if ($publicEvents) {
                    $offers = $publicEvents->map(function (PublicEventDto $publicEvent) {
                        $offerInfo = [];
                        collect($publicEvent->sprints)->map(function ($sprint) use ($publicEvent, &$offerInfo) {
                            array_map(function ($ticketType) use ($publicEvent, &$offerInfo) {
                                $offerInfo = new OfferDto([
                                    'id' => $ticketType['offer']['id'],
                                    'merchant_id' => $publicEvent->organizer->merchant_id,
                                ]);
                            }, $sprint['ticketTypes']);
                        });
                        return $offerInfo;
                    })->keyBy('id');
                }
            } else {
                $offersQuery = $this->offerService->newQuery()
                    ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
                    ->include('product')
                    ->setFilter('id', $offerIds);
                $offers = $this->offerService->offers($offersQuery)->keyBy('id');
            }
        }

        foreach ($order->basket->items as $item) {
            if (!in_array($item->id, $itemsForReturn)) {
                //$paymentMode = self::PAYMENT_MODE_FULL_PAYMENT; //TODO::Закомментировано до реализации IBT-433

                $itemValue = $item->price / $item->qty;
                if (($certificatesDiscount > 0) && ($itemValue > 1)) {
                    $discountPrice = $itemValue - 1;
                    if ($discountPrice > $certificatesDiscount) {
                        $itemValue -= $certificatesDiscount;
                        $certificatesDiscount = 0;
                        //$paymentMode = self::PAYMENT_MODE_PARTIAL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
                    } else {
                        $itemValue -= $discountPrice;
                        $certificatesDiscount -= $discountPrice;
                        $paymentMode = PaymentMode::FULL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
                    }
                }
                $offer = $offers[$item->offer_id] ?? null;
                $merchantId = $offer['merchant_id'] ?? null;
                $merchant = $merchants[$merchantId] ?? null;

                $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant);
                $this->builder->addItem(new ReceiptItem([
                    'description' => $item->name,
                    'quantity' => $item->qty,
                    'amount' => [
                        'value' => $itemValue,
                        'currency' => CurrencyCode::RUB,
                    ],
                    'vat_code' => $receiptItemInfo['vat_code'],
                    'payment_mode' => $receiptItemInfo['payment_mode'],
                    'payment_subject' => $receiptItemInfo['payment_subject'],
//                    'agent_type' => $receiptItemInfo['agent_type'],
                ]));
            }
        }
        if ((float) $order->delivery_price > 0 && !$deliveryForReturn) {
            $paymentMode = PaymentMode::FULL_PAYMENT;
            $deliveryPrice = $order->delivery_price;
            if (($certificatesDiscount > 0) && ($deliveryPrice >= $certificatesDiscount)) {
                $deliveryPrice -= $certificatesDiscount;
//                $paymentMode = $deliveryPrice > $certificatesDiscount ? self::PAYMENT_MODE_PARTIAL_PREPAYMENT : self::PAYMENT_MODE_FULL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
            }

            $this->builder->addItem(new ReceiptItem([
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => $deliveryPrice,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => VatCode::CODE_DEFAULT,
                'payment_mode' => $paymentMode,
                'payment_subject' => PaymentSubject::SERVICE,
//                'agent_type' => false,
            ]));
        }
    }

    private function getReceiptItemInfo(BasketItem $item, ?object $offerInfo, ?object $merchant): array
    {
        $paymentMode = PaymentMode::FULL_PAYMENT;
        $paymentSubject = PaymentSubject::COMMODITY;
        $vatCode = VatCode::CODE_DEFAULT;
        $agentType = false;
        switch ($item->type) {
            case Basket::TYPE_MASTER:
                $paymentSubject = PaymentSubject::SERVICE;

                if (isset($offerInfo, $merchant)) {
                    $vatCode = $this->getVatCode($offerInfo, $merchant);
                }
                $agentType = AgentType::AGENT;
                break;
            case Basket::TYPE_PRODUCT:
                $paymentSubject = PaymentSubject::COMMODITY;

                if (isset($offerInfo, $merchant)) {
                    $vatCode = $this->getVatCode($offerInfo, $merchant);
                }
                $agentType = AgentType::COMMISSIONER;
                break;
            case Basket::TYPE_CERTIFICATE:
                $paymentSubject = PaymentSubject::PAYMENT;
                $paymentMode = PaymentMode::ADVANCE;
                break;
        }

        return [
            'vat_code' => $vatCode,
            'payment_mode' => $paymentMode,
            'payment_subject' => $paymentSubject,
            'agent_type' => $agentType,
        ];
    }

    private function getVatCode(object $offerInfo, object $merchant): ?int
    {
        $vatValue = null;
        $itemMerchantVats = $merchant['vats'];
        usort($itemMerchantVats, static function ($a, $b) {
            return $b['type'] - $a['type'];
        });
        foreach ($itemMerchantVats as $vat) {
            $vatValue = $this->getVatValue($vat, $offerInfo);

            if ($vatValue) {
                break;
            }
        }

        return [
            0 => VatCode::CODE_0_PERCENT,
            10 => VatCode::CODE_10_PERCENT,
            20 => VatCode::CODE_20_PERCENT,
        ][$vatValue] ?? VatCode::CODE_DEFAULT;
    }

    private function getVatValue(array $vat, object $offerInfo): ?int
    {
        switch ($vat['type']) {
            case VatDto::TYPE_GLOBAL:
                break;
            case VatDto::TYPE_MERCHANT:
                return $vat['value'];
            case VatDto::TYPE_BRAND:
                if ($offerInfo['product']['brand_id'] === $vat['brand_id']) {
                    return $vat['value'];
                }
                break;
            case VatDto::TYPE_CATEGORY:
                if ($offerInfo['product']['category_id'] === $vat['category_id']) {
                    return $vat['value'];
                }
                break;
            case VatDto::TYPE_SKU:
                if ($offerInfo['product_id'] === $vat['product_id']) {
                    return $vat['value'];
                }
                break;
        }

        return null;
    }

    /**
     * Добавление признаков оплаты (чек зачета предоплаты и обычная оплата)
     */
    private function addSettlements(Order $order): void
    {
        $settlements = [];
        if ($order->spent_certificate > 0) {
            $settlements[] = [
                'type' => SettlementType::PREPAYMENT,
                'amount' => [
                    'value' => $order->spent_certificate,
                    'currency' => CurrencyCode::RUB,
                ],
            ];

            if ($order->price > $order->spent_certificate) {
                $settlements[] = [
                    'type' => SettlementType::CASHLESS,
                    'amount' => [
                        'value' => $order->price - $order->spent_certificate,
                        'currency' => CurrencyCode::RUB,
                    ],
                ];
            }
        } else {
            $settlements[] = [
                'type' => SettlementType::CASHLESS,
                'amount' => [
                    'value' => $order->price,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        }

        $this->builder->setSettlements($settlements);
    }
}
