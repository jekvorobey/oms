<?php

namespace App\Services\PublicEventService\Email;

use Pim\Dto\Search\PublicEventQuery;
use Pim\Dto\Search\PublicEventSearchResult;
use Pim\Services\SearchService\SearchService;

/**
 * Class PublicEventCartRepository
 * @package App\Services\PublicEventService\Email
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
            $card->speakers = $publicEvent[PublicEventQuery::SPEAKERS];
            $card->places = $publicEvent[PublicEventQuery::PLACES];
            $card->stages = $publicEvent[PublicEventQuery::STAGES];
            $card->organizer = $publicEvent[PublicEventQuery::ORGANIZER];
            $card->dateFrom = $publicEvent[PublicEventQuery::DATE_FROM];
            $card->dateTo = $publicEvent[PublicEventQuery::DATE_TO];
            $card->code = $publicEvent[PublicEventQuery::CODE];
            $card->active = $publicEvent[PublicEventQuery::ACTIVE];
            $card->offerIds = $publicEvent[PublicEventQuery::OFFER_IDS];
            $card->availableForSale = $publicEvent[PublicEventQuery::AVAILABLE_FOR_SALE];
            $card->nearestDate = $publicEvent[PublicEventQuery::NEAREST_DATE];
            $card->nearestTimeFrom = $publicEvent[PublicEventQuery::NEAREST_TIME_FROM];
            $card->nearestPlaceName = $publicEvent[PublicEventQuery::NEAREST_PLACE_NAME];
            $card->image = $publicEvent[PublicEventQuery::CATALOG_IMAGE];

            $publicEventCards[] = $card;
        }

        return $publicEventCards;
    }
}
