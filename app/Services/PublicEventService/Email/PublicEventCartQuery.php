<?php

namespace App\Services\PublicEventService\Email;

use Pim\Dto\Search\PublicEventQuery;

/**
 * Class PublicEventCartQuery
 * @package App\Services\PublicEventService\Email
 */
class PublicEventCartQuery
{
    /** @var PublicEventCartRepository */
    private $repository;
    /** @var PublicEventQuery */
    private $pimPublicEventQuery;

    /**
     * PublicEventCardQuery constructor.
     * @param  PublicEventCartRepository  $repository
     * @param  int|null  $segment
     * @param  int|null  $role
     */
    public function __construct(PublicEventCartRepository $repository)
    {
        $this->pimPublicEventQuery = new PublicEventQuery();
        $this->repository = $repository;
    }

    /**
     * @param  array  $offerIds
     * @return $this
     */
    public function whereOfferIds(array $offerIds): self
    {
        $this->pimPublicEventQuery->offer_ids = $offerIds;

        return $this;
    }

    /**
     * @param  int  $page
     * @param  int  $size
     * @return $this
     */
    public function pageNumber(int $page, int $size): self
    {
        $this->pimPublicEventQuery->page($page, $size);

        return $this;
    }

    /**
     * @param $name
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($name, $direction = 'asc'): self
    {
        $this->pimPublicEventQuery->orderBy($name, $direction);

        return $this;
    }

    /**
     * @return array|[total, publicEvents]
     */
    public function get(): array
    {
        $this->pimPublicEventQuery->fields([
            PublicEventQuery::ID,
            PublicEventQuery::NAME,
            PublicEventQuery::SPRINT_ID,
            PublicEventQuery::ORGANIZER,
            PublicEventQuery::SPEAKERS,
            PublicEventQuery::CODE,
            PublicEventQuery::ACTIVE,
            PublicEventQuery::NEAREST_DATE,
            PublicEventQuery::NEAREST_TIME_FROM,
            PublicEventQuery::NEAREST_PLACE_NAME,
            PublicEventQuery::CATALOG_IMAGE,
            PublicEventQuery::PLACES,
            PublicEventQuery::STAGES,
        ]);

        return $this->repository->find($this);
    }

    /**
     * @return PublicEventQuery
     */
    public function getPimPublicEventQuery(): PublicEventQuery
    {
        return $this->pimPublicEventQuery;
    }
}
