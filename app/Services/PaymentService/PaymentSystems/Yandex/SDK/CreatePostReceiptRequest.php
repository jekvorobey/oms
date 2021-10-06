<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\SDK;

use YooKassa\Request\Receipts\CreatePostReceiptRequest as BaseCreatePostReceiptRequest;

class CreatePostReceiptRequest extends BaseCreatePostReceiptRequest
{
    /** Идентификатор объекта оплаты */
    private ?string $paymentId = null;

    /**
     * Возвращает Id объекта чека
     */
    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    /**
     * Устанавливает Id объекта чека
     */
    public function setPaymentId(string $value): void
    {
        $this->paymentId = $value;
    }

    /**
     * @inheritDoc
     * @return CreatePostReceiptRequestBuilder Инстанс билдера объектов запрсов
     */
    public static function builder()
    {
        return new CreatePostReceiptRequestBuilder();
    }
}
