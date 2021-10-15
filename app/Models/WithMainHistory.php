<?php

namespace App\Models;

use App\Core\Notifications\NotificationInterface;
use App\Models\History\History;
use App\Models\History\HistoryMainEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * Трейт для записи истории, если сущность является основной (к которой привязываются события дочерних сущностей)
 * @property Collection|History[] $history - история изменений
 */
trait WithMainHistory
{
    use WithHistory;

    /**
     * Сервис для уведомлений по событиям
     */
    public function historyNotificator(): ?NotificationInterface
    {
        return null;
    }

    /**
     * История изменений (включая события дочерних сущностей)
     */
    public function history(): MorphToMany
    {
        return $this->morphToMany(History::class, 'main_entity', HistoryMainEntity::class);
    }

    /** @return Model|WithMainHistory */
    protected function historyMainModel()
    {
        return $this;
    }
}
