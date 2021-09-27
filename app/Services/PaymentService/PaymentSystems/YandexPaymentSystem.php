<?php

namespace App\Services\PaymentService\PaymentSystems;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Dto\VatDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Monolog\Logger;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;
use YooKassa\Client;
use YooKassa\Model\Notification\NotificationSucceeded;
use YooKassa\Model\Notification\NotificationWaitingForCapture;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\PaymentStatus;
use GuzzleHttp\Client as GuzzleClient;
use App\Models\Basket\Basket;

/**
 * Class YandexPaymentSystem
 * @package App\Services\PaymentService\PaymentSystems
 */
class YandexPaymentSystem implements PaymentSystemInterface
{
    public const CURRENCY_RUB = 'RUB';

    /** @var Client */
    private $yandexService;
    /** @var Logger */
    private $logger;
    /** @var MerchantService */
    private $merchantService;
    /** @var OfferService */
    private $offerService;

    /**
     * YandexPaymentSystem constructor.
     */
    public function __construct()
    {
        $this->yandexService = resolve(Client::class);
        $this->logger = Log::channel('payments');
        $this->merchantService = resolve(MerchantService::class);
        $this->offerService = resolve(OfferService::class);
    }

    /**
     * @throws \Exception
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void
    {
        $order = $payment->order;
        $idempotenceKey = uniqid('', true);

        $paymentData = [
            'amount' => [
                'value' => number_format($order->price, 2, '.', ''),
                'currency' => self::CURRENCY_RUB,
            ],
            'capture' => false,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnLink,
            ],
            'description' => "Заказ №{$order->id}",
            'receipt' => [
                'tax_system_code' => '3',
                'phone' => $order->customerPhone(),
                'items' => $this->generateItems($order),
            ],
            'metadata' => [
                'source' => config('app.url'),
            ],
        ];
        /*$email = $order->customerEmail();
        if ($email) {
            $paymentData['receipt']['email'] = $email;
        }*/
        $this->logger->info('Create payment data', $paymentData);
        $response = $this->yandexService->createPayment($paymentData, $idempotenceKey);
        $this->logger->info('Create payment result', $response->jsonSerialize());

        $data = $payment->data;
        $data['externalPaymentId'] = $response['id'];
        $data['paymentUrl'] = $response['confirmation']['confirmation_url'];
        $payment->data = $data;

