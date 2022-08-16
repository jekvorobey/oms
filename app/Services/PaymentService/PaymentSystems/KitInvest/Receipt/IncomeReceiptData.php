<?php

namespace App\Services\PaymentService\PaymentSystems\KitInvest\Receipt;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderReturn;
use IBT\KitInvest\Models\CheckModel;
use IBT\KitInvest\Models\SubjectModel;

/**
 * Class IncomeReceiptData
 * @package App\Services\PaymentService\PaymentSystems\KitInvest\Receipt
 */
class IncomeReceiptData extends ReceiptData
{
    public function getReceiptData(Order $order, string $paymentId): CheckModel
    {

        $check = new CheckModel();
        $check
            ->setCheckId($paymentId)
            ->setPhone($order->customerPhone())
            ->setSubjects($this->getReceiptItems($order));

        return $check;
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

            $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant, $item->qty);
            $receiptItems[] = new SubjectModel($receiptItemInfo);
        }

        if ((float) $order->delivery_price > 0 && !$deliveryForReturn) {
            $receiptItems[] = $this->getDeliveryReceiptItem($order->delivery_price);
        }

        return $receiptItems;
    }
}
