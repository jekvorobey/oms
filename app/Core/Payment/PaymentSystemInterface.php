<?php


namespace App\Core\Payment;


use App\Models\Payment\Payment;

interface PaymentSystemInterface
{
    /**
     * Обратиться к внешней системы оплаты для создания платежа.
     *
     * @param Payment $payment
     * @param string $returnLink ссылка на страницу, на которую пользователь должен попасть после оплаты
     */
    public function createExternalPayment(Payment $payment, string $returnLink): void;

    /**
     * Получить от внешней системы ссылку страницы оплаты.
     *
     * @param Payment $payment
     * @return string
     */
    public function paymentLink(Payment $payment): string;

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
     *
     * @return int|null
     */
    public function duration(): ?int;
}
