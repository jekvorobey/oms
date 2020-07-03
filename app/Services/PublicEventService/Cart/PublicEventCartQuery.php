<?php

namespace App\Services\PublicEventService\Cart;

use Pim\Dto\Search\PublicEventQuery;

/**
 * Class PublicEventCartQuery
 * @package App\Services\PublicEventService\Cart
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
     * @param $offset
     * @param $limit
     * @return $this
     */
    public function pageOffset($offset, $limit): self
    {
        $this->pimPublicEventQuery->pageOffset($offset, $limit);

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
            PublicEventQuery::TICKET_TYPES,
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
