<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\SDK;

use YooKassa\Request\Receipts\CreatePostReceiptRequestInterface as BaseCreatePostReceiptRequestInterface;

interface CreatePostReceiptRequestInterface extends BaseCreatePostReceiptRequestInterface
{
    /**
     * Возвращает идентификатор платежа, для которого формируется чек
     */
    public function getPaymentId(): string;

    /**
     * Устанавливает идентификатор платежа, для которого формируется чек
     */
    public function setPaymentId(string $value): CreatePostReceiptRequestInterface;
}
