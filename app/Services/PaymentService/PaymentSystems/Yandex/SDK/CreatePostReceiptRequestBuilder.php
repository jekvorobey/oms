<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\SDK;

use YooKassa\Common\AbstractRequest;
use YooKassa\Model\MonetaryAmount;
use YooKassa\Model\ReceiptCustomer;
use YooKassa\Request\Receipts\CreatePostReceiptRequestBuilder as BaseCreatePostReceiptRequestBuilder;

class CreatePostReceiptRequestBuilder extends BaseCreatePostReceiptRequestBuilder
{
    /**
     * Собираемый объект запроса
     * @var CreatePostReceiptRequest
     */
    protected $currentObject;

    /**
     * @inheritDoc
     * @return CreatePostReceiptRequest Инстанс собираемого объекта запроса к API
     */
    protected function initCurrentObject()
    {
        $this->customer = new ReceiptCustomer();
        $this->amount = new MonetaryAmount();

        return new CreatePostReceiptRequest();
    }

    /**
     * Устанавливает Id объекта чека
     */
    public function setPaymentId(string $value): CreatePostReceiptRequestBuilder
    {
        $this->currentObject->setPaymentId($value);
        return $this;
    }

    /**
     * @param array|null $options
     * @return \YooKassa\Request\Receipts\CreatePostReceiptRequest|AbstractRequest
     */
    public function build(?array $options = null)
    {
        if (!empty($options['payment_id'])) {
            $this->setPaymentId($options['payment_id']);
        }

        return parent::build($options);
    }
}
