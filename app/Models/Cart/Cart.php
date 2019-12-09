<?php
namespace App\Models\Cart;

use App\Models\Checkout\CheckoutDataDto;
use App\Models\Checkout\CheckoutSummaryDto;
use Greensight\Oms\Dto\ItemPricesDto;
use Greensight\Oms\Services\BasketService\BasketService;

class Cart implements \JsonSerializable
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_MASTER = 'masterclass';
    
    private $baskets = [];
    /** @var CartItem[] */
    private $items = [];
    private $alerts = [];
    /** @var CartCheckout[] */
    private $checkout = [];
    
    public function addItem(string $type, int $id, int $count, $product)
    {
        $this->items[] = new CartItem($id, $type, $count, $product);
    }
    
    public function setCheckout(string $type, int $productsCost, $cartDiscount, $promoDiscount)
    {
        // todo бонусы должны приходить из маркетинга
        $this->checkout[$type] = new CartCheckout($productsCost, $cartDiscount, $promoDiscount, ceil($productsCost/100));
    }
    
    public function setBasket(int $basketId, string $type)
    {
        $this->baskets[$type] = $basketId;
    }
    
    public function getCheckout(string $type)
    {
        return $this->checkout[$type] ?? null;
    }
    
    public function getBasketId(string $type): ?int
    {
        return $this->baskets[$type] ?? null;
    }
    
    /**
     * @param string $type
     * @return CartItem[]
     */
    public function getItems(string $type): array
    {
        return array_filter($this->items, function (CartItem $item) use ($type) {
            return $item->is($type);
        });
    }
    
    public function summary(string $type): CheckoutSummaryDto
    {
        $summary = new CheckoutSummaryDto();
        $cartCheckout = $this->checkout[$type];
        // тут немного халтуры
        // как бы надо по максимум из корзины данные в ответ копировать
        // но корзина сама наполнена данными из этого объекта
        // поэтому пока копируем только результаты расчётов
        $summary->cartCost = $cartCheckout->cost;
        $summary->cartDiscount = $cartCheckout->cartDiscount;
        
        return $summary;
    }
    
    public function commitPrices(BasketService $basketService)
    {
        $prices = new ItemPricesDto();
        foreach ($this->items as $item) {
            [$offerId, $cost, $price] = $item->product->marketValues();
            $prices->addItem($offerId, $cost, $price);
        }
        
        $basketService->commitItemPrice($this->getBasketId(Cart::TYPE_PRODUCT), $prices);
    }
    
    public function jsonSerialize()
    {
        $result = [];
        $productItems = $this->getItems('product');
        if (count($productItems)) {
            $result[self::TYPE_PRODUCT] = [
                'id' => 1,
                'type' => self::TYPE_PRODUCT,
                'alerts' => $this->alerts,
                'summary' => $this->checkout[self::TYPE_PRODUCT] ?? [],
                'items' => $productItems
            ];
        }
    
//        $masterclassItems = $this->getItems('masterclass');
//        if (count($productItems)) {
//            $result[self::TYPE_MASTER] = [
//                'id' => 1,
//                'type' => self::TYPE_MASTER,
//                'summary' => $this->checkout[self::TYPE_MASTER] ?? [],
//                'items' => $masterclassItems
//            ];
//        }
        return $result;
    }
}
