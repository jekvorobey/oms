<?php

namespace App\Models\Basket;

use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\Order\Order;
use App\Models\Order\OrderReturnItem;
use App\Models\WithHistory;
use App\Services\PublicEventService\Cart\PublicEventCartRepository;
use App\Services\PublicEventService\Cart\PublicEventCartStruct;
use Exception;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductImageDto;
use Pim\Dto\Product\ProductImageType;
use Pim\Dto\PublicEvent\MediaDto;
use Pim\Dto\PublicEvent\SprintDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;
use Pim\Services\PublicEventMediaService\PublicEventMediaService;
use Pim\Services\PublicEventSprintService\PublicEventSprintService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Состав корзины",
 *     @OA\Property(property="basket_id", type="integer", description="ID корзины"),
 *     @OA\Property(property="offer_id", type="integer", description="ID предложения мерчанта"),
 *     @OA\Property(property="type", type="integer", description="тип товара (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)"),
 *     @OA\Property(property="name", type="string", description="название товара"),
 *     @OA\Property(property="qty", type="number", description="кол-во товара"),
 *     @OA\Property(property="price", type="number", description="цена элемента корзины со скидкой"),
 *     @OA\Property(property="cost", type="number", description="стоимость элемента корзины без скидок (offerCost * qty)"),
 *     @OA\Property(property="bonus_spent", type="integer", description="потраченные бонусы на элемент корзины ( * qty)"),
 *     @OA\Property(property="bonus_discount", type="integer", description="оплачено бонусами ( * qty)"),
 *     @OA\Property(property="referrer_id", type="integer", description="ID РП, по чьей ссылке товар был добавлен в корзину"),
 *     @OA\Property(property="product", type="number", description="данные зависящие от типа товара"),
 *     @OA\Property(property="bundle_id", type="number", description="id бандла, в который входит этот товар"),
 *     @OA\Property(property="basket", type="array", @OA\Items(ref="#/components/schemas/Basket")),
 *     @OA\Property(property="shipmentItem", type="array", @OA\Items(ref="#/components/schemas/ShipmentItem")),
 *     @OA\Property(property="shipmentPackageItem", type="array", @OA\Items(ref="#/components/schemas/ShipmentPackageItem")),
 * )
 *
 * Состав корзины
 * Class BasketItem
 * @package App\Models
 *
 * @property int $basket_id - id корзины
 * @property int $offer_id - id предложения мерчанта
 * @property int $type - тип товара (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER|Basket::TYPE_CERTIFICATE)
 * @property string $name - название товара
 * @property float $qty - кол-во товара
 * @property float|null $price - цена элемента корзины со скидкой
 * @property float|null $cost - стоимость элемента корзины без скидок (offerCost * qty)
 * @property int $bonus_spent - потраченные бонусы на элемент корзины ( * qty)
 * @property int $bonus_discount - оплачено бонусами ( * qty)
 * @property int|null $referrer_id - ID РП, по чьей ссылке товар был добавлен в корзину
 * @property array $product - данные зависящие от типа товара
 * @property int|null $bundle_id - id бандла, в который входит этот товар
 *
 * @property-read Basket $basket
 * @property-read ShipmentItem $shipmentItem
 * @property-read ShipmentPackageItem $shipmentPackageItem
 */
class BasketItem extends AbstractModel
{
    use WithHistory;

    /** @var bool */
    protected static $unguarded = true;

    /** @var array */
    protected $casts = [
        'product' => 'array',
    ];

