<?php

namespace App\Services\Dto\Internal\PublicEventOrder;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Class OrderInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class OrderInfoDto implements Arrayable
{
    /** @var int */
    public $id;
    /** @var int */
    public $type;
    /** @var string */
    public $number;
    /** @var Carbon */
    public $createdAt;
    /** @var float */
    public $price;
    /** @var int */
    public $status;
    /** @var int */
    public $paymentStatus;
    /** @var bool */
    public $isCanceled;
    /** @var string */
    public $receiverName;
    /** @var string */
    public $receiverPhone;
    /** @var string */
    public $receiverEmail;
    /** @var bool */
    public $canRepeat = true; //todo
    /** @var bool */
    public $hasBadOffers = false; //todo
    /** @var Collection|PublicEventInfoDto[] */
    public $publicEvents;

    /**
     * OrderInfoDto constructor.
     */
    public function __construct()
    {
        $this->publicEvents = collect();
    }

    public function addPublicEvent(PublicEventInfoDto $publicEventInfoDto): void
    {
        $this->publicEvents->push($publicEventInfoDto);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'number' => $this->number,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'), //todo Отрефакторить
            'price' => $this->price,
            'status' => $this->status,
            'payment_status' => $this->paymentStatus,
            'is_canceled' => $this->isCanceled,
            'receiver_name' => $this->receiverName,
            'receiver_phone' => $this->receiverPhone,
            'receiver_email' => $this->receiverEmail,
            'can_repeat' => $this->canRepeat,
            'has_bad_offers' => $this->hasBadOffers,
            'publicEvents' => $this->publicEvents->map(function (PublicEventInfoDto $publicEventInfoDto) {
                return $publicEventInfoDto->toArray();
            })->toArray(),
        ];
    }
}
