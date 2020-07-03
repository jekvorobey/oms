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
     * @param  int  $offerId
     * @return mixed
     */
    protected function getFieldByOfferId(int $offerId, string $field)
    {
        $ticketTypes = collect($this->ticketTypes)->keyBy('offer_id')->toArray();

        return data_get($ticketTypes, $offerId . '.' . $field);
    }

    /**
     * Получить название типа билета
     * @param  int  $offerId
     * @return string
     */
    public function getNameByOfferId(int $offerId): string
    {
        return (string)$this->getFieldByOfferId($offerId, 'name');
    }
}
