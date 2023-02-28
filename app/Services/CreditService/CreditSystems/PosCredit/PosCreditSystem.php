<?php

namespace App\Services\CreditService\CreditSystems\PosCredit;

use App\Core\Order\OrderWriter;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use App\Services\CreditService\CreditService;
use App\Services\CreditService\CreditSystems\CreditSystemInterface;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentService;
use IBT\CreditLine\Enum\OrderStatusEnum;
use IBT\PosCredit\PosCredit;
use Throwable;

/**
 * Class PosCreditSystem
 * @package App\Services\CreditService\CreditSystems\PosCredit
 */
class PosCreditSystem implements CreditSystemInterface
{
    public const CREDIT_ORDER_ERROR_NOT_FIND = -5;

    private PosCredit $posCreditService;

    /**
     * CreditLineSystem constructor.
     */
    public function __construct()
    {
        $this->posCreditService = resolve(PosCredit::class);
    }

    /**
     * Обратиться к внешней системе по внешнему Id платежа
     */
    public function getCreditOrder(string $id): ?array
    {
        $creditOrder = $this->posCreditService->getOrderStatus($id);

        if ($creditOrder->getErrorCode() === self::CREDIT_ORDER_ERROR_NOT_FIND) {
            return null;
        }

        return [
            'status' => $creditOrder->getStatus(),
            'statusId' => $creditOrder->getStatusId(),
            'statusDescription' => $creditOrder->getStatusDescription(),
            'discount' => $creditOrder->getDiscount(),
            'numOrder' => $creditOrder->getNumOrder(),
            'confirm' => $creditOrder->getConfirm(),
            'initPay' => $creditOrder->getInitPay(),
            'bankCode' => $creditOrder->getBankCode(),
            'bankName' => $creditOrder->getBankName(),
        ];
    }

    /**
     * Проверить статус кредитного заказа во внешней системе  и обновить заказ
     */
    public function checkCreditOrder(Order $order): ?array
    {
        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);

        $creditOrder = $this->posCreditService->getOrderStatus($order->number);

        if ($creditOrder->getErrorCode() === self::CREDIT_ORDER_ERROR_NOT_FIND) {
            return null;
        }

        $isUpdateOrder = false;

        $creditStatusId = $order->credit_status_id;
        $creditStatusIdNew = $creditOrder->getStatusId();
        // Обновить статус кредитного договора, если он отличается от текущего
        if ($creditStatusId !== $creditStatusIdNew) {
            $order->credit_status_id = $creditStatusIdNew;
            $isUpdateOrder = true;
        }

        $creditDiscount = $order->credit_discount;
        $creditDiscountNew = $creditOrder->getDiscount();
        // Обновить процент скидки
        if ($creditDiscount !== $creditDiscountNew) {
            $order->credit_discount = $creditDiscountNew;
            $isUpdateOrder = true;
        }

        if ($isUpdateOrder === true) {
            $order->save();
        }

        // Отмена заказа, у которого отклонена заявка на кредит, который не оплачен, который не отменен ранее
        if (
            !$order->is_canceled
            && $order->payment_status !== PaymentStatus::PAID
            && in_array(
                $order->credit_status_id,
                [OrderStatusEnum::CREDIT_ORDER_STATUS_REFUSED, OrderStatusEnum::CREDIT_ORDER_STATUS_ANNULED],
                true
            )
        ) {
            try {
                $orderService->cancel($order, CreditService::ORDER_RETURN_REASON_ID);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $creditOrder->toArray();
    }

    /**
     * Формирование кассового чека с расчетом "Предоплата"
     * если заказ в статусе "Передан в доставку"
     * и статус кредитной заявки сменился на "Cached"
     * и нет ранее сформированных чеков
     */
    public function sendCreditPaymentReceiptTypePrepayment(Order $order): void
    {
        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        if (
            $order->status === OrderStatus::TRANSFERRED_TO_DELIVERY
            && $order->credit_status_id === OrderStatusEnum::CREDIT_ORDER_STATUS_CASHED
            && $order->payments->isEmpty()
        ) {
            $payment = $this->createCreditPayment($order, CreditService::CREDIT_PAYMENT_RECEIPT_TYPE_PREPAYMENT);
            if ($payment instanceof Payment) {
                $paymentService->sendIncomeFullPaymentReceipt($payment);
            }
        }
    }

    /**
     * Формирование кассового чека с расчетом "В кредит"
     * если заказ в статусе "Передан в доставку"
     * и статусы кредитной заявки сменились на ???
     * и нет ранее сформированных чеков
     */
    public function sendCreditPaymentReceiptTypeOnCredit(Order $order): ?array
    {
        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        if (
            $order->status === OrderStatus::TRANSFERRED_TO_DELIVERY
            && $order->payments->isEmpty()
            && in_array(
                $order->credit_status_id,
                [OrderStatusEnum::CREDIT_ORDER_STATUS_ACCEPTED, OrderStatusEnum::CREDIT_ORDER_STATUS_SIGNED],
                true
            )
        ) {
            $payment = $this->createCreditPayment($order, CreditService::CREDIT_PAYMENT_RECEIPT_TYPE_ON_CREDIT);
            if ($payment instanceof Payment) {
                $creditPaymentReceipt = $paymentService->sendCreditPaymentReceipt($payment, null);
            }
        }

        return $creditPaymentReceipt ?? null;
    }

    /**
     * Формирование кассового чека с расчетом "Погашение кредита"
     * если статус кредитной заявки сменился на "Cached"
     * и заказ еще в пути
     * и ранее был выбит чек с расчетом "В кредит"
     */
    public function sendCreditPaymentReceiptTypeRepaymentCredit(Order $order): ?array
    {
        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        if (
            $order->credit_status_id === OrderStatusEnum::CREDIT_ORDER_STATUS_CASHED
            && in_array($order->status, [OrderStatus::TRANSFERRED_TO_DELIVERY, OrderStatus::DELIVERING, OrderStatus::READY_FOR_RECIPIENT], true)
            && $order->payments->isNotEmpty()
        ) {
            $payment = $order->payments()->first();
            if ($payment instanceof Payment) {
                $creditPaymentReceipt = $paymentService->sendCreditPaymentReceipt($payment, null);
            }
        }

        return $creditPaymentReceipt ?? null;
    }

    public function createCreditPayment(Order $order, int $receiptType): ?Payment
    {
        $paymentSum = $order->price;

        $payment = new Payment();
        $payment->payment_method = PaymentMethod::CREDITLINE_PAID;
        $payment->payment_system = PaymentSystem::CREDIT;
        $payment->order_id = $order->id;
        $payment->sum = $paymentSum;

        $writer = new OrderWriter();
        try {
            $writer->setPayments($order, collect([$payment]));
        } catch (Throwable $exception) {
            report($exception);
            return null;
        }

        return $payment;
    }
}
