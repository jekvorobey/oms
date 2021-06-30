<?php

namespace App\Services\PublicEventService\Cart;

use Pim\Dto\Search\PublicEventQuery;
use Pim\Dto\Search\PublicEventSearchResult;
use Pim\Services\SearchService\SearchService;

/**
 * Class PublicEventCartRepository
 * @package App\Services\PublicEventService\Cart
 */
class PublicEventCartRepository
{
    /** @var SearchService */
    private $searchService;

    /**
     * PublicEventCardRepository constructor.
     */
    public function __construct()
    {
        $this->searchService = resolve(SearchService::class);
    }

    public function query(): PublicEventCartQuery
    {
        return new PublicEventCartQuery($this);
    }

    /**
     * @return array|[total, publicEvents]
     */
    public function find(PublicEventCartQuery $query): array
    {
        $searchResult = $this->searchService->publicEvents($query->getPimPublicEventQuery());
        $publicEvents = $this->extractPublicEventCards($searchResult);

        return [$searchResult->total, $publicEvents];
    }

    /**
     * @return array|PublicEventCartStruct[]
     */
    private function extractPublicEventCards(PublicEventSearchResult $searchResult): array
    {
        $publicEventCards = [];
        foreach ($searchResult->publicEvents as $publicEvent) {
            $card = new PublicEventCartStruct();
            $card->id = $publicEvent[PublicEventQuery::ID];
            $card->name = $publicEvent[PublicEventQuery::NAME];
            $card->sprintId = $publicEvent[PublicEventQuery::SPRINT_ID];
            $card->ticketTypes = $publicEvent[PublicEventQuery::TICKET_TYPES];

            $publicEventCards[] = $card;
        }

        return $publicEventCards;
    }
}
