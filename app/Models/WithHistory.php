<?php

namespace App\Models;

use App\Models\History\History;
use App\Models\History\HistoryType;
use Illuminate\Database\Eloquent\Model;

/**
 * Трейт для записи в историю событий создания/обновления/удаления модели
 */
trait WithHistory
{
    public static function bootWithHistory(): void
    {
        static::created(function (Model $model) {
            $model->saveHistoryEvent(HistoryType::TYPE_CREATE);
        });

        static::updated(function (Model $model) {
            $model->saveHistoryEvent(HistoryType::TYPE_UPDATE);
        });

        static::deleting(function (Model $model) {
            $model->saveHistoryEvent(HistoryType::TYPE_DELETE);
        });
    }

    /*
     * Запись события в историю
     */
    public function saveHistoryEvent(int $type, $mainModel = null): void
    {
        $mainModel ??= $this->historyMainModel();

        if (!$mainModel) {
            return;
        }

        History::saveEvent($type, $mainModel, $this);
    }

    /**
     * Основная(родительская) сущность, к которой будет привязано событие
     * @return Model|Model[]|WithMainHistory|null
     */
    abstract protected function historyMainModel();
}
