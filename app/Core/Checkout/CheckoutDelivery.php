<?php

namespace App\Core\Checkout;

class CheckoutDelivery
{
    /** @var int */
    public $tariffId;
    /** @var int */
    public $deliveryMethod;
    /** @var int */
    public $deliveryService;
    /** @var int */
    public $pointId;

    /** @var array */
    public $deliveryAddress;
    /** @var string */
    public $receiverName;
    /** @var string */
    public $receiverPhone;
    /** @var string */
    public $receiverEmail;

    /** @var string */
    public $selectedDate;

    /** @var string */
    public $deliveryTimeStart;

    /** @var string */
    public $deliveryTimeEnd;

    /** @var string */
    public $deliveryTimeCode;
    /** @var int */
    public $dt;
    /** @var string */
    public $pdd;
    /** @var float */
    public $cost;
    /** @var CheckoutShipment[] */
    public $shipments;

    public static function fromArray(array $data): self
    {
        $delivery = new self();
        @([
            'tariffId' => $delivery->tariffId,
            'deliveryMethod' => $delivery->deliveryMethod,
            'deliveryService' => $delivery->deliveryService,
            'pointId' => $delivery->pointId,
            'deliveryAddress' => $delivery->deliveryAddress,
            'receiverName' => $delivery->receiverName,
            'receiverPhone' => $delivery->receiverPhone,
            'receiverEmail' => $delivery->receiverEmail,
            'selectedDate' => $delivery->selectedDate,
            'deliveryTimeStart' => $delivery->deliveryTimeStart,
            'deliveryTimeEnd' => $delivery->deliveryTimeEnd,
            'deliveryTimeCode' => $delivery->deliveryTimeCode,
            'dt' => $delivery->dt,
            'pdd' => $delivery->pdd,
            'cost' => $delivery->cost,
            'shipments' => $shipments,
        ] = $data);

        foreach ($shipments as $shipmentData) {
            $delivery->shipments[] = CheckoutShipment::fromArray($shipmentData);
        }

        return $delivery;
    }
}
