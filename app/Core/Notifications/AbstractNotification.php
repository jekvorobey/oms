<?php

namespace App\Core\Notifications;

use Greensight\Message\Dto\Notification\NotificationDto;

/**
 * Class AbstractNotification
 * @package App\Core\Notifications
 */
class AbstractNotification
{
    protected function getBaseNotification(): NotificationDto
    {
        return new NotificationDto([
            'type' => '',
            'status' => 0,
            'payload' => [
                'title' => '',
                'body' => '',
            ],
        ]);
    }
}
