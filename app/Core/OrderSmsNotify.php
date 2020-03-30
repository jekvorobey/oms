<?php


namespace App\Core;


use App\Models\Order\Order;
use Greensight\Message\Services\SmsCentreService\SmsCentreService;

class OrderSmsNotify
{
    public static function payed(Order $order)
    {
        static::send($order, "Заказ №{$order->number} оплачен и принят в обработку!");
    }

    public static function transferredToDelivery()
    {
    }

    protected static function send(Order $order, $text)
    {
        /** @var SmsCentreService $smsService */
        $smsService = resolve(SmsCentreService::class);

        $smsService->send([$order->getUser()->phone], join("\n", [
            $text,
            "www.iBT.studio тел. 8(800)000-00-00"
        ]));
    }
}