    /**
     * BasketItem constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->product = [];

        parent::__construct($attributes);
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    public function shipmentItem(): HasOne
    {
        return $this->hasOne(ShipmentItem::class);
    }

    public function shipmentPackageItem(): HasOne
    {
        return $this->hasOne(ShipmentPackageItem::class);
    }

    public function orderReturnItems(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    protected function historyMainModel(): ?Order
    {
        return $this->basket->order;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    public function setDataByType(array $data = []): void
    {
        if ($this->type == Basket::TYPE_PRODUCT) {
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $offerInfo = $offerService->offerInfo($this->offer_id);
            $offerStocks = $offerInfo->stocks->keyBy('store_id');
            if (
                (
                    isset($data['product']['store_id'])
                    && (
                        !$offerStocks->has($data['product']['store_id'])
                        || $offerStocks[$data['product']['store_id']]->qty <= 0
                    )
                )
                || $offerStocks->isEmpty()
            ) {
                throw new BadRequestHttpException('offer without stocks');
            }
            $this->name = $offerInfo->name;
            $this->product = array_merge($this->product, [
                'weight' => $offerInfo->weight,
                'width' => $offerInfo->width,
                'height' => $offerInfo->height,
                'length' => $offerInfo->length,
                'merchant_id' => $offerInfo->merchant_id,
                'is_explosive' => $offerInfo->is_explosive,
                'sale_at' => $offerInfo->sale_at,
            ]);
            if (!isset($this->product['store_id'])) {
                $product = $this->product;
                $product['store_id'] = $offerInfo->stocks->sortBy('qty')[0]->store_id;
                $this->product = $product;
            }
        } elseif ($this->type == Basket::TYPE_MASTER) {
            [$totalCount, $cardStructs] = (new PublicEventCartRepository())->query()
                ->whereOfferIds([$this->offer_id])
                ->get();
            if (!$totalCount) {
                throw new BadRequestHttpException('PublicEvent not found');
            }

            /** @var PublicEventCartStruct $publicEventCartStruct */
            $publicEventCartStruct = $cardStructs[0];
            $this->name = $publicEventCartStruct->name;
            $this->product = array_merge($this->product, [
                'sprint_id' => $publicEventCartStruct->sprintId,
                'ticket_type_id' => $publicEventCartStruct->getIdByOfferId($this->offer_id),
                'ticket_type_name' => $publicEventCartStruct->getNameByOfferId($this->offer_id),
            ]);
        } elseif ($this->type == Basket::TYPE_CERTIFICATE) {
            //
        } else {
            throw new Exception('Undefined basket type');
        }
    }

    public function getStoreId(): ?int
    {
        return isset($this->product['store_id']) ? (int) $this->product['store_id'] : null;
    }

    /**
     * Получить id билетов на мастер-классы
     * @return array|null
     */
    public function getTicketIds(): ?array
    {
        return isset($this->product['ticket_ids']) ? (array) $this->product['ticket_ids'] : null;
    }

    /**
     * @param array $ticketIds
     */
    public function setTicketIds(array $ticketIds): void
    {
        $this->setProductField('ticket_ids', $ticketIds);
    }

    public function getSprintId(): ?int
    {
        return isset($this->product['sprint_id']) ? (int) $this->product['sprint_id'] : null;
    }

    public function getTicketTypeId(): ?int
    {
        return isset($this->product['ticket_type_id']) ? (int) $this->product['ticket_type_id'] : null;
    }

    public function getTicketTypeName(): ?string
    {
        return isset($this->product['ticket_type_name']) ? (string) $this->product['ticket_type_name'] : null;
    }

    protected function setProductField(string $field, $value): void
    {
        $product = $this->product;
        $product[$field] = $value;
        $this->product = $product;
    }

    public function getItemMedia()
    {
        switch ($this->type) {
            case Basket::TYPE_PRODUCT:
                return $this->getProductMedia();
            case Basket::TYPE_MASTER:
                return $this->getMasterMedia();
        }
    }

    private function getProductMedia()
    {
        /** @var OfferService $offerService */
        $offerService = app(OfferService::class);
        /** @var ProductService $productService */
        $productService = app(ProductService::class);

        /** @var OfferDto $offer */
        $offer = $offerService->offers(
            $offerService->newQuery()
                ->setFilter('id', $this->offer_id)
        )->first();

        return $productService
            ->allImages([$offer->product_id], ProductImageType::TYPE_MAIN)
            ->map(function (ProductImageDto $image) {
                return $image->url;
            })
            ->toArray();
    }

    private function getMasterMedia()
    {
        /** @var PublicEventSprintService $sprintService */
        $sprintService = app(PublicEventSprintService::class);
        /** @var PublicEventMediaService $publicEventMediaService */
        $publicEventMediaService = app(PublicEventMediaService::class);
        /** @var FileService $fileService */
        $fileService = app(FileService::class);

        /** @var SprintDto $sprint */
        $sprint = $sprintService->find(
            $sprintService->query()
                ->setFilter('id', $this->product['sprint_id'])
        )->first();

        return $publicEventMediaService
            ->find(
                $publicEventMediaService->query()
                    ->setFilter('collection', 'detail')
                    ->setFilter('media_id', $sprint->public_event_id)
                    ->setFilter('media_type', 'App\Models\PublicEvent\PublicEvent')
            )
            ->map(function (MediaDto $media) use ($fileService) {
                return $fileService
                    ->getFiles([$media->value])
                    ->first()
                    ->absoluteUrl();
            })
            ->toArray();
    }
}
