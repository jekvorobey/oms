<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Receipt;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequest;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\CreatePostReceiptRequestBuilder;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Receipt\SettlementType;
use YooKassa\Model\ReceiptCustomer;
use YooKassa\Model\ReceiptItem;
use YooKassa\Model\ReceiptType;

class RefundReceiptData extends ReceiptData
{
    /**
     * Сформировать чек возврата всех позиций
     */
    public function getRefundReceiptAllItemsData(Order $order, string $paymentId): CreatePostReceiptRequestBuilder
    {
        $builder = $this->getBaseCreateReceiptBuilder($order, $paymentId);

        $builder->setItems($this->getReceiptItemsForFullRefund($order));
        $builder->setSettlements($this->getSettlements($order));

        return $builder;
    }

    /**
     * Сформировать чек частичного возврата
     */
    public function getRefundReceiptPartiallyData(
        string $paymentId,
        OrderReturn $orderReturn
    ): CreatePostReceiptRequestBuilder {
        $order = $orderReturn->order;

        $builder = $this->getBaseCreateReceiptBuilder($order, $paymentId);

        $builder->setItems($this->getReceiptItemsForPartiallyRefund($orderReturn));
        $builder->setSettlements($this->getSettlements($order, $orderReturn));

        return $builder;
    }

    protected function getBaseCreateReceiptBuilder(Order $order, string $paymentId): CreatePostReceiptRequestBuilder
    {
        return CreatePostReceiptRequest::builder()
            ->setType(ReceiptType::REFUND)
            ->setPaymentId($paymentId)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true);
    }

    /**
     * Сформировать позиции для возврата всех позиций заказа
     */
    protected function getReceiptItemsForFullRefund(Order $order): array
    {
        $receiptItems = [];

        $offerIds = $order->basket->items
            ->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])
            ->pluck('offer_id')
            ->toArray();
        [$offers, $merchants] = $this->loadOffersAndMerchants($offerIds, $order);

        foreach ($order->basket->items as $item) {
            $offer = $offers[$item->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;
            $qtyToRefund = $item->qty + $item->qty_canceled;
            $amountToRefund = $item->price / $item->qty;

            $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant, $qtyToRefund, $amountToRefund);
            $receiptItems[] = new ReceiptItem($receiptItemInfo);
        }
        if ((float) $order->delivery_price > 0) {
            $receiptItems[] = $this->getDeliveryReceiptItem($order->delivery_price);
        }

        return $receiptItems;
    }

    /**
     * Get receipt items from order
     */
    protected function getReceiptItemsForPartiallyRefund(OrderReturn $orderReturn): array
    {
        $receiptItems = [];
        $order = $orderReturn->order;

        $offerIds = $orderReturn->items
            ->whereIn('type', [Basket::TYPE_PRODUCT, Basket::TYPE_MASTER])
            ->pluck('offer_id')
            ->toArray();
        [$offers, $merchants] = $this->loadOffersAndMerchants($offerIds, $order);

        foreach ($orderReturn->items as $item) {
            $basketItem = $item->basketItem;
            $basketItem->price = $item->price ?: $basketItem->price;
            $basketItem->qty = $item->qty ?: $basketItem->qty;
            $offer = $offers[$basketItem->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;

            $receiptItemInfo = $this->getReceiptItemInfo($basketItem, $offer, $merchant, $basketItem->qty, $basketItem->price);
            $receiptItems[] = new ReceiptItem($receiptItemInfo);
        }

        if ($orderReturn->is_delivery) {
            $receiptItems[] = $this->getDeliveryReceiptItem($orderReturn->price);
        }

        return $receiptItems;
    }

    /**
     * Добавление признаков оплаты (чек зачета предоплаты и обычная оплата)
     */
    private function getSettlements(Order $order, ?OrderReturn $orderReturn = null): array
    {
        $settlements = [];

        $refundSum = $orderReturn->price ?? 0.0;

        if ($order->spent_certificate > 0) {
            $priceToReturn = $orderReturn->price ?? $order->price;

            $restCashlessReturnPrice = max(0, $order->remaining_price - $order->spent_certificate);
            $returnPrepayment = max(0, $priceToReturn - $restCashlessReturnPrice);
            $returnCashless = max(0, $priceToReturn - $returnPrepayment);

            if ($returnCashless > 0) {
                $settlements[] = [
                    'type' => SettlementType::CASHLESS,
                    'amount' => [
                        'value' => $returnCashless,
                        'currency' => CurrencyCode::RUB,
                    ],
                ];
            }

            $settlements[] = [
                'type' => SettlementType::PREPAYMENT,
                'amount' => [
                    'value' => $returnPrepayment,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        } else {
            $settlements[] = [
                'type' => SettlementType::CASHLESS,
                'amount' => [
                    'value' => $refundSum > 0 ? $refundSum : $order->price,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        }

        return $settlements;
    }
}
