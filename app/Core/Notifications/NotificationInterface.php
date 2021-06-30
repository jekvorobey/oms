<?php

namespace App\Core\Notifications;

use App\Models\OmsModel;

/**
 * Interface NotificationInterface
 * @package App\Core\Notifications
 */
interface NotificationInterface
{
    /**
     * Создать уведомление
     */
    public static function notify(int $type, OmsModel $mainModel, OmsModel $model): void;
}
