<?php

namespace App\Services\PublicEventService\Cart;

/**
 * Class PublicEventCartStruct
 * @package App\Services\PublicEventService\Cart
 */
class PublicEventCartStruct
{
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var int */
    public $sprintId;
    /** @var array */
    public $ticketTypes;

    /**
     * Получить поле типа билета
     * @return mixed
     */
    protected function getFieldByOfferId(int $offerId, string $field)
    {
        $ticketTypes = collect($this->ticketTypes)->keyBy('offer_id')->toArray();

        return data_get($ticketTypes, $offerId . '.' . $field);
    }

    /**
     * Получить название типа билета
     */
    public function getNameByOfferId(int $offerId): string
    {
        return (string) $this->getFieldByOfferId($offerId, 'name');
    }

    /**
     * Получить id типа билета
     */
    public function getIdByOfferId(int $offerId): int
    {
        return (int) $this->getFieldByOfferId($offerId, 'id');
    }

    /**
     * Получить ids программ типа билета
     */
    public function getStageIdsByOfferId(int $offerId): int
    {
        return (int) $this->getFieldByOfferId($offerId, 'stage_ids');
    }
}
