<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\SDK;

use YooKassa\Model\ReceiptType;
use YooKassa\Request\Receipts\CreatePostReceiptRequestSerializer as BaseCreatePostReceiptRequestSerializer;

class CreatePostReceiptRequestSerializer extends BaseCreatePostReceiptRequestSerializer
{
    private function serializeObjectId(CreatePostReceiptRequestInterface $request): array
    {
        $result = [];

        if ($request->getPaymentId()) {
            $result['payment_id'] = $request->getPaymentId();
        } else {
            if ($request->getType() === ReceiptType::PAYMENT) {
                $result['payment_id'] = $request->getObjectId();
            } elseif ($request->getType() === ReceiptType::REFUND) {
                $result['refund_id'] = $request->getObjectId();
            }
        }

        return $result;
    }
}
