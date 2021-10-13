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
    private function getSettlements(Order $order): array
    {
        $settlements = [];

        if ($order->spent_certificate > 0) {
            $returnPrepayment = 0;
            $returnCashless = 0;

            if ($order->done_return_sum > 0) {
                $restReturnPrice = $order->price - $order->done_return_sum;
                $restCashlessReturnPrice = max(0, $restReturnPrice - $order->spent_certificate);
                $returnPrepayment = max(0, $order->done_return_sum - $restCashlessReturnPrice);
                $returnCashless = max(0, $order->done_return_sum - $returnPrepayment);
            }

            if ($order->cashless_price > 0) {
                $settlements[] = [
                    'type' => SettlementType::CASHLESS,
                    'amount' => [
                        'value' => $order->cashless_price - $returnCashless,
                        'currency' => CurrencyCode::RUB,
                    ],
                ];
            }

            $settlements[] = [
                'type' => SettlementType::PREPAYMENT,
                'amount' => [
                    'value' => $order->spent_certificate - $returnPrepayment,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        } else {
            $settlements[] = [
                'type' => SettlementType::CASHLESS,
                'amount' => [
                    'value' => $order->price - $order->done_return_sum,
                    'currency' => CurrencyCode::RUB,
                ],
            ];
        }

        return $settlements;
    }
}
