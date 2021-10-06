<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\SDK;

use YooKassa\Client as BaseClient;
use YooKassa\Common\HttpVerb;
use YooKassa\Helpers\UUID;
use YooKassa\Request\Receipts\ReceiptResponseFactory;

class Client extends BaseClient
{
    public function createReceipt($receipt, $idempotenceKey = null)
    {
        $path = self::RECEIPTS_PATH;

        $headers = [];

        if ($idempotenceKey) {
            $headers[self::IDEMPOTENCY_KEY_HEADER] = $idempotenceKey;
        } else {
            $headers[self::IDEMPOTENCY_KEY_HEADER] = UUID::v4();
        }

        if (is_array($receipt)) {
            $receipt = CreatePostReceiptRequest::builder()->build($receipt);
        }

        $serializer = new CreatePostReceiptRequestSerializer();
        $serializedData = $serializer->serialize($receipt);
        $httpBody = $this->encodeData($serializedData);

        $response = $this->execute($path, HttpVerb::POST, null, $httpBody, $headers);

        $receiptResponse = null;
        if ($response->getCode() == 200) {
            $resultArray = $this->decodeData($response);
            $factory = new ReceiptResponseFactory();
            $receiptResponse = $factory->factory($resultArray);
        } else {
            $this->handleError($response);
        }

        return $receiptResponse;
    }
}
