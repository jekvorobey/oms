<?php

namespace App\Core\Notifications;

use Greensight\Message\Dto\Notification\NotificationDto;

/**
 * Class AbstractNotification
 * @package App\Core\Notifications
 */
class AbstractNotification
{
    /**
     * @return array
     */
    protected static function getBaseNotification(): NotificationDto
    {
        return new NotificationDto([
            'status' => 0,
            'payload' => [
                'type' => '',
                'title' => '',
                'body' => ''
            ]
        ]);
    }
}
