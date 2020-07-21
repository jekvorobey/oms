<?php

namespace App\Services\Dto\Internal\OrderTicket;

/**
 * Class SpeakerInfoDto
 * @package App\Mail\PublicEvent\SendTicket\Dto
 */
class SpeakerInfoDto
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
    public $profession;
    /** @var int */
    public $avatar;
    /** @var string */
    public $instagram;
    /** @var string */
    public $facebook;
    /** @var string */
    public $linkedin;

    /**
     * @param  string|null  $link
     * @return string|null
     */
    protected function getSocialLogin(?string $link): ?string
    {
        return $link ? collect(explode('/', $link))->last() : null;
    }

    /**
     * @param  string|null  $instagram
     */
    public function setInstagram(?string $instagram): void
    {
        $this->instagram = $this->getSocialLogin($instagram);
    }

    /**
     * @param  string|null  $facebook
     */
    public function setFacebook(?string $facebook): void
    {
        $this->facebook = $this->getSocialLogin($facebook);
    }

    /**
     * @param  string|null  $linkedin
     */
    public function setLinkedin(?string $linkedin): void
    {
        $this->linkedin = $this->getSocialLogin($linkedin);
    }
}
