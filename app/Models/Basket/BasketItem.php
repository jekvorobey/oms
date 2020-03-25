<?php

namespace App\Models\Basket;

use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\OmsModel;
use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Pim\Services\OfferService\OfferService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Состав корзины
 * Class BasketItem
 * @package App\Models
 *
 * @property int $basket_id - id корзины
 * @property int $offer_id - id предложения мерчанта
 * @property int $type - тип товара (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)
 * @property string $name - название товара
 * @property float $qty - кол-во товара
 * @property float|null $price - цена элемента корзины со скидкой (cost - discount)
 * @property float|null $discount - скидка элемента корзины (offerDiscount * qty)
 * @property float|null $cost - стоимость элемента корзины без скидок (offerCost * qty)
 * @property int|null $referrer_id - ID РП, по чьей ссылке товар был добавлен в корзину
 * @property array $product - данные зависящие от типа товара
 *
 * @property-read Basket $basket
 * @property-read ShipmentItem $shipmentItem
 * @property-read ShipmentPackageItem $shipmentPackageItem
 *
 * @OA\Schema(
 *     schema="BasketItem",
 *     @OA\Property(property="id", type="integer", description="id оффера в корзине"),
 *     @OA\Property(property="basket_id", type="integer", description="id корзины"),
 *     @OA\Property(property="offer_id", type="integer", description="id предложения мерчанта"),
 *     @OA\Property(property="name", type="string", description="название товара"),
 *     @OA\Property(property="qty", type="integer", description="кол-во"),
 *     @OA\Property(property="price", type="number", description="цена за единицу товара без учета скидки"),
 *     @OA\Property(property="discount", type="number", description="скидка за все кол-во товара"),
 *     @OA\Property(property="cost", type="number", description="сумма за все кол-во товара с учетом скидки (расчитывается автоматически)"),
 * )
 */
class BasketItem extends OmsModel
{
    /** @var array */
    protected $casts = [
        'product' => 'array'
    ];

    /**
     * BasketItem constructor.
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->product = [];
        parent::__construct($attributes);
    }
    /**
     * @return BelongsTo
     */
    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    /**
     * @return HasOne
     */
    public function shipmentItem(): HasOne
    {
        return $this->hasOne(ShipmentItem::class);
    }

    /**
     * @return HasOne
     */
    public function shipmentPackageItem(): HasOne
    {
        return $this->hasOne(ShipmentPackageItem::class);
    }

    /**
     * @param array $data
     * @throws Exception
     */
    public function setDataByType(array $data = []): void
    {
        if($this->type == Basket::TYPE_PRODUCT) {
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $offerInfo = $offerService->offerInfo($this->offer_id);
            $offerStocks = $offerInfo->stocks->keyBy('store_id');
            if ((isset($data['product']['store_id']) && (!$offerStocks->has($data['product']['store_id']) || $offerStocks[$data['product']['store_id']]->qty <= 0)) || $offerStocks->isEmpty()) {
                throw new BadRequestHttpException('offer without stocks');
            }
            $this->name = $offerInfo->name;
            $this->product = array_merge($this->product, [
                'weight' => $offerInfo->weight,
                'width' => $offerInfo->width,
                'height' => $offerInfo->height,
                'length' => $offerInfo->length,
            ]);
            if (!isset($this->product['store_id'])) {
                $product = $this->product;
                $product['store_id'] = $offerInfo->stocks->sortBy('qty')[0]->store_id;
                $this->product = $product;
            }
        } else {
            throw new Exception('Masterclass type is not supported yet...');
        }
    }
}
