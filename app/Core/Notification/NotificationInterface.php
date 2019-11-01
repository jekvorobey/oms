<?php

namespace App\Core\Notification;


use App\Models\OmsModel;

/**
 * Interface NotificationInterface
 * @package App\Core\Notification
 */
interface NotificationInterface
{
    /**
     * Создать уведомление
     * @param  int  $type
     * @param  OmsModel  $mainModel
     * @param  OmsModel  $model
     */
    public static function notify(int $type, OmsModel $mainModel, OmsModel $model): void;
}
