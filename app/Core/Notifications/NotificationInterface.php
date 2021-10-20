<?php

namespace App\Core\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface NotificationInterface
 * @package App\Core\Notifications
 */
interface NotificationInterface
{
    /**
     * Создать уведомление
     */
    public function notify(int $type, Model $mainModel, Model $model): void;
}
