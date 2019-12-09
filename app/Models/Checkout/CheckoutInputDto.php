<?php

namespace App\Models\Checkout;

use App\Models\Cart\CartManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CheckoutInputDto implements \JsonSerializable
{
    public $recipients = [];
    public $addresses = [];
    
    public $receiveMethodID;
    public $deliveryMethodID;
    public $paymentMethodID;
    public $confirmationTypeID;
    
    public $recipient;
    public $address;
    /** @var PickupPoint */
    public $pickupPoint;
    /** @var DeliveryType */
    public $deliveryType;
    
    public $subscribe = 0;
    public $agreement = 0;
    
    public $promocode;
    public $bonus;
    /** @var Certificate[] */
    public $certificates = [];
    
    /**
     * @param Request $request
     * @param string $path
     * @return CheckoutInputDto|string
     */
    public static function fromRequest(Request $request, ?string $path = null)
    {
        $data = data_get($request->all(), $path);
        // todo добавить правила валидации
        $validator = Validator::make($data, [
            'recipients' => 'nullable|array',
            'addresses' => 'nullable|array',
            'input' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        
        $input = new self();
        $input->recipients = $data['recipients'] ?? [];
        $input->addresses = $data['addresses'] ?? [];
        if (isset($data['input'])) {
            [
                'receiveMethodID' => $input->receiveMethodID,
                'deliveryMethodID' => $input->deliveryMethodID,
                'paymentMethodID' => $input->paymentMethodID,
                'confirmationTypeID' => $input->confirmationTypeID,
        
                'recipient' => $input->recipient,
                'address' => $input->address,
                'pickupPoint' => $input->pickupPoint,
        
                'subscribe' => $input->subscribe,
                'agreement' => $input->agreement,
        
                'promocode' => $input->promocode,
                'bonus' => $input->bonus,
            ] = $data['input'];
            
            foreach ($data['input']['certificates'] as ['id' => $certId,'code' => $certCode,'amount' => $certAmount]) {
                // todo проверять данные сертификата!
                $input->certificates[] = new Certificate($certId, $certCode, $certAmount);
            }
            if ($data['input']['deliveryType']) {
                $input->deliveryType = DeliveryType::fromRequest($data['input']['deliveryType']);
            }
            if ($data['input']['pickupPoint']) {
                $input->pickupPoint = PickupPoint::fromRequest($data['input']['pickupPoint']);
            }
        }
        
        return $input;
    }
    
    public function setAddress(UserAddress $address)
    {
        $this->address = $address;
        $this->pickupPoint = null;
    }
    
    public function setPromocode(?string $promocode = null)
    {
        $this->promocode = $promocode;
    }
    
    public function addCertificate(string $code)
    {
        $certificate = CartManager::certificateByCode($code);
        if ($certificate) {
            foreach ($this->certificates as $yetApplied) {
                if ($yetApplied->id == $certificate->id) {
                    return;
                }
            }
            $this->certificates[] = $certificate;
        }
    }
    
    public function removeCertificate(string $code)
    {
        foreach ($this->certificates as $i => $certificate) {
            if ($certificate->code == $code) {
                unset($this->certificates[$i]);
            }
        }
    }
    
    public function jsonSerialize()
    {
        return [
            'receiveMethodID' => $this->receiveMethodID,
            'deliveryMethodID' => $this->deliveryMethodID,
            'paymentMethodID' => $this->paymentMethodID,
            'confirmationTypeID' => $this->confirmationTypeID,
    
            'recipient' => $this->recipient,
            'address' => $this->address,
            'pickupPoint' => $this->pickupPoint,
            'deliveryType' => $this->deliveryType,
    
            'subscribe' => $this->subscribe ?? 0,
            'agreement' => $this->agreement ?? 0,
    
            'promocode' => $this->promocode,
            'bonus' => $this->bonus,
            'certificates' => $this->certificates,
        ];
    }
    
    public function setPickup(PickupPoint $point)
    {
        $this->pickupPoint = $point;
    }
}
