<?php

namespace App\Services\PaymentService\PaymentSystems\KitInvest\Receipt;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Services\PaymentService\PaymentSystems\KitInvest\OrderData;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\VatCode;
use IBT\KitInvest\Enum\ReceiptEnum;
use IBT\KitInvest\Models\SubjectModel;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Services\OfferService\OfferService;
use Pim\Services\PublicEventService\PublicEventService;

/**
 * Абстрактный класс для формирования запроса на создание чека
 *
 * @package App\Services\PaymentService\PaymentSystems\KitInvest\Receipt
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
        float $price,
        ?int $payAttribute = null
    ): array {
        $payAttribute = $payAttribute ?: $this->getItemPaymentMode($item);
        if ($payAttribute == ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_CREDIT_PAYMENT) {
            $goodsAttribute = ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_PAYMENT;
        } else {
            $goodsAttribute = $this->getItemPaymentSubject($item);
        }
        $agentType = $this->getItemAgentType($item);
        $vatCode = $this->getItemVatCode($offerInfo, $merchant);

        $result = [
            'subjectName' => $item->name,
            'quantity' => $quantity,
            'price' => $price * 100,
            'tax' => $vatCode,
            'payAttribute' => $payAttribute,
            'goodsAttribute' => $goodsAttribute,
        ];

        if (isset($merchant) && $agentType) {
            $result['supplierInn'] = $merchant->inn;
            $result['supplierInfo'] = [
                'name' => $merchant->legal_name,
                //'phoneNumbers' => $merchant->phone, //ToDo Add merchant global phone in database
            ];
            $result['agentType'] = $agentType;
        }

        return $result;
    }

    protected function getItemPaymentSubject(BasketItem $item): int
    {
        if ($item->type === Basket::TYPE_PRODUCT && !$this->isFullPayment) {
            return ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_PAYMENT;
        }

        return [
                Basket::TYPE_MASTER => ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_SERVICE,
                Basket::TYPE_PRODUCT => ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_COMMODITY,
                Basket::TYPE_CERTIFICATE => ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_PAYMENT,
            ][$item->type] ?? ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_COMMODITY;
    }

    protected function getItemPaymentMode(BasketItem $item): int
    {
        if ($item->type === Basket::TYPE_PRODUCT && !$this->isFullPayment) {
            return ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PREPAYMENT; //Полная предварительная оплата
        }

        return [
                Basket::TYPE_CERTIFICATE => ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_ADVANCE, //Аванс
            ][$item->type] ?? ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PAYMENT; //Полная оплата, в том числе с учетом аванса
    }

    protected function getItemAgentType(BasketItem $item): ?int
    {
        return [
                Basket::TYPE_MASTER => ReceiptEnum::RECEIPT_AGENT_TYPE_AGENT,
                Basket::TYPE_PRODUCT => ReceiptEnum::RECEIPT_AGENT_TYPE_COMMISSIONER,
            ][$item->type] ?? null;
    }

    protected function getDeliveryReceiptItem(float $deliveryPrice, ?int $payAttribute = null): SubjectModel
    {
        if (!$payAttribute) {
            $payAttribute = $this->isFullPayment ? ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PAYMENT : ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PREPAYMENT;
        }
        if ($payAttribute == ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_CREDIT_PAYMENT) {
            $goodsAttribute = ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_PAYMENT;
        } else {
            $goodsAttribute = $this->isFullPayment ? ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_SERVICE : ReceiptEnum::RECEIPT_PAYMENT_SUBJECT_PAYMENT;
        }

        return new SubjectModel([
            'subjectName' => 'Доставка',
            'quantity' => 1,
            'price' => $deliveryPrice * 100,
            'tax' => VatCode::CODE_DEFAULT,
            'payAttribute' => $payAttribute,
            'goodsAttribute ' => $goodsAttribute,
        ]);
    }
}
