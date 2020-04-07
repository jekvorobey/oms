<?php

namespace App\Services\Dto\Out;

use Illuminate\Support\Fluent;

/**
 * Class DocumentDto
 * @package App\Services\Dto\Out
 *
 * @property int $file_id - id на файл
 * @property bool $success
 * @property string $message - сообщение в случае ошибки
 */
class DocumentDto extends Fluent
{
}
