<?php

namespace App\Models\Cart;

use App\Models\Catalog\Product;
use App\Models\Checkout\Certificate;
use App\Models\Checkout\CheckoutDataDto;
use App\Models\Checkout\CheckoutInputDto;
use App\Models\Image;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Greensight\Marketing\Dto\Basket\BasketInDto as PriceInBasket;
use Greensight\Marketing\Services\PriceService\PriceService;
use Greensight\Oms\Dto\BasketDto;
use Greensight\Oms\Dto\BasketItemDto;
use Greensight\Oms\Services\BasketService\BasketService;
use Illuminate\Session\Store;
use Illuminate\Support\Collection;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Offer\OfferSaleStatus;
use Pim\Dto\Product\ProductArchiveStatus;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

class CartManager
{
    /** @var RequestInitiator */
    private $user;
    /** @var ProductService */
    private $productService;
    /** @var OfferService */
    private $offerService;
    /** @var PriceService */
    private $priceService;
    /** @var BasketService */
    private $basketService;
    /** @var BasketDto */
    private $basket;
    
    public static function certificateByCode(string $code): ?Certificate
    {
        if ($code == 'CERT2020-500') {
            return new Certificate(1, $code, 500);
        }
        if ($code == 'CERT2019-1000') {
            return new Certificate(2, $code, 1000);
        }
        return null;
    }
    
    public function __construct(RequestInitiator $user)
    {
        $this->productService = resolve(ProductService::class);
        $this->offerService = resolve(OfferService::class);
        $this->priceService = resolve(PriceService::class);
        $this->basketService = resolve(BasketService::class);
        $this->user = $user;
        $this->basket = $this->basketService->getByUser($user->userId(), 1, true);
    }
    
    public function originalBasket(): BasketDto
    {
        return $this->basket;
    }
    
    public function addItem(int $offerId, ?int $count = null)
    {
        $customerId = $this->user->userId();
        /** @var BasketDto $basket */
        $basket = $this->basketService->getByUser($customerId, 1, true);
        $item = $basket->itemByOffer($offerId);
        if ($count !== null) {
            if ($count === 0) {
                $item->markToDelete();
            } else {
                $item->qty = $count;
            }
        } else {
            $item->increment();
        }
        
        $this->basketService->setItem($basket->id, $offerId, $item, false);
    }
    
    public function deleteItem($offerId)
    {
        $customerId = $this->user->userId();
        ///** @var BasketDto $basket */
        //$basket = $this->basketService->getByUser($customerId, 1, true);
        $item = $this->basket->itemByOffer($offerId);
        $item->markToDelete();
        $this->basketService->setItem($this->basket->id, $offerId, $item, false);
    }
    
    /**
     * Сформировать данные о корзине.
     * @param CheckoutInputDto|null $input - данные чекаута влияющие на расчёт стоимости заказа
     * @return Cart
     * @todo передавать данные чекаута в мс маркетинга для расчёта бонусов/сертификатов/прочих скидок
     */
    public function getCart(?CheckoutInputDto $input = null): Cart
    {
        $customerId = $this->user->userId();
        ///** @var BasketDto $basket */
        //$basket = $this->basketService->getByUser($customerId, 1, true);
        $bundles = $this->loadProducts($this->basket);
        $cart = new Cart();
        $cart->setBasket($this->basket->id, Cart::TYPE_PRODUCT);
        if ($bundles) {
            $basketDataForPrice = new PriceInBasket($customerId);
            foreach ($bundles as ['basketItem' => $item]) {
                $basketDataForPrice->addItem($item->id, $item->offer_id, $item->qty);
            }
            $prices = $this->priceService->calculateBasketPrice($basketDataForPrice);
            
            $cart->setCheckout(Cart::TYPE_PRODUCT, $prices->cost, $prices->discount, 0);
            foreach ($prices->items as $itemPrice) {
                [
                    'offer' => $offerDto,
                    'product' => $productDto,
                    'image' => $imageDto,
                    'basketItem' => $item
                ] = $bundles[$itemPrice->id];
        
                $product = new Product($offerDto->id);
                $product->setName($productDto->name)
                    ->setPrice($itemPrice->price)
                    ->setOldPrice($itemPrice->totalCost)
                    ->setCode($productDto->code)
                    ->setRating(5)
                    ->setTags([]);
                if ($imageDto) {
                    $image = Image::createFromParams($imageDto['id'], Image::extractExtension($imageDto['url']));
                    $product->setImage($image->toFront());
                }
                $cart->addItem(Cart::TYPE_PRODUCT, $item->id, $item->qty, $product);
            }
        } else {
            $cart->setCheckout(Cart::TYPE_PRODUCT, 0, 0, 0);
        }
        
        return $cart;
    }
    
    private function loadProducts(BasketDto $basket)
    {
        $items = $basket->items();
        if (!$items) {
            return [];
        }
        /** @var Collection|BasketItemDto[] $items */
        $items = $items->keyBy('offer_id');
        $offerIds = $items->keys()->all();
        $query = $this->offerService->newQuery();
        $query
            ->addFields(OfferDto::entity(), 'id', 'product_id')
            ->setFilter('sale_status', [OfferSaleStatus::STATUS_ON_SALE, OfferSaleStatus::STATUS_PRE_ORDER])
            ->setFilter('id', $offerIds);
        /** @var Collection|OfferDto[] $offers */
        $offers = $this->offerService->offers($query);
        
        $productIds = $offers->pluck('product_id')->all();
        $query = $this->productService->newQuery();
        $query
            ->addFields(ProductDto::entity(), 'id', 'brand_id', 'category_id', 'name')
            ->include('properties')
            ->setFilter('archive', ProductArchiveStatus::NO_ARCHIVE)
            ->setFilter('id', $productIds);
        /** @var Collection|ProductDto[] $products */
        $products = $this->productService->products($query)->keyBy('id');
        /** @var Collection $images */
        $images = $this->productService->allImages($productIds, 1)->keyBy('productId');
        
        $result = [];
        foreach ($offers as $offer) {
            if (!$products->has($offer->product_id)) {
                continue;
            }
            $result[$items[$offer->id]->id] = [
                'offer' => $offer,
                'product' => $products[$offer->product_id],
                'image' => $images[$offer->product_id] ?? null,
                'basketItem' => $items[$offer->id]
            ];
        }
        return $result;
    }
}
