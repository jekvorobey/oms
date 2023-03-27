<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentReceipt;
use App\Models\Payment\PaymentStatus;
use App\Services\CreditService\CreditService;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentService;
use DateTime;
use Exception;
use IBT\CreditLine\Enum\OrderStatusEnum;
use Illuminate\Console\Command;
use Throwable;

class CheckPosCreditStatus extends Command
{
    protected $signature = 'poscredit:check';
    protected $description = 'Проверить статусы кредитных договоров по кредитным заказам PosCredit и актуализация статуса заказа';

    public function handle(): void
    {
        Order::query()
            ->where('payment_method_id', PaymentMethod::POSCREDIT_PAID)
            ->where('is_canceled', 0)
            ->whereNotIn('status', [OrderStatus::DONE])
            //->whereNotIn('payment_status', [PaymentStatus::PAID])
            ->each(function (Order $order) {
                $this->checkCreditOrder($order);
            });
    }

    /**
     * @throws Exception
     */
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

        // Не найдена кредитная заявка
        if (!$checkStatus) {

            // Отмена заказа, если
            // заказ не отменен ранее,
            // статус заказа - создан,
            // статус оплаты заказа - не оплачен,
            // заказ создан более 7-х дней
            if (
                !$order->is_canceled
                && $order->status == OrderStatus::CREATED
                && $order->payment_status !== PaymentStatus::PAID
                && (new DateTime($order->created_at))->format('Y-m-d') < (new DateTime())->modify('-7 day')->format('Y-m-d')
            ) {
                try {
                    $orderService->cancel($order, CreditService::ORDER_RETURN_REASON_ID);
                    echo "Заказ $order->number: отмена заказа - не найдена кредитная заявка \n";
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

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

        // Отметка об оплате заказа, который не отменен ранее, если кредитная заявка подписана
        if (
            !$order->is_canceled
            && $order->payment_status !== PaymentStatus::PAID
            && in_array(
                $creditStatusIdNew,
                [OrderStatusEnum::CREDIT_ORDER_STATUS_SIGNED, OrderStatusEnum::CREDIT_ORDER_STATUS_CASHED],
                true
            )
        ) {
            $order->payment_status = PaymentStatus::PAID;
            $order->payment_status_at = now();
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
                $creditStatusIdNew,
                [OrderStatusEnum::CREDIT_ORDER_STATUS_REFUSED, OrderStatusEnum::CREDIT_ORDER_STATUS_ANNULED],
                true
            )
        ) {
            try {
                $orderService->cancel($order, CreditService::ORDER_RETURN_REASON_ID);
                echo "Заказ $order->id: отмена заказа - заявка отклонена \n";
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
            $order->credit_status_id === OrderStatusEnum::CREDIT_ORDER_STATUS_CASHED
            //&& $order->status === OrderStatus::TRANSFERRED_TO_DELIVERY
            //&& $creditStatusId !== $creditStatusIdNew
            && $order->payments->isEmpty()
        ) {
            $payment = $creditService->createCreditPayment($order, CreditService::CREDIT_PAYMENT_RECEIPT_TYPE_PREPAYMENT);
            if ($payment instanceof Payment) {
                $creditPaymentReceipt = $paymentService->createCreditPrepaymentReceipt($payment);
                if ($creditPaymentReceipt) {
                    $paymentService->sendCreditPaymentReceipt($payment, $creditPaymentReceipt, PaymentReceipt::TYPE_PREPAYMENT);
                    echo "Заказ $order->id: создана оплата $payment->id и кассовый чек с расчетом 'Предоплата' \n";
                }
            }

            return;
        }

        //Формирование кассового чека с расчетом "В кредит"
        //если заказ в статусе "Передан в доставку"
        //и статусы кредитной заявки сменились на ???
        //и нет ранее сформированных чеков
        if (
            in_array(
                $order->credit_status_id,
                [OrderStatusEnum::CREDIT_ORDER_STATUS_ACCEPTED, OrderStatusEnum::CREDIT_ORDER_STATUS_SIGNED],
                true
            )
            //&& $order->status === OrderStatus::TRANSFERRED_TO_DELIVERY
            //&& $creditStatusId !== $creditStatusIdNew
            && $order->payments->isEmpty()
        ) {
            $payment = $creditService->createCreditPayment($order, CreditService::CREDIT_PAYMENT_RECEIPT_TYPE_ON_CREDIT);
            if ($payment instanceof Payment && !$payment->is_credit_receipt_sent) {
                $creditPaymentReceipt = $paymentService->createCreditReceipt($payment);
                if ($creditPaymentReceipt) {
                    $paymentService->sendCreditPaymentReceipt($payment, $creditPaymentReceipt, PaymentReceipt::TYPE_ON_CREDIT);
                    echo "Заказ $order->id: создана оплата $payment->id и кассовый чек с расчетом 'В кредит' \n";
                }
            }

            return;
        }

        //Формирование кассового чека с расчетом "Погашение кредита"
        //если статус кредитной заявки сменился на "Cached"
        //и заказ еще в пути
        //и ранее был выбит чек с расчетом "В кредит"
        if (
            $order->credit_status_id === OrderStatusEnum::CREDIT_ORDER_STATUS_CASHED
            && in_array(
                $order->status,
                [OrderStatus::TRANSFERRED_TO_DELIVERY, OrderStatus::DELIVERING, OrderStatus::READY_FOR_RECIPIENT],
                true
            )
            && $order->payments->isNotEmpty()
        ) {
            $payment = $order->payments()->first();
            if ($payment instanceof Payment && $payment->is_credit_receipt_sent && !$payment->is_credit_payment_receipt_sent) {
                $creditPaymentReceipt = $paymentService->createCreditPaymentReceipt($payment);
                if ($creditPaymentReceipt) {
                    $paymentService->sendCreditPaymentReceipt($payment, $creditPaymentReceipt, PaymentReceipt::TYPE_CREDIT_PAYMENT);
                    echo "Заказ $order->id: обновлена оплата $payment->id и создан кассовый чек с расчетом 'Погашение кредита' \n";
                }
            }
        }
    }
}
