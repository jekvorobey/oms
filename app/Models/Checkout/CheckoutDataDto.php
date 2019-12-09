<?php

namespace App\Models\Checkout;

use App\Models\Cart\Cart;
use App\Models\Cart\CartManager;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;

class CheckoutDataDto implements \JsonSerializable
{
    
    public $pickupPoints = [];
    /** @var DeliveryMethod[] */
    public $receiveMethods = [];
    /** @var DeliveryType[] */
    public $deliveryTypes = [];
    /** @var PaymentMethod[] */
    public $paymentMethods = [];
    /** @var ConfirmationType[] */
    public $confirmationTypes = [];
    public $availableBonus = 0;
    
    /** @var CheckoutInputDto */
    public $input;
    /** @var CheckoutSummaryDto */
    public $summary;
    
    public function setReceiveMethod(array $newMethod)
    {
        $this->input->receiveMethodID = $newMethod['id'];
        $this->input->deliveryMethodID = null;
    }
    
    public function receiveMethodById(int $id): ?DeliveryMethod
    {
        $receiveMethods = array_filter($this->receiveMethods, function (DeliveryMethod $method) use ($id) {
            return $method->id == $id;
        });
        return $receiveMethods ? current($receiveMethods) : null;
    }
    
    public function deliveryTypeById(int $id): ?DeliveryType
    {
        $types = array_filter($this->deliveryTypes, function (DeliveryType $type) use ($id) {
            return $type->id == $id;
        });
        return $types ? current($types) : null;
    }
    
    public function pickupById($id)
    {
        $pickupPoints = array_filter($this->pickupPoints, function (PickupPoint $point) use ($id) {
            return $point->id == $id;
        });
        return count($pickupPoints) ? current($pickupPoints) : null;
    }
    
    public function setBonus(int $bonus)
    {
        if ($bonus > 0) {
            $this->input->bonus = $this->availableBonus < $bonus ? $this->availableBonus : $bonus;
        } else {
            $this->input->bonus = 0;
        }
    }
    
    public function jsonSerialize()
    {
        return [
            'recipients' => $this->input->recipients,
            'addresses' => $this->input->addresses,
            'pickupPoints' => $this->pickupPoints,
            'receiveMethods' => $this->receiveMethods,
            'paymentMethods' => $this->paymentMethods,
            'confirmationTypes' => $this->confirmationTypes,
            'deliveryTypes' => $this->deliveryTypes,
            'availableBonus' => $this->availableBonus,
            
            'input' => $this->input,
            'summary' => $this->summary
        ];
    }
}
