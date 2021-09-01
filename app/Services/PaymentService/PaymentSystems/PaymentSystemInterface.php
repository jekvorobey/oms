<?php

namespace App\Services\PaymentService\PaymentSystems;

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
     * Обратиться к внешней системы оплаты для создания платежа.
     *
     * @param string $returnLink ссылка на страницу, на которую пользователь должен попасть после оплаты
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void;

    /**
     * Провести оплату холдированными средствами.
     *
     * @param $amount
     */
    public function commitHoldedPayment(Payment $localPayment, $amount);

    /**
     * Получить от внешней системы ссылку страницы оплаты.
     */
    public function paymentLink(Payment $payment): ?string;

    /**
     * Получить от id оплаты во внешней системе.
     */
    public function externalPaymentId(Payment $payment): ?string;

    /**
     * Обработать данные от платёжной ситсемы о совершении платежа.
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
    public function refund(string $paymentId, int $amount): array;
}
