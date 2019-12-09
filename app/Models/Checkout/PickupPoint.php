<?php

namespace App\Models\Checkout;

class PickupPoint implements \JsonSerializable
{
    public $id;
    public $methodId;
    public $title;
    public $time;
    public $name;
    public $phone;
    public $description;
    public $payment;
    public $startDate;
    public $schedule = [];
    public $coords;
    
    public static function fromRequest(array $data): ?PickupPoint
    {
        $point = new self($data['id'], $data['methodID'], $data['name'], $data['payment']);
        $point->setDescription($data['title'], $data['description'], $data['phone'], $data['map']['coords']);
        $point->setDate($data['startDate']);
        foreach ($data['schedule'] as $scheduleItem) {
            $point->addScheduleItem($scheduleItem['id'], $scheduleItem['title'], $scheduleItem['time']);
        }
        return $point;
    }
    
    /**
     * PickupPoint constructor.
     * @param $id
     * @param $methodId
     * @param $name
     * @param $payment
     */
    public function __construct($id, $methodId, $name, $payment)
    {
        $this->id = $id;
        $this->methodId = $methodId;
        $this->title = $name;
        $this->payment = $payment;
    }
    
    public function setDescription(string $title, string $description, string $phone, array $coords)
    {
        $this->title = $title;
        $this->description = $description;
        $this->phone = $phone;
        $this->coords = $coords;
    }
    
    public function setDate(string $startDate)
    {
        $this->startDate = $startDate;
    }
    
    public function addScheduleItem(int $id, string $title, string $time)
    {
        $this->schedule[] = [
            'id' => $id,
            'title' => $title,
            'time' => $time
        ];
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'methodID' => $this->methodId,
            'title' => $this->title,
            'name' => $this->name,
            'phone' => $this->phone,
            'description' => $this->description,
            'payment' => $this->payment,
            'startDate' => $this->startDate,
            'schedule' => $this->schedule,
            'map' => [
                'coords' => $this->coords
            ],
        ];
    }
}
