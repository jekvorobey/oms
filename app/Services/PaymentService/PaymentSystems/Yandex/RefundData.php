<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\Tax;
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
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\Receipt\AgentType;
use YooKassa\Model\Receipt\PaymentMode;
use YooKassa\Model\Receipt\PaymentSubject;
use YooKassa\Model\Receipt\SettlementType;
use YooKassa\Model\ReceiptCustomer;
use YooKassa\Model\ReceiptItem;
use YooKassa\Model\ReceiptType;
use YooKassa\Request\Refunds\CreateRefundRequest;
use YooKassa\Request\Refunds\CreateRefundRequestBuilder;

class RefundData
{
    private MerchantService $merchantService;
    private OfferService $offerService;
    private PublicEventService $publicEventService;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->merchantService = resolve(MerchantService::class);
        $this->offerService = resolve(OfferService::class);
        $this->publicEventService = resolve(PublicEventService::class);
    }

    /**
     * Формирование данных для возврата платежа
     */
    public function getRefundData(string $paymentId, OrderReturn $orderReturn): CreateRefundRequestBuilder
    {
        $builder = CreateRefundRequest::builder();
        $builder
            ->setAmount(new MonetaryAmount($orderReturn->price))
            ->setCurrency(CurrencyCode::RUB)
            ->setPaymentId($paymentId)
            ->setReceiptPhone($orderReturn->order->customerPhone())
            ->setTaxSystemCode(Tax::TAX_SYSTEM_CODE_SIMPLE_MINUS_INCOME);

        return $builder;
    }

    /**
     * Сформировать чек возврата всех позиций
     */
    public function getRefundReceiptAllItemsData(Order $order, string $paymentId): CreatePostReceiptRequestBuilder
    {
        $builder = CreatePostReceiptRequest::builder();

        $builder
            ->setType(ReceiptType::REFUND)
            ->setPaymentId($paymentId)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true);

        $builder->setItems($this->getReceiptItemsForFullRefund($order));
        $builder->setSettlements($this->getSettlements($order));

        return $builder;
    }

    public function getRefundReceiptPartiallyData(
        string $paymentId,
        OrderReturn $orderReturn
    ): CreatePostReceiptRequestBuilder
    {
        $builder = CreatePostReceiptRequest::builder();
        $order = $orderReturn->order;
        $builder
            ->setType(ReceiptType::REFUND)
            ->setPaymentId($paymentId)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true);

        $builder->setItems($this->getReceiptItemsForPartiallyRefund($orderReturn));
        $builder->setSettlements($this->getSettlements($order, $orderReturn));

        return $builder;
    }

    /**
     * Сформировать позиции для возврата всех позиций заказа
     */
    protected function getReceiptItemsForFullRefund(Order $order): array
    {
        $receiptItems = [];
        $certificatesDiscount = 0;

        if ($order->spent_certificate > 0) {
            $certificatesDiscount = $order->spent_certificate;
        }

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
            $receiptItems[] = new ReceiptItem([
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
            ]);
        }
        if ((float) $order->delivery_price > 0) {
            $paymentMode = PaymentMode::FULL_PAYMENT;
            $deliveryPrice = $order->delivery_price;
            if (($certificatesDiscount > 0) && ($deliveryPrice >= $certificatesDiscount)) {
                $deliveryPrice -= $certificatesDiscount;
//                $paymentMode = $deliveryPrice > $certificatesDiscount ? self::PAYMENT_MODE_PARTIAL_PREPAYMENT : self::PAYMENT_MODE_FULL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
            }

            $receiptItems[] = new ReceiptItem([
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
            ]);
        }

        return $receiptItems;
    }

    /**
     * Get receipt items from order
     */
    protected function getReceiptItemsForPartiallyRefund(OrderReturn $orderReturn): array
    {
        $receiptItems = [];
        $order = $orderReturn->order;

        $merchants = collect();
        $merchantIds = $orderReturn->items
            ->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])
            ->map(static function (OrderReturnItem $orderReturnItem) {
                return $orderReturnItem->basketItem->product['merchant_id'];
            });

        if (!empty($merchantIds)) {
            $merchantIds = $merchantIds->toArray();
            $merchantQuery = $this->merchantService->newQuery()
                ->addFields(MerchantDto::entity(), 'id')
                ->include('vats')
                ->setFilter('id', $merchantIds);
            $merchants = $this->merchantService->merchants($merchantQuery)->keyBy('id');
        }

        $offers = collect();
        $offerIds = $orderReturn->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('offer_id')->toArray();
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

        foreach ($orderReturn->items as $item) {
            //$paymentMode = self::PAYMENT_MODE_FULL_PAYMENT; //TODO::Закомментировано до реализации IBT-433
            $basketItem = $item->basketItem;
            $itemValue = $item->price / $item->qty;
            $offer = $offers[$basketItem->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;

            $receiptItemInfo = $this->getReceiptItemInfo($basketItem, $offer, $merchant);
            $receiptItems[] = new ReceiptItem([
                'description' => $basketItem->name,
                'quantity' => $basketItem->qty,
                'amount' => [
                    'value' => $itemValue,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => $receiptItemInfo['vat_code'],
                'payment_mode' => $receiptItemInfo['payment_mode'],
                'payment_subject' => $receiptItemInfo['payment_subject'],
//                    'agent_type' => $receiptItemInfo['agent_type'],
            ]);
        }
        if ($orderReturn->is_delivery) {
            $paymentMode = PaymentMode::FULL_PAYMENT;
            $receiptItems[] = new ReceiptItem([
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => $orderReturn->price,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => VatCode::CODE_DEFAULT,
                'payment_mode' => $paymentMode,
                'payment_subject' => PaymentSubject::SERVICE,
//                'agent_type' => false,
            ]);
        }

        return $receiptItems;
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
    private function getSettlements(Order $order, OrderReturn $orderReturn = null): array
    {
        $settlements = [];
        $refundSum = $orderReturn->price ?? 0;

        if ($order->spent_certificate > 0) {
            //@TODO::Доработать возврат при оплате с помощью ПС
            if ($order->price > $order->spent_certificate) {
                $settlements[] = [
                    'type' => SettlementType::CASHLESS,
                    'amount' => [
                        'value' => $order->price - $order->spent_certificate,
                        'currency' => CurrencyCode::RUB,
                    ],
                ];
            }

            $settlements[] = [
                'type' => SettlementType::PREPAYMENT,
                'amount' => [
                    'value' => $order->spent_certificate,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        } else {
            $settlements[] = [
                'type' => SettlementType::CASHLESS,
                'amount' => [
                    'value' => $refundSum ?: $order->price,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        }

        return $settlements;
    }
}
