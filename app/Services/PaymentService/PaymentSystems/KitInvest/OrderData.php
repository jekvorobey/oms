<?php

namespace App\Services\PaymentService\PaymentSystems\KitInvest;

use App\Models\Order\Order;
use IBT\KitInvest\Enum\ReceiptEnum;
use Illuminate\Support\Collection;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Dto\VatDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\PublicEvent\PublicEventDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\PublicEventService\PublicEventService;

/**
 * Абстрактный класс для формирования информации по заказу
 *
 * @package App\Services\PaymentService\PaymentSystems\KitInvest
 */
abstract class OrderData
{
    protected MerchantService $merchantService;
    protected OfferService $offerService;
    protected PublicEventService $publicEventService;

    protected bool $isFullPayment = false;

    public function __construct()
    {
        $this->merchantService = resolve(MerchantService::class);
        $this->offerService = resolve(OfferService::class);
        $this->publicEventService = resolve(PublicEventService::class);
    }

    /**
     * @throws PimException
     */
    protected function loadOffersAndMerchants(array $offerIds, Order $order): array
    {
        $offers = collect();
        if ($offerIds) {
            $offers = $this->getOffers($offerIds, $order);
        }

        $merchantIds = $offers->pluck('merchant_id')->filter()->toArray();
        $merchants = collect();
        if (!empty($merchantIds)) {
            $merchants = $this->getMerchants($merchantIds);
        }

        return [$offers, $merchants];
    }

    protected function getMerchants(array $merchantIds): Collection
    {
        $merchantQuery = $this->merchantService->newQuery()
            ->addFields(MerchantDto::entity(), 'id', 'inn', 'legal_name')
            ->include('vats')
            ->setFilter('id', $merchantIds);

        return $this->merchantService->merchants($merchantQuery)->keyBy('id');
    }

    /**
     * @throws PimException
     */
    protected function getOffers(array $offerIds, Order $order): Collection
    {
        if ($order->isPublicEventOrder()) {
            return $this->getOffersForPublicEvents($offerIds);
        }

        $offersQuery = $this->offerService->newQuery()
            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
            ->include('product')
            ->setFilter('id', $offerIds);

        return $this->offerService->offers($offersQuery)->keyBy('id');
    }

    private function getOffersForPublicEvents(array $offerIds): Collection
    {
        $publicEventQuery = $this->publicEventService->query()
            ->addFields(PublicEventDto::class)
            ->setFilter('offer_id', $offerIds)
            ->include('organizer', 'sprints.ticketTypes.offer');
        $publicEvents = $this->publicEventService->findPublicEvents($publicEventQuery);

        return $publicEvents->groupBy('organizer.merchant_id')->flatMap(function (Collection $publicEvents, $merchantId) {
            return $publicEvents->pluck('sprints.*.ticketTypes.*.offer.id')
                ->collapse()
                ->filter()
                ->map(function ($offerId) use ($merchantId) {
                    return new OfferDto([
                        'id' => $offerId,
                        'merchant_id' => $merchantId,
                    ]);
                });
        })->keyBy('id');
    }

    protected function getItemVatCode(?object $offerInfo, ?object $merchant): ?int
    {
        if (!isset($offerInfo, $merchant)) {
            return ReceiptEnum::RECEIPT_SUBJECT_TAX_NONE;
        }
        $vatValue = $this->getMerchantVatValue($offerInfo, $merchant);

        return [
                -1 => ReceiptEnum::RECEIPT_SUBJECT_TAX_NONE,
                0 => ReceiptEnum::RECEIPT_SUBJECT_TAX_0,
                10 => ReceiptEnum::RECEIPT_SUBJECT_TAX_10,
                20 => ReceiptEnum::RECEIPT_SUBJECT_TAX_20,
            ][$vatValue] ?? ReceiptEnum::RECEIPT_SUBJECT_TAX_NONE;
    }

    protected function getMerchantVatValue(object $offerInfo, object $merchant): ?int
    {
        $itemMerchantVats = $merchant['vats'];
        usort($itemMerchantVats, static function ($a, $b) {
            return $b['type'] - $a['type'];
        });

        foreach ($itemMerchantVats as $vat) {
            $vatValue = $this->getVatValue($vat, $offerInfo);

            if ($vatValue !== null) {
                return $vatValue;
            }
        }

        return null;
    }

    protected function getVatValue(array $vat, object $offerInfo): ?int
    {
        switch ($vat['type']) {
            case VatDto::TYPE_GLOBAL:
                break;
            case VatDto::TYPE_MERCHANT:
                return $vat['value'];
            case VatDto::TYPE_BRAND:
                if ($offerInfo['product']['brand_id'] === $vat['brand_id']) {
                    return $vat['value'];
                }
                break;
            case VatDto::TYPE_CATEGORY:
                if ($offerInfo['product']['category_id'] === $vat['category_id']) {
                    return $vat['value'];
                }
                break;
            case VatDto::TYPE_SKU:
                if ($offerInfo['product_id'] === $vat['product_id']) {
                    return $vat['value'];
                }
                break;
        }

        return null;
    }
}
