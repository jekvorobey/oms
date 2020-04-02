<?php


namespace App\Core;


use App\Models\Delivery\Delivery;
use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\PointDto;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Message\Services\SmsCentreService\SmsCentreService;

class OrderSmsNotify
{
    public static function payed(Order $order)
    {
        static::send($order, "Заказ №{$order->number} оплачен и принят в обработку!");
    }

    public static function deliveryShipped(Delivery $delivery)
    {
        $delivery_at = $delivery->delivery_at->format('d.m.Y');
        $cost = $delivery->shipments->sum('cost');
        static::send($delivery->order, "Заказ №{$delivery->number} на сумму {$cost} р. передан в службу доставки. Ожидайте доставку {$delivery_at}.");
    }

    public static function deliveryReadyForRecipient(Delivery $delivery)
    {
        if ($delivery->delivery_method != DeliveryMethod::METHOD_PICKUP) {
            return;
        }

        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);
        /** @var PointDto $point */
        $point = $listsService->points((new RestQuery())->setFilter('id', $delivery->point_id))->first();
        if (!$point) {
            return;
        }

//        $point->address->
        $cost = $delivery->shipments->sum('cost');
        static::send($delivery->order, join("\n", [
            "Заказ №{$delivery->number} на сумму $cost} р. ожидает вас в пункте самовывоза по адресу: {{DELIVIRY_ADDRESS}} до {{RESERVED_DATE}}.",
            "Режим работы {{DELIVERY_TYPE}}: {{OPER_MODE}}",
            "Контактный номер {{DELIVERY_TYPE}}: {{CALL_TK}}",
        ]));
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
