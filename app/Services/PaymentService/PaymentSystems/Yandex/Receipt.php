<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Yandex;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use Illuminate\Support\Facades\Log;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Dto\VatDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Monolog\Logger;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;
use YooKassa\Client;
use YooKassa\Common\AbstractPaymentRequestBuilder;
use YooKassa\Model\Receipt\PaymentMode;
use YooKassa\Model\Receipt\PaymentSubject;

class Receipt
{
    public const VAT_CODE_DEFAULT = 1;
    public const VAT_CODE_0_PERCENT = 2;
    public const VAT_CODE_10_PERCENT = 3;
    public const VAT_CODE_20_PERCENT = 4;

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
     * Get receipt items from order
     */
    protected function addReceiptItems(Order $order, AbstractPaymentRequestBuilder $builder): void
    {
        $certificatesDiscount = 0;

        if ($order->spent_certificate > 0) {
            $certificatesDiscount = $order->spent_certificate;
        }
        $itemsForReturn = OrderReturnItem::query()
            ->whereIn('basket_item_id', $order->basket->items->pluck('id'))
            ->pluck('basket_item_id')
            ->toArray();
        $deliveryForReturn = OrderReturn::query()
            ->where('order_id', $order->id)
            ->where('is_delivery', true)
            ->exists();

        $merchants = collect();
        $merchantIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('product.merchant_id');
        if (!empty($merchantIds)) {
            $merchantIds = $merchantIds->toArray();
            $merchantQuery = $this->merchantService->newQuery()
                ->addFields(MerchantDto::entity(), 'id')
                ->include('vats')
                ->setFilter('id', $merchantIds);
            $merchants = $this->merchantService->merchants($merchantQuery)->keyBy('id');
        }

        $offers = collect();
        $productOfferIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_PRODUCT])->pluck('offer_id');
        if ($productOfferIds) {
            $productOfferQuery = $this->offerService->newQuery();
            $productOfferQuery->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
                ->include('product')
                ->setFilter('id', $productOfferIds->toArray());
            $offers = $this->offerService->offers($productOfferQuery)->keyBy('id');
        }

        foreach ($order->basket->items as $item) {
            if (!in_array($item->id, $itemsForReturn)) {
                //$paymentMode = self::PAYMENT_MODE_FULL_PAYMENT; //TODO::Закомментировано до реализации IBT-433

                $itemValue = $item->price / $item->qty;
                if (($certificatesDiscount > 0) && ($itemValue > 1)) {
                    $discountPrice = $itemValue - 1;
                    if ($discountPrice > $certificatesDiscount) {
                        $itemValue -= $certificatesDiscount;
                        $certificatesDiscount = 0;
                        //$paymentMode = self::PAYMENT_MODE_PARTIAL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
                    } else {
                        $itemValue -= $discountPrice;
                        $certificatesDiscount -= $discountPrice;
                        $paymentMode = PaymentMode::FULL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
                    }
                }
                $offer = $offers[$item->offer_id] ?? null;
                $merchantId = $offer['merchant_id'] ?? null;
                $merchant = $merchants[$merchantId] ?? null;

                $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant);
                $builder->addReceiptItem(
                    $item->name,
                    number_format($itemValue, 2, '.', ''),
                    $item->qty,
                    $receiptItemInfo['vat_code'],
                    $receiptItemInfo['payment_mode'],
                    $receiptItemInfo['payment_subject']
                );
            }
        }
        if ((float) $order->delivery_price > 0 && !$deliveryForReturn) {
            $paymentMode = PaymentMode::FULL_PAYMENT;
            $deliveryPrice = $order->delivery_price;
            if (($certificatesDiscount > 0) && ($deliveryPrice >= $certificatesDiscount)) {
                $deliveryPrice -= $certificatesDiscount;
//                $paymentMode = $deliveryPrice > $certificatesDiscount ? self::PAYMENT_MODE_PARTIAL_PREPAYMENT : self::PAYMENT_MODE_FULL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
            }
            $builder->addReceiptShipping(
                'Доставка',
                number_format($deliveryPrice, 2, '.', ''),
                self::VAT_CODE_DEFAULT,
                $paymentMode,
                PaymentSubject::SERVICE,
            );
        }
    }

    private function getReceiptItemInfo(BasketItem $item, ?object $offerInfo, ?object $merchant): array
    {
        $paymentMode = PaymentMode::FULL_PAYMENT;
        $paymentSubject = PaymentSubject::COMMODITY;
        $vatCode = self::VAT_CODE_DEFAULT;
        switch ($item->type) {
            case Basket::TYPE_MASTER:
                $paymentSubject = PaymentSubject::SERVICE;

                if (isset($offerInfo, $merchant)) {
                    $vatCode = $this->getVatCode($offerInfo, $merchant);
                }
                break;
            case Basket::TYPE_PRODUCT:
                $paymentSubject = PaymentSubject::COMMODITY;

                if (isset($offerInfo, $merchant)) {
                    $vatCode = $this->getVatCode($offerInfo, $merchant);
                }
                break;
            case Basket::TYPE_CERTIFICATE:
                $paymentSubject = PaymentSubject::PAYMENT;
                $paymentMode = PaymentMode::ADVANCE;
                break;
        }

        return [
            'vat_code' => $vatCode,
            'payment_mode' => $paymentMode,
            'payment_subject' => $paymentSubject,
        ];
    }

    private function getVatCode(object $offerInfo, object $merchant): ?int
    {
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
                0 => self::VAT_CODE_0_PERCENT,
                10 => self::VAT_CODE_10_PERCENT,
                20 => self::VAT_CODE_20_PERCENT,
            ][$vatValue] ?? self::VAT_CODE_DEFAULT;
    }

    private function getVatValue(array $vat, object $offerInfo): ?int
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
}
