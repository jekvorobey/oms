<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Receipt;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\VatCode;
use Illuminate\Support\Collection;
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
use YooKassa\Model\ReceiptItem;

/**
 * Абстрактный класс для формирования запроса на создание чека
 *
 * @package App\Services\PaymentService\PaymentSystems\Yandex\Receipt
 */
abstract class ReceiptData
{
    protected MerchantService $merchantService;
    protected OfferService $offerService;
    protected PublicEventService $publicEventService;

    public function __construct()
    {
        $this->merchantService = resolve(MerchantService::class);
        $this->offerService = resolve(OfferService::class);
        $this->publicEventService = resolve(PublicEventService::class);
    }

    protected function getMerchants(array $merchantIds): Collection
    {
        $merchantQuery = $this->merchantService->newQuery()
            ->addFields(MerchantDto::entity(), 'id', 'inn')
            ->include('vats')
            ->setFilter('id', $merchantIds);
        return $this->merchantService->merchants($merchantQuery)->keyBy('id');
    }

    protected function getOffers(array $offerIds, Order $order): Collection
    {
        if ($order->isPublicEventOrder()) {
            $publicEventQuery = $this->publicEventService->query()
                ->addFields(PublicEventDto::class)
                ->setFilter('offer_id', $offerIds)
                ->include('organizer', 'sprints.ticketTypes.offer');
            $publicEvents = $this->publicEventService->findPublicEvents($publicEventQuery);

            if ($publicEvents->isEmpty()) {
                return collect();
            }

            return $publicEvents->map(function (PublicEventDto $publicEvent) {
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

        $offersQuery = $this->offerService->newQuery()
            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
            ->include('product')
            ->setFilter('id', $offerIds);

        return $this->offerService->offers($offersQuery)->keyBy('id');
    }

    protected function getReceiptItemInfo(BasketItem $item, ?object $offerInfo, ?object $merchant): array
    {
        $paymentMode = $this->getItemPaymentMode($item);
        $paymentSubject = $this->getItemPaymentSubject($item);
        $agentType = $this->getItemAgentType($item);
        $vatCode = $this->getItemVatCode($offerInfo, $merchant);

        $result = [
            'description' => $item->name,
            'quantity' => $item->qty,
            'amount' => [
                'value' => (float) $item->price / $item->qty,
                'currency' => CurrencyCode::RUB,
            ],
            'vat_code' => $vatCode,
            'payment_mode' => $paymentMode,
            'payment_subject' => $paymentSubject,
            'agent_type' => $agentType ?: false,
        ];

        if (isset($merchant) && $agentType) {
            $result['supplier'] = [
                'inn' => $merchant->inn,
            ];
        }

        return $result;
    }

    protected function getItemPaymentSubject(BasketItem $item): string
    {
        return [
            Basket::TYPE_MASTER => PaymentSubject::SERVICE,
            Basket::TYPE_PRODUCT => PaymentSubject::COMMODITY,
            Basket::TYPE_CERTIFICATE => PaymentSubject::PAYMENT,
        ][$item->type] ?? PaymentSubject::COMMODITY;
    }

    protected function getItemPaymentMode(BasketItem $item): string
    {
        return [
            Basket::TYPE_CERTIFICATE => PaymentMode::FULL_PAYMENT,
        ][$item->type] ?? PaymentMode::ADVANCE;
    }

    protected function getItemAgentType(BasketItem $item): ?string
    {
        return [
            Basket::TYPE_MASTER => AgentType::AGENT,
            Basket::TYPE_PRODUCT => AgentType::COMMISSIONER,
        ][$item->type] ?? null;
    }

    protected function getItemVatCode(?object $offerInfo, ?object $merchant): ?int
    {
        if (!isset($offerInfo, $merchant)) {
            return VatCode::CODE_DEFAULT;
        }

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

    protected function getVatValue(array $vat, object $offerInfo): ?int
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

    protected function getDeliveryReceiptItem(float $deliveryPrice): ReceiptItem
    {
        return new ReceiptItem([
            'description' => 'Доставка',
            'quantity' => 1,
            'amount' => [
                'value' => $deliveryPrice,
                'currency' => CurrencyCode::RUB,
            ],
            'vat_code' => VatCode::CODE_DEFAULT,
            'payment_mode' => PaymentMode::FULL_PAYMENT,
            'payment_subject' => PaymentSubject::SERVICE,
            'agent_type' => false,
        ]);
    }
}
