<?php

namespace App\Services\PaymentService\PaymentSystems;

use App\Models\Order\OrderReturn;
use App\Models\Payment\Payment;

/**
 * Interface PaymentSystemInterface
 * @package App\Services\PaymentService\PaymentSystems
 */
interface PaymentSystemInterface
{
    /**
     * Статус успешного возврата оплаты
     */
    public const STATUS_REFUND_SUCCESS = 'succeeded';

    /**
     * Статус отмены оплаты
     */
    public const STATUS_CANCELLED = 'canceled';

    /**
     * Обратиться к внешней системы оплаты для создания платежа.
     *
     * @param string $returnLink ссылка на страницу, на которую пользователь должен попасть после оплаты
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void;

    /**
     * Провести оплату холдированными средствами.
     */
    public function commitHoldedPayment(Payment $localPayment, $amount);

    /**
     * Обработать данные от платёжной системы о совершении платежа.
     *
     * @param array $data
     */
    public function handlePushPayment(array $data): void;

    /**
     * Время в часах, в течение которого можно совершить платёж после его создания.
     * Если за эт овремя платёж не совершён - заказ отменяется.
     * Если не указано, то время бесконечно.
     */
    public function duration(): ?int;

    /**
     * Сформировать запрос на возврат средств
     */
    public function refund(Payment $payment, OrderReturn $orderReturn): array;

    /**
     * Сформировать запрос на отмену оплаты
     */
    public function cancel(string $paymentId): array;

    /**
     * Создание чека прихода
     */
    public function createIncomeReceipt(Payment $payment, bool $isFullPayment): void;

    /**
     * Создание чека "В кредит"
     */
    public function createCreditReceipt(Payment $payment): void;

    /**
     * Создание чека "Погашение кредита"
     */
    public function createCreditPaymentReceipt(Payment $payment): void;

    /**
     * Создание чека возврата (при отмене всего заказа/платежа)
     */
    public function createRefundAllReceipt(Payment $payment): void;

    /**
     * Получить информацию о платеже в платёжной системе
     */
    public function paymentInfo(Payment $payment);

    /**
     * Обработать данные от платёжной системы
     */
    public function updatePaymentStatus(Payment $localPayment, $payment): void;
}
