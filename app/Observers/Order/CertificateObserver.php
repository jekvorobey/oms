<?php


namespace App\Observers\Order;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use Greensight\Marketing\Services\Certificate\OrderService;

class CertificateObserver
{
    public function saved(Order $order)
    {
        if ($order->payment_status == $order->getOriginal('payment_status'))
            return;

        if ($order->type !== Basket::TYPE_CERTIFICATE)
            return;

        // Изменена статус оплаты для заказа подарочного сертификата - сообщаем об этом маркетинг модулю
        try {
            resolve(OrderService::class)->updatePaymentStatus($order->id, $order->payment_status);
        } catch (\Exception $e) {
            logger("FAILED: markPaid({$order->id}) / " . $e->getMessage());
        }
    }
}