        $ok = $payment->save();
        if (!$ok) {
            $this->logger->error('Payment not saved after create', [
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Получить от внешней системы ссылку страницы оплаты.
     */
    public function paymentLink(Payment $payment): ?string
    {
        return $payment->data['paymentUrl'] ?? null;
    }

    /**
     * Получить от id оплаты во внешней системе.
     */
    public function externalPaymentId(Payment $payment): ?string
    {
        return $payment->data['externalPaymentId'] ?? null;
    }

    /**
     * Обработать данные от платёжной ситсемы о совершении платежа.
     * @param array $data
     * @throws \Exception
     */
    public function handlePushPayment(array $data): void
    {
        $this->logger->info('Handle external payment');
        $notification = $data['event'] === NotificationEventType::PAYMENT_SUCCEEDED
            ? new NotificationSucceeded($data)
            : new NotificationWaitingForCapture($data);

        $this->logger->info('External event data', $notification->jsonSerialize());

        $payment = $this->yandexService->getPaymentInfo($notification->getObject()->getId());
        $metadata = $payment->metadata ? $payment->metadata->toArray() : null;

        $this->logger->info('Metadata', $metadata);

        if ($metadata && $metadata['source'] && $metadata['source'] !== config('app.url')) {
            $this->logger->info('Redirect payment', [
                'proxy' => $metadata['source'],
            ]);
            $client = new GuzzleClient();
            $client->request('POST', $metadata['source'] . route('handler.yandexPayment', [], false), [
                'form_params' => $data,
            ]);
        } else {
            $this->logger->info('Process payment', [
                'external_payment_id' => $payment->id,
                'status' => $payment->getStatus(),
            ]);
            /** @var Payment $localPayment */
            $localPayment = Payment::query()
                ->where('data->externalPaymentId', $payment->id)
                ->firstOrFail();

            switch ($payment->getStatus()) {
                case PaymentStatus::WAITING_FOR_CAPTURE:
                    $this->logger->info('Set holded', ['local_payment_id' => $localPayment->id]);
                    $localPayment->status = Models\Payment\PaymentStatus::HOLD;
                    $localPayment->yandex_expires_at = $notification->getObject()->getExpiresAt();
                    $localPayment->save();
                    break;
                case PaymentStatus::SUCCEEDED:
                    $this->logger->info('Set paid', ['local_payment_id' => $localPayment->id]);
                    $localPayment->status = Models\Payment\PaymentStatus::PAID;
                    $localPayment->payed_at = Carbon::now();
                    $localPayment->save();
                    break;
                case PaymentStatus::CANCELED:
                    $this->logger->info('Set canceled', ['local_payment_id' => $localPayment->id]);
                    $localPayment->status = Models\Payment\PaymentStatus::TIMEOUT;
                    $localPayment->save();
                    break;
            }
        }
    }

    /**
     * Время в часах, в течение которого можно совершить платёж после его создания.
     * Если за это время платёж не совершён - заказ отменяется.
     * Если не указано, то время бесконечно.
     */
    public function duration(): ?int
    {
        return 1;
    }

    /**
     * @param $amount
     * @throws \Exception
     */
    public function commitHoldedPayment(Payment $localPayment, $amount)
    {
        $captureData = [
            'amount' => [
                'value' => $amount,
                'currency' => self::CURRENCY_RUB,
            ],
            'receipt' => [
                'tax_system_code' => '3',
                'phone' => $localPayment->order->customerPhone(),
                'items' => $this->generateItems($localPayment->order),
            ],
        ];
        /*$email = $localPayment->order->customerEmail();
        if ($email) {
            $captureData['receipt']['email'] = $email;
        }*/
        $this->logger->info('Start commit holded payment', $captureData);
        $response = $this->yandexService->capturePayment(
            $captureData,
            $this->externalPaymentId($localPayment),
            uniqid('', true)
        );

        $this->logger->info('Commit result', $response->jsonSerialize());
    }

    /**
     * Get receipt items from order
     */
    protected function generateItems(Order $order): array
    {
        $items = [];
        $certificatesDiscount = 0;
        if (!empty($order->certificates)) {
            foreach ($order->certificates as $certificate) {
                $certificatesDiscount += $certificate['amount'];
            }
        }

        $merchantIds = $order->basket->items->where('type', Basket::TYPE_PRODUCT)->pluck('product.merchant_id');
        if (!empty($merchantIds)) {
            $merchantIds = $merchantIds->toArray();
            $merchantQuery = $this->merchantService->newQuery()
                ->addFields(MerchantDto::entity(), 'id')
                ->include('vats')
                ->setFilter('id', $merchantIds);
            $merchants = $this->merchantService->merchants($merchantQuery)->keyBy('id');
        }

        $productOfferIds = $order->basket->items->where('type', Basket::TYPE_PRODUCT)->pluck('offer_id');

        if ($productOfferIds) {
            $productOfferQuery = $this->offerService->newQuery();
            $productOfferQuery->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
                ->include('product')
                ->setFilter('id', $productOfferIds->toArray());
            $offers = $this->offerService->offers($productOfferQuery)->keyBy('id');
        }

        foreach ($order->basket->items as $item) {
            $paymentMode = 'full_payment';

            $itemValue = $item->price / $item->qty;
            if (($certificatesDiscount > 0) && ($itemValue > 1)) {
                $discountPrice = $itemValue - 1;
                if ($discountPrice > $certificatesDiscount) {
                    $itemValue -= $certificatesDiscount;
                    $certificatesDiscount = 0;
                    $paymentMode = 'partial_prepayment';
                } else {
                    $itemValue -= $discountPrice;
                    $certificatesDiscount -= $discountPrice;
                    $paymentMode = 'full_prepayment';
                }
            }

            $paymentSubject = 'commodity';
            $vatCode = 1;
            switch ($item->type) {
                case Basket::TYPE_MASTER:
                    $paymentSubject = 'service';

                    if (isset($offers, $offers[$item->offer_id], $merchants)) {
                        $vatCode = $this->getVatCode($offers[$item->offer_id], $merchants[$offers[$item->offer_id]['merchant_id']]);
                    }
                    break;
                case Basket::TYPE_PRODUCT:
                    $paymentSubject = 'commodity';

                    if (isset($offers, $offers[$item->offer_id], $merchants)) {
                        $vatCode = $this->getVatCode($offers[$item->offer_id], $merchants[$offers[$item->offer_id]['merchant_id']]);
                    }
                    break;
                case Basket::TYPE_CERTIFICATE:
                    $paymentSubject = 'payment';
                    $paymentMode = 'advance';
                    break;
            }

            $items[] = [
                'description' => $item->name,
                'quantity' => $item->qty,
                'amount' => [
                    'value' => number_format($itemValue, 2, '.', ''),
                    'currency' => self::CURRENCY_RUB,
                ],
                'vat_code' => $vatCode,
                'payment_mode' => $paymentMode,
                'payment_subject' => $paymentSubject,
            ];
        }
        if ((float) $order->delivery_price > 0) {
            $paymentMode = 'full_payment';
            $deliveryPrice = $order->delivery_price;
            if (($certificatesDiscount > 0) && ($deliveryPrice >= $certificatesDiscount)) {
                $deliveryPrice -= $certificatesDiscount;
                $paymentMode = $deliveryPrice > $certificatesDiscount ? 'partial_prepayment' : 'full_prepayment';
            }
            $items[] = [
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => number_format($deliveryPrice, 2, '.', ''),
                    'currency' => self::CURRENCY_RUB,
                ],
                'vat_code' => 1,
                'payment_mode' => $paymentMode,
                'payment_subject' => 'service',
            ];
        }

        return $items;
    }

    private function getVatCode(object $offerInfo, object $merchant): ?int
    {
        $vatCode = 1;
        $vatValue = null;
        $itemMerchantVats = $merchant['vats'];
        usort($itemMerchantVats, static function ($a, $b) {
            return $b['type'] - $a['type'];
        });
        foreach ($itemMerchantVats as $vat) {
            switch ($vat['type']) {
                case VatDto::TYPE_GLOBAL:
                    break;
                case VatDto::TYPE_MERCHANT:
                    $vatValue = $vat['value'];
                    break 2;
                case VatDto::TYPE_BRAND:
                    if ($offerInfo['product']['brand_id'] === $vat['brand_id']) {
                        $vatValue = $vat['value'];
                        break 2;
                    }
                    break;
                case VatDto::TYPE_CATEGORY:
                    if ($offerInfo['product']['category_id'] === $vat['category_id']) {
                        $vatValue = $vat['value'];
                        break 2;
                    }
                    break;
                case VatDto::TYPE_SKU:
                    if ($offerInfo['product_id'] === $vat['product_id']) {
                        $vatValue = $vat['value'];
                        break 2;
                    }
                    break;
            }
        }

        if (isset($vatValue)) {
            switch ($vatValue) {
                case 0:
                    $vatCode = 2;
                    break;
                case 10:
                    $vatCode = 3;
                    break;
                case 20:
                    $vatCode = 4;
                    break;
            }
        }

        return $vatCode;
    }
}
