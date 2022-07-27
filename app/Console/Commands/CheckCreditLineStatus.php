<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Services\CreditService\CreditService;
use App\Services\CreditService\CreditSystems\CreditSystemInterface;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Exception;
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
            ->each(function (Order $order) {
                $this->checkCreditOrder($order);
            });
    }

    private function checkCreditOrder(Order $order): void
    {
        dump($order->id);

        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);

        try {
            $creditService = new CreditService();
            $checkStatus = $creditService->checkStatus($order);
        } catch (Throwable $e) {
            report($e);
            return;
        }

        if (!$checkStatus) {
            return;
        }

        $isUpdateOrder = false;
        // Обновить процент скидки
        if ((float) $order->credit_discount !== (float) $checkStatus['discount']) {
            $order->credit_discount = (float) $checkStatus['discount'];
            $isUpdateOrder = true;
        }

        // Обновить статус кредитного договора
        if ((int) $order->credit_status_id !== (int) $checkStatus['statusId']) {
            $order->credit_status_id = (int) $checkStatus['statusId'];
            $isUpdateOrder = true;
        }

        if ($isUpdateOrder === true) {
            $order->save();
        }

        // Отмена заказа, у которого не принята заявка на кредит
        if (!$order->is_canceled && in_array($checkStatus['statusId'], [CreditSystemInterface::CREDIT_ORDER_STATUS_REFUSED, CreditSystemInterface::CREDIT_ORDER_STATUS_ANNULED], true)
        ) {
            try {
                $orderService->cancel($order, CreditSystemInterface::ORDER_RETURN_REASON_ID);
            } catch (Exception $e) {
                report($e);
                return;
            }
        }
    }
}
