<?php

namespace App\Services;

use App\Models\Delivery\Shipment;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use MerchantManagement\Dto\OperatorCommunicationMethod;
use MerchantManagement\Dto\OperatorDto;
use MerchantManagement\Services\OperatorService\OperatorService;

/**
 * Класс-бизнес логики по работе с сущностями отправлениями
 * Class ShipmentService
 * @package App\Services
 */
class ShipmentService
{
    public function sendShipmentNotification(Shipment $shipment)
    {
        try {
            /** @var ServiceNotificationService $serviceNotificationService */
            $serviceNotificationService = app(ServiceNotificationService::class);
            /** @var OperatorService $operatorService */
            $operatorService = app(OperatorService::class);
            /** @var UserService $userService */
            $userService = app(UserService::class);

            $operators = $operatorService->operators(
                (new RestQuery())->setFilter('merchant_id', '=', $shipment->merchant_id)
            );

            $users = $userService->users(
                $userService->newQuery()->setFilter('id', $operators->pluck('user_id')->all())
            )->keyBy('id');

            /** @var OperatorDto $operator */
            foreach ($operators as $i => $operator) {
                $user = $users->get($operator->user_id);
                $attributes = $this->generateNotificationAttributes($shipment, $user);

                if ($user) {
                    if ($i === 0) { // TODO: добавить проверку, что оператор является админом
                        $serviceNotificationService->send(
                            $operator->user_id,
                            'klientoformlen_novyy_zakaz',
                            $this->generateNotificationAttributes($shipment, $user)
                        );
                        continue;
                    }

                    switch ($operator->communication_method) {
                        case OperatorCommunicationMethod::METHOD_PHONE:
                            $serviceNotificationService->sendDirect('klientoformlen_novyy_zakaz', $user->phone, 'sms', $attributes);
                            break;
                        case OperatorCommunicationMethod::METHOD_EMAIL:
                            $serviceNotificationService->sendDirect('klientoformlen_novyy_zakaz', $user->email, 'email', $attributes);
                            break;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function generateNotificationAttributes(Shipment $shipment, ?UserDto $user): array
    {
        return [
            'QUANTITY_ORDERS' => 1,
            'LINK_ORDERS' => sprintf('%s/shipment/list/%d', config('mas.masHost'), $shipment->id),
            'CUSTOMER_NAME' => $user ? $user->first_name : '',
            'SUM_ORDERS' => (int) $shipment->cost,
            'GOODS_NAME' => $shipment->items->first()->basketItem->name,
            'QUANTITY_GOODS' => (int) $shipment->items->first()->basketItem->qty,
            'PRISE_GOODS' => (int) $shipment->items->first()->basketItem->price,
            'ALL_QUANTITY_GOODS' => (int) $shipment->items()->with('basketItem')->get()->sum('basketItem.qty'),
            'ALL_PRISE_GOODS' => (int) $shipment->cost,
        ];
    }
}
