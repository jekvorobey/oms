<?php

namespace App\Services\Dto\Internal\PublicEventOrder;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Class SpeakerInfoDto
 * @package App\Mail\PublicEvent\SendTicket\Dto
 */
class SpeakerInfoDto implements Arrayable
{
    /** @var int */
    public $id;
    /** @var string */
    public $firstName;
    /** @var string */
    public $middleName;
    /** @var string */
    public $lastName;
    /** @var string */
    public $phone;
    /** @var string */
    public $email;
    /** @var string */
    public $profession;
    /** @var int */
    public $avatar;
    /** @var string */
    public $instagram;
    /** @var string */
    public $facebook;
    /** @var string */
    public $linkedin;

    protected function getSocialLogin(?string $link): ?string
    {
        return $link ? collect(explode('/', $link))->last() : null;
    }

    public function setInstagram(?string $instagram): void
    {
        $this->instagram = $this->getSocialLogin($instagram);
    }

    public function setFacebook(?string $facebook): void
    {
        $this->facebook = $this->getSocialLogin($facebook);
    }

    public function setLinkedin(?string $linkedin): void
    {
        $this->linkedin = $this->getSocialLogin($linkedin);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'email' => $this->email,
            'profession' => $this->profession,
            'avatar' => $this->avatar,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'linkedin' => $this->linkedin,
        ];
    }
}
