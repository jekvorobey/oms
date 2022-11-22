<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Receipt;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\VatCode;
use App\Services\PaymentService\PaymentSystems\Yandex\OrderData;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Services\MerchantService\MerchantService;
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
abstract class ReceiptData extends OrderData
{
    protected MerchantService $merchantService;
    protected OfferService $offerService;
    protected PublicEventService $publicEventService;

    protected bool $isFullPayment = false;

    /** @return static */
    public function setIsFullPayment(bool $isFullPayment): self
    {
        $this->isFullPayment = $isFullPayment;

        return $this;
    }

    protected function getReceiptItemInfo(
        BasketItem $item,
        ?object $offerInfo,
        ?object $merchant,
        float $quantity,
        ?string $paymentMode = null
    ): array {
        $paymentMode = $paymentMode ?: $this->getItemPaymentMode($item);
        $paymentSubject = $this->getItemPaymentSubject($item);
        $agentType = $this->getItemAgentType($item, $merchant);
        $vatCode = $this->getItemVatCode($offerInfo, $merchant);
        $productName = $this->getShortName($item->name);

        $result = [
            'description' => $productName,
            'quantity' => $quantity,
            'amount' => [
                'value' => $item->unit_price,
                'currency' => CurrencyCode::RUB,
            ],
            'vat_code' => $vatCode,
            'payment_mode' => $paymentMode,
            'payment_subject' => $paymentSubject,
        ];

        if (isset($merchant) && $agentType) {
            $result['supplier'] = [
                'inn' => $merchant->inn,
            ];
            $result['agent_type'] = $agentType;
        }

        return $result;
    }

    protected function getItemPaymentSubject(BasketItem $item): string
    {
        if ($item->type === Basket::TYPE_PRODUCT && !$this->isFullPayment) {
            return PaymentSubject::PAYMENT;
        }

        return [
            Basket::TYPE_MASTER => PaymentSubject::SERVICE,
            Basket::TYPE_PRODUCT => PaymentSubject::COMMODITY,
            Basket::TYPE_CERTIFICATE => PaymentSubject::PAYMENT,
        ][$item->type] ?? PaymentSubject::COMMODITY;
    }

    protected function getItemPaymentMode(BasketItem $item): string
    {
        if ($item->type === Basket::TYPE_PRODUCT && !$this->isFullPayment) {
            return PaymentMode::FULL_PREPAYMENT;
        }

        return [
            Basket::TYPE_CERTIFICATE => PaymentMode::ADVANCE,
        ][$item->type] ?? PaymentMode::FULL_PAYMENT;
    }

    protected function getItemAgentType(BasketItem $item, ?object $merchant): ?string
    {
        if (!$merchant) {
            return null;
        }

        return [
            Basket::TYPE_MASTER => $merchant->agent_type ? AgentType::AGENT : null,
            Basket::TYPE_PRODUCT => $merchant->commissionaire_type ? AgentType::COMMISSIONER : null,
        ][$item->type] ?? null;
    }

    protected function getDeliveryReceiptItem(float $deliveryPrice, ?string $paymentMode = null): ReceiptItem
    {
        $paymentMode = $paymentMode ?: ($this->isFullPayment ? PaymentMode::FULL_PAYMENT : PaymentMode::FULL_PREPAYMENT);

        return new ReceiptItem([
            'description' => 'Доставка',
            'quantity' => 1,
            'amount' => [
                'value' => $deliveryPrice,
                'currency' => CurrencyCode::RUB,
            ],
            'vat_code' => VatCode::CODE_DEFAULT,
            'payment_mode' => $paymentMode,
            'payment_subject' => $this->isFullPayment ? PaymentSubject::SERVICE : PaymentSubject::PAYMENT,
        ]);
    }
}
