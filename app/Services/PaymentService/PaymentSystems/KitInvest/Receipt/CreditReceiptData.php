<?php

namespace App\Services\PaymentService\PaymentSystems\KitInvest\Receipt;

use App\Models\Basket\Basket;
use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;

class CreditReceiptData extends ReceiptData
{
    public ?string $paymentMode = null;
    public ?string $settlementType = null;
    private ?float $amountPayment = null;

    /** @return static */
    public function setPaymentMode(string $paymentMode): self
    {
        $this->paymentMode = $paymentMode;

        return $this;
    }

    /** @return static */
    public function setSettlementType(string $settlementType): self
    {
        $this->settlementType = $settlementType;

        return $this;
    }

    public function getReceiptData(Payment $payment): CreatePostReceiptRequestBuilder
    {
        $order = $payment->order;

        $builder = CreatePostReceiptRequest::builder();
        $builder
            ->setType(ReceiptType::PAYMENT)
            ->setCustomer(new ReceiptCustomer([
                'phone' => $order->customerPhone(),
            ]))
            ->setSend(true)
            ->setPaymentId($payment->external_payment_id);

        $builder->setItems($this->getReceiptItems($payment));

        $builder->setSettlements($this->getSettlements());

        return $builder;
    }

    /**
     * Получение позиций заказа для чека
     */
    protected function getReceiptItems(Payment $payment): array
    {
        $order = $payment->order;

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

            $quantity = $item->qty;
            if ((float) $order->credit_discount > 0 && (float) $order->credit_discount < 100) {
                $price = round($item->unit_price * (100 - (float) $order->credit_discount), 2);
            } else {
                $price = $item->unit_price;
            }

            if ($quantity && $price) {
                $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant, $quantity, $price, $this->paymentMode);
                $this->amountPayment += $price * $quantity;

                $receiptItems[] = new ReceiptItem($receiptItemInfo);
            }
        }

        if ((float) $order->delivery_price > 0 && !$deliveryForReturn) {
            if ((float) $order->credit_discount > 0 && (float) $order->credit_discount < 100) {
                $deliveryPrice = round($order->delivery_price * (100 - (float) $order->credit_discount), 2);
            } else {
                $deliveryPrice = $order->delivery_price;
            }

            if ($deliveryPrice) {
                $receiptItems[] = $this->getDeliveryReceiptItem($deliveryPrice, $this->paymentMode);
                $this->amountPayment += $deliveryPrice;
            }
        }

        return $receiptItems;
    }

    /**
     * Формирование признаков оплаты (в кредит, Погашение кредита)
     */
    private function getSettlements(): array
    {
        $settlements = [];
        $settlements[] = [
            'type' => $this->settlementType,
            'amount' => [
                'value' => $this->amountPayment,
                'currency' => CurrencyCode::RUB,
            ],
        ];

        return $settlements;
    }
}
