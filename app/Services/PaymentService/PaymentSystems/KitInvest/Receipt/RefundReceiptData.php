<?php

namespace App\Services\PaymentService\PaymentSystems\KitInvest\Receipt;

use App\Models\Basket\Basket;
use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;
use IBT\KitInvest\Enum\ReceiptEnum;
use IBT\KitInvest\Models\CheckModel;
use IBT\KitInvest\Models\PayModel;
use IBT\KitInvest\Models\SubjectModel;
use Pim\Core\PimException;

class RefundReceiptData extends ReceiptData
{
    public ?int $payAttribute = ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_CREDIT;
    private ?float $amountPayment = null;

    /** @return $this */
    public function setPayAttribute(int $payAttribute): self
    {
        $this->payAttribute = $payAttribute;

        return $this;
    }

    public function getReceiptData(Payment $payment, ?bool $isMerchant = true): array
    {
        $order = $payment->order;

        $items = $this->getReceiptItems($payment, $isMerchant);

        $pays = new PayModel();
        switch ($this->payAttribute) {
            case ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PREPAYMENT:
                $pays->setPrepaymentSum($this->amountPayment * 100);
                break;
            case ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_FULL_PAYMENT:
                $pays->setPostpaySum($this->amountPayment * 100);
                break;
            case ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_CREDIT:
                $pays->setPostpaySum($this->amountPayment * 100);
                break;
            case ReceiptEnum::RECEIPT_SUBJECT_PAY_ATTRIBUTE_CREDIT_PAYMENT:
                $pays->setEMoneySum($this->amountPayment * 100);
                break;
            default:
                $pays->setPostpaySum($this->amountPayment * 100);
        }

        $receipt = new CheckModel();
        $receipt
            ->setCheckId($order->number . '/' . $payment->id)
            ->setTaxSystemType(ReceiptEnum::RECEIPT_TAX_SYSTEM_USN_COST) //4 - УСН доход-расход
            ->setCalculationType(ReceiptEnum::RECEIPT_TYPE_RETURN_INCOME) //2 Признак расчета - возврат прихода
            ->setSum($this->amountPayment * 100) //Сумма чека в копейках
            ->setPay($pays)
            ->setSubjects($items);

        if ($order->customerEmail()) {
            $receipt->setEmail($order->customerEmail());
        } else if ($order->customerPhone()) {
            $receipt->setPhone($order->customerPhone());
        }

        return $receipt->toArray();
    }

    /**
     * Получение позиций заказа для чека
     * @throws PimException
     */
    protected function getReceiptItems(Payment $payment, ?bool $isMerchant = true): array
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

        if (!$isMerchant) {
            $merchants = [];
        }

        foreach ($order->basket->items as $item) {
            if ($item->isCanceled()) {
                continue;
            }

            $offer = $offers[$item->offer_id] ?? null;
            $merchantId = $offer['merchant_id'] ?? null;
            $merchant = $merchants[$merchantId] ?? null;

            $quantity = $item->qty;
            $price = $item->unit_price;

            if ($quantity && $price) {
                $receiptItemInfo = $this->getReceiptItemInfo($item, $offer, $merchant, $quantity, $price, $this->payAttribute);
                $this->amountPayment += $price * $quantity;

                $receiptItems[] = new SubjectModel($receiptItemInfo);
            }
        }

        if ($order->delivery_price > 0 && !$deliveryForReturn) {
            $deliveryPrice = $order->delivery_price;

            if ($deliveryPrice) {
                $receiptItems[] = $this->getDeliveryReceiptItem($deliveryPrice, $this->payAttribute);
                $this->amountPayment += $deliveryPrice;
            }
        }

        return $receiptItems;
    }
}
