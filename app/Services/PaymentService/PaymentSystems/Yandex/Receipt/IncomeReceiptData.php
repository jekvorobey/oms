<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Receipt;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Models\Order\OrderReturnItem;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequest;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequestBuilder;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Receipt\SettlementType;
use YooKassa\Model\ReceiptCustomer;
use YooKassa\Model\ReceiptItem;
use YooKassa\Model\ReceiptType;

class IncomeReceiptData extends ReceiptData
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
        $builder->setItems($this->getReceiptItems($order));
        $builder->setSettlements($this->getSettlements($order));

        return $builder;
    }

    /**
     * Получение позиций заказа для чека
     */
    protected function getReceiptItems(Order $order): array
    {
        $receiptItems = [];
        $deliveryForReturn = OrderReturn::query()
            ->where('order_id', $order->id)
            ->where('is_delivery', true)
            ->exists();

        $offerIds = $order->basket->items
            ->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])
            ->pluck('offer_id')
            ->toArray();
        [$offers, $merchants] = $this->loadOffersAndMerchants($offerIds, $order);

        foreach ($order->basket->items as $item) {
            if ($item->isCanceled()) {
                continue;
            }

            $offer = $offers[$item->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;

            $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant);
            $receiptItems[] = new ReceiptItem($receiptItemInfo);
        }

        if ((float) $order->delivery_price > 0 && !$deliveryForReturn) {
            $receiptItems[] = $this->getDeliveryReceiptItem($order->delivery_price);
        }

        return $receiptItems;
    }

    /**
     * Формирование признаков оплаты (чек зачета предоплаты и обычная оплата)
     */
    private function getSettlements(Order $order): array
    {
        $settlements = [];

        if ($order->spent_certificate > 0) {
            $remainingCashlessPrice = max(0, $order->cashless_price - $order->done_return_sum);

            $returnedPrepayment = max(0, $order->done_return_sum - $order->cashless_price);
            $remainingPrepaymentPrice = max(0, $order->spent_certificate - $returnedPrepayment);

            if ($remainingCashlessPrice > 0) {
                $settlements[] = [
                    'type' => SettlementType::CASHLESS,
                    'amount' => [
                        'value' => $remainingCashlessPrice,
                        'currency' => CurrencyCode::RUB,
                    ],
                ];
            }

            $settlements[] = [
                'type' => SettlementType::PREPAYMENT,
                'amount' => [
                    'value' => $remainingPrepaymentPrice,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        } else {
            $settlements[] = [
                'type' => SettlementType::CASHLESS,
                'amount' => [
                    'value' => $order->remaining_price,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        }

        return $settlements;
    }
}
