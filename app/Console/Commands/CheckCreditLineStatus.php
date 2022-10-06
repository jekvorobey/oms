<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Services\CreditService\CreditService;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentService;
use IBT\CreditLine\Enum\OrderStatusEnum;
use Illuminate\Console\Command;
use Throwable;

/**
 * Class CheckCreditLineStatus
 * @package App\Console\Commands
 */
class CheckCreditLineStatus extends Command
{
    protected $signature = 'creditline:check';
    protected $description = 'Проверить статусы кредитных договоров по кредитным заказам и актуализация статуса заказа';

    public function handle()
    {
        Order::query()
            ->where('payment_method_id', PaymentMethod::CREDITPAID)
            ->where('is_canceled', 0)
            ->whereNotIn('status', [OrderStatus::DONE])
            ->each(function (Order $order) {
                $this->checkCreditOrder($order);
            });
    }

    private function checkCreditOrder(Order $order): void
    {
        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);

        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        try {
            $creditService = new CreditService();
            $checkStatus = $creditService->getCreditOrder($order);
        } catch (Throwable $exception) {
            report($exception);
            return;
        }

        if (!$checkStatus) {
            return;
        }

        $creditStatusId = $order->credit_status_id;
        $creditDiscount = $order->credit_discount;
        $creditStatusIdNew = $checkStatus['statusId'];
        $creditDiscountNew = $checkStatus['discount'];
        $isUpdateOrder = false;

        // Обновить статус кредитного договора, если он отличается от текущего
        if ($creditStatusId !== $creditStatusIdNew) {
            $order->credit_status_id = $creditStatusIdNew;
            $isUpdateOrder = true;
        }

        // Обновить процент скидки
        if ($creditDiscount !== (float) $creditDiscountNew) {
            $order->credit_discount = (float) $creditDiscountNew;
            $isUpdateOrder = true;
        }

        // Отметка об оплате заказа, если кредитная заявка подписана
        if ($order->payment_status !== PaymentStatus::PAID && $creditDiscountNew == OrderStatusEnum::CREDIT_ORDER_STATUS_SIGNED) {
            $order->payment_status = PaymentStatus::PAID;
            $order->payment_status_at = now();
            $isUpdateOrder = true;
        }

        if ($isUpdateOrder === true) {
            $order->save();
        }

        // Отмена заказа, у которого не принята заявка на кредит и который не отменен ранее
        if (
            !$order->is_canceled
            && in_array(
                $creditStatusIdNew,
                [OrderStatusEnum::CREDIT_ORDER_STATUS_REFUSED, OrderStatusEnum::CREDIT_ORDER_STATUS_ANNULED],
                true
            )
        ) {
            try {
                $orderService->cancel($order, CreditService::ORDER_RETURN_REASON_ID);
            } catch (Throwable $exception) {
                report($exception);
            }

            return;
        }

        //Формирование кассового чека с расчетом "Предоплата"
        //если заказ в статусе "Передан в доставку"
        //и статус кредитной заявки сменился на "Cached"
        //и нет ранее сформированных чеков
        if (
            $order->status === OrderStatus::TRANSFERRED_TO_DELIVERY
            && $creditStatusId !== $creditStatusIdNew
            && $creditStatusIdNew === OrderStatusEnum::CREDIT_ORDER_STATUS_CASHED
            && $order->payments->isEmpty()
        ) {
            $payment = $creditService->createCreditPayment($order, CreditService::CREDIT_PAYMENT_RECEIPT_TYPE_PREPAYMENT);
            if ($payment instanceof Payment) {
                $creditPaymentReceipt = $paymentService->createCreditPrepaymentReceipt($payment);
                if ($creditPaymentReceipt) {
                    $paymentService->sendCreditPaymentReceipt($payment, $creditPaymentReceipt);
                }
            }

            return;
        }

        //Формирование кассового чека с расчетом "В кредит"
        //если заказ в статусе "Передан в доставку"
        //и статусы кредитной заявки сменились на ???
        //и нет ранее сформированных чеков
        if (
            $order->status === OrderStatus::TRANSFERRED_TO_DELIVERY
            && $creditStatusId !== $creditStatusIdNew
            && in_array(
                $creditStatusIdNew,
                [OrderStatusEnum::CREDIT_ORDER_STATUS_ACCEPTED, OrderStatusEnum::CREDIT_ORDER_STATUS_SIGNED],
                true
            )
            && $order->payments->isEmpty()
        ) {
            $payment = $creditService->createCreditPayment($order, CreditService::CREDIT_PAYMENT_RECEIPT_TYPE_ON_CREDIT);
            if ($payment instanceof Payment) {
                $creditPaymentReceipt = $paymentService->createCreditReceipt($payment);
                if ($creditPaymentReceipt) {
                    $paymentService->sendCreditPaymentReceipt($payment, $creditPaymentReceipt);
                }
            }

            return;
        }

        //Формирование кассового чека с расчетом "Погашение кредита"
        //если статус кредитной заявки сменился на "Cached"
        //и заказ еще в пути
        //и ранее был выбит чек с расчетом "В кредит"
        if (
            in_array($order->status, [OrderStatus::TRANSFERRED_TO_DELIVERY, OrderStatus::DELIVERING, OrderStatus::READY_FOR_RECIPIENT], true)
            && $creditStatusId !== $creditStatusIdNew
            && $creditStatusIdNew === OrderStatusEnum::CREDIT_ORDER_STATUS_CASHED
            && $order->payments->isNotEmpty()
        ) {
            $payment = $order->payments()->first();
            if ($payment instanceof Payment) {
                $creditPaymentReceipt = $paymentService->createCreditPaymentReceipt($payment);
                if ($creditPaymentReceipt) {
                    $paymentService->sendCreditPaymentReceipt($payment, $creditPaymentReceipt);
                }
            }
        }
    }
}

