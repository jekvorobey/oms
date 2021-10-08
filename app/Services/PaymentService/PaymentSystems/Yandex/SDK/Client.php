<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\SDK;

use YooKassa\Client as BaseClient;
use YooKassa\Common\HttpVerb;
use YooKassa\Helpers\UUID;
use YooKassa\Request\Receipts\ReceiptResponseFactory;

/**
 * Class Client
 *
 * @package App\Services\PaymentService\PaymentSystems\Yandex\SDK
 */
class Client extends BaseClient
{
    /**
     * Переопределение запроса создания чека
     * В некоторых ситуациях необходимо прикреплять чек возврата к payment_id, что не поддерживает метод SDK
     *
     * @param array|\YooKassa\Request\Receipts\CreatePostReceiptRequestInterface $receipt
     * @param null $idempotenceKey
     * @return \YooKassa\Request\Receipts\AbstractReceiptResponse|\YooKassa\Request\Receipts\PaymentReceiptResponse|\YooKassa\Request\Receipts\RefundReceiptResponse|\YooKassa\Request\Receipts\SimpleReceiptResponse|null
     * @throws \YooKassa\Common\Exceptions\ApiConnectionException
     * @throws \YooKassa\Common\Exceptions\ApiException
     * @throws \YooKassa\Common\Exceptions\AuthorizeException
     * @throws \YooKassa\Common\Exceptions\BadApiRequestException
     * @throws \YooKassa\Common\Exceptions\ExtensionNotFoundException
     * @throws \YooKassa\Common\Exceptions\ForbiddenException
     * @throws \YooKassa\Common\Exceptions\InternalServerError
     * @throws \YooKassa\Common\Exceptions\NotFoundException
     * @throws \YooKassa\Common\Exceptions\ResponseProcessingException
     * @throws \YooKassa\Common\Exceptions\TooManyRequestsException
     * @throws \YooKassa\Common\Exceptions\UnauthorizedException
     */
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
