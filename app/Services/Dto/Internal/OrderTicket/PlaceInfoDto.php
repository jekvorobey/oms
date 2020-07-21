<?php

namespace App\Services\Dto\Internal\OrderTicket;

use Illuminate\Support\Collection;

/**
 * Class PlaceInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class PlaceInfoDto
{
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var string */
    public $description;
    /** @var string */
    public $cityId;
    /** @var string */
    public $cityName;
    /** @var string */
    public $address;
    /** @var string */
    public $latitude;
    /** @var string */
    public $longitude;
    /** @var Collection|GalleryItemInfoDto[] */
    public $gallery;

    public function __construct()
    {
        $this->gallery = collect();
    }

    /**
     * @param  GalleryItemInfoDto  $galleryItemInfoDto
     */
    public function addGalleryItem(GalleryItemInfoDto $galleryItemInfoDto): void
    {
        $this->gallery->push($galleryItemInfoDto);
    }
}
