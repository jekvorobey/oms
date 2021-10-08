<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Receipt;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Services\PaymentService\PaymentSystems\Yandex\Dictionary\VatCode;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequest;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequestBuilder;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Receipt\PaymentMode;
use YooKassa\Model\Receipt\PaymentSubject;
use YooKassa\Model\Receipt\SettlementType;
use YooKassa\Model\ReceiptCustomer;
use YooKassa\Model\ReceiptItem;
use YooKassa\Model\ReceiptType;

class ReceiptData extends BaseReceiptData
{
    public function getReceiptData(Order $order, string $paymentId): CreatePostReceiptRequestBuilder
    {
        $builder = CreatePostReceiptRequest::builder();
        $builder
            ->setType(ReceiptType::PAYMENT)
            ->setPaymentId($paymentId)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true);
        $builder->setItems($this->addReceiptItems($order));
        $builder->setSettlements($this->addSettlements($order));

        return $builder;
    }

    /**
     * Получение позиций заказа для чека
     */
    protected function addReceiptItems(Order $order): array
    {
        $receiptItems = [];
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

        $merchantIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('product.merchant_id')->toArray();
        $merchants = collect();
        if (!empty($merchantIds)) {
            $merchants = $this->getMerchants($merchantIds);
        }

        $offerIds = $order->basket->items->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])->pluck('offer_id')->toArray();
        $offers = collect();
        if ($offerIds) {
            $offers = $this->getOffers($offerIds, $order);
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
                $receiptItems[] = new ReceiptItem([
                    'description' => $item->name,
                    'quantity' => $item->qty,
                    'amount' => [
                        'value' => $itemValue,
                        'currency' => CurrencyCode::RUB,
                    ],
                    'vat_code' => $receiptItemInfo['vat_code'],
                    'payment_mode' => $receiptItemInfo['payment_mode'],
                    'payment_subject' => $receiptItemInfo['payment_subject'],
//                    'agent_type' => $receiptItemInfo['agent_type'],
                ]);
            }
        }
        if ((float) $order->delivery_price > 0 && !$deliveryForReturn) {
            $paymentMode = PaymentMode::FULL_PAYMENT;
            $deliveryPrice = $order->delivery_price;
            if (($certificatesDiscount > 0) && ($deliveryPrice >= $certificatesDiscount)) {
                $deliveryPrice -= $certificatesDiscount;
//                $paymentMode = $deliveryPrice > $certificatesDiscount ? self::PAYMENT_MODE_PARTIAL_PREPAYMENT : self::PAYMENT_MODE_FULL_PREPAYMENT;//TODO::Закомментировано до реализации IBT-433
            }

            $receiptItems[] = new ReceiptItem([
                'description' => 'Доставка',
                'quantity' => 1,
                'amount' => [
                    'value' => $deliveryPrice,
                    'currency' => CurrencyCode::RUB,
                ],
                'vat_code' => VatCode::CODE_DEFAULT,
                'payment_mode' => $paymentMode,
                'payment_subject' => PaymentSubject::SERVICE,
//                'agent_type' => false,
            ]);
        }

        return $receiptItems;
    }

    /**
     * Формирование признаков оплаты (чек зачета предоплаты и обычная оплата)
     */
    private function addSettlements(Order $order): array
    {
        $settlements = [];

        $refundSum = 0;
        $refundSum += (int) OrderReturn::query()
            ->where('order_id', $order->id)
            ->sum('price');

        if ($order->spent_certificate > 0) {
            $cashlessPrice = $order->price - $order->spent_certificate - $refundSum;
            $refundSum -= $order->price - $order->spent_certificate;
            if ($cashlessPrice > 0) {
                $settlements[] = [
                    'type' => SettlementType::CASHLESS,
                    'amount' => [
                        'value' => $cashlessPrice,
                        'currency' => CurrencyCode::RUB,
                    ],
                ];
            }

            $settlements[] = [
                'type' => SettlementType::PREPAYMENT,
                'amount' => [
                    'value' => $order->spent_certificate - $refundSum,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        } else {
            $settlements[] = [
                'type' => SettlementType::CASHLESS,
                'amount' => [
                    'value' => $order->price - $refundSum,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        }

        return $settlements;
    }
}
