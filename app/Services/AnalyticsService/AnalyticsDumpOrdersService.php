<?php

namespace App\Services\AnalyticsService;

use App\Http\Requests\AnalyticsRequest;
use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Dto\Lists\CityDto;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Greensight\Logistics\Dto\Lists\DeliveryServiceStatus;
use Greensight\Logistics\Dto\Lists\PointDto;
use Greensight\Logistics\Services\ListsService\ListsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use DateTime;
use Exception;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use Pim\Dto\BrandDto;
use Pim\Dto\CategoryDto;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductByOfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\BrandService\BrandService;
use Pim\Services\CategoryService\CategoryService;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

class AnalyticsDumpOrdersService
{
    public function __construct()
    {
        ini_set('memory_limit', '256M');
    }

    public function dumpOrders(AnalyticsRequest $request): array
    {
        if ($request->filter) {
            return [
                'results' => [],
                '__count' => $this->getCountRow($request),
            ];
        }

        $ordersQuery = Order::query()
            ->select([
                DB::raw('basket_items.id AS id'),
                DB::raw('orders.id AS orderId'),
                DB::raw('MAX(payments.payment_type) AS paymentType'),
                DB::raw('DATE_FORMAT(orders.created_at, \'%Y-%m-%d\') AS createdAt'),
                DB::raw('MAX(DATE_FORMAT(payments.payed_at, \'%Y-%m-%d\')) AS payedAt'),
                DB::raw('basket_items.qty AS qty'),
                DB::raw('basket_items.cost AS cost'),
                DB::raw('basket_items.price AS price'),
                DB::raw('GROUP_CONCAT(DISTINCT order_discounts.name SEPARATOR \' | \') AS discounts'),
                DB::raw('GROUP_CONCAT(DISTINCT shipments.number SEPARATOR \', \') AS shipmentsNumber'),
                DB::raw('GROUP_CONCAT(DISTINCT delivery.delivery_service SEPARATOR \', \') AS deliveryService'),
                DB::raw('MIN(DATE_FORMAT(delivery.delivery_at, \'%Y-%m-%d\')) AS deliveryAt'),
                DB::raw('MAX(DATE_FORMAT(delivery.delivered_at, \'%Y-%m-%d\')) AS deliveredAt'),
                DB::raw('MAX(DATE_FORMAT(delivery.status_at, \'%Y-%m-%d\')) AS deliveryStatusAt'),
                DB::raw('MAX(delivery.point_id) AS pointId'),
                DB::raw('orders.type AS orderType'),
                DB::raw('orders.status AS orderStatus'),
                DB::raw('orders.payment_status AS paymentStatus'),
                DB::raw('orders.type AS orderTypeName'),
                DB::raw('orders.status AS orderStatusName'),
                DB::raw('orders.payment_status AS paymentStatusName'),
                DB::raw('orders.customer_id AS customerId'),
                DB::raw('basket_items.offer_id AS offerId'),
                DB::raw('basket_items.name AS productName'),
            ])
            ->join(DB::raw('basket_items'), function($join) {
                $join->on('orders.basket_id', '=', 'basket_items.basket_id');
            })
            ->leftJoin(DB::raw('payments'), function($join) {
                $join->on('orders.id', '=', 'payments.order_id');
            })
            ->leftJoin(DB::raw('order_discounts'), function($join) {
                $join->on('orders.id', '=', 'order_discounts.order_id');
            })
            ->leftJoin(DB::raw('shipment_items'), function($join) {
                $join->on('shipment_items.basket_item_id', '=', 'basket_items.id');
            })
            ->leftJoin(DB::raw('shipments'), function($join) {
                $join->on('shipments.id', '=', 'shipment_items.shipment_id');
            })
            ->leftJoin(DB::raw('delivery'), function($join) {
                $join->on('shipments.delivery_id', '=', 'delivery.id');
            })

            ->where('orders.is_canceled', '=', 0)
            ->where('orders.created_at', '>=', $request->createdStart)
            ->where('orders.created_at', '<=', $request->createdEnd)
            ->groupBy('orders.id', 'basket_items.id');

        if ($request->paymentStatus) {
            $ordersQuery->whereIn('orders.payment_status', explode(",", $request->paymentStatus));
        }

        if ($request->top) {
            $ordersQuery->limit($request->top);
        }
        if ($request->skip) {
            $ordersQuery->offset($request->skip);
        }
        if ($request->orderBy) {
            $ordersBy = explode(' ', $request->orderBy);
            $ordersQuery->orderBy($ordersBy[0] ?? 'orderId', $ordersBy[1] ?? 'asc');
        } else {
            $ordersQuery
                ->orderBy('orderId')
                ->orderBy('shipmentsNumber')
            ;
        }

        $orders = $ordersQuery->get();

        $results = [];

        $offersIds = $orders->pluck('offerId')->unique()->all();
        $productsByOffers = $this->getProductsByOffers($offersIds);

        $deliveryServiceIds = $orders->pluck('deliveryService')->unique()->all();
        $deliveryServices = $this->getDeliveryServices();

        $merchantsId = [];
        $categoriesId = [];
        $brandsId = [];

        foreach ($productsByOffers->toArray() as $item) {
            $merchantId = $item['offer']['merchant_id'] ?? null;
            $categoryId = $item['product']['category_id'] ?? null;
            $brandId = $item['product']['brand_id'] ?? null;

            if ($merchantId) {
                $merchantsId[$merchantId] = $merchantId;
            }
            if ($categoryId) {
                $categoriesId[$categoryId] = $categoryId;
            }
            if ($brandId) {
                $brandsId[$brandId] = $brandId;
            }
        }

        $merchants = $this->getMerchants($merchantsId);
        $categories = $this->getCategories($categoriesId);
        $brands = $this->getBrands($brandsId);

        $customersIds = $orders->pluck('customerId')->unique()->all();
        $customers = $this->getCustomers($customersIds);
        //$usersIds = $customers->pluck('user_id')->unique()->all();
        //$users = $this->getUsers($usersIds);
        $pointsIds = $orders->pluck('pointId')->unique()->all();
        $points = $this->getPoints($pointsIds);
        $cityGuids = $points->pluck('city_guid')->unique()->all();
        $cities = $this->getCities($cityGuids);

        foreach ($orders->toArray() as $item) {
            $item['qty'] = (float) $item['qty'];
            $item['cost'] = (float) $item['cost'];
            $item['price'] = (float) $item['price'];

            $item['orderStatusName'] = OrderStatus::all()[$item['orderStatus']]->name ?? null;
            $item['paymentStatusName'] = PaymentStatus::allByKey()[$item['paymentStatus']]->name ?? null;
            $item['orderTypeName'] = $this->getOrderTypeName($item['orderType']);

            $item['overdueDays'] = null;
            if ($item['deliveryAt'] && $item['orderType'] == Basket::TYPE_PRODUCT) {

                if (!$item['deliveredAt'] && $item['orderStatus'] == OrderStatus::DONE) {
                    $item['deliveredAt'] = $item['deliveryStatusAt'];
                }

                if ($item['deliveredAt'] > $item['deliveryAt']) {
                    try {
                        if ($item['deliveryAt'] <= (new DateTime($item['deliveredAt']))->format('Y-m-d')) {
                            $item['overdueDays'] = (new DateTime($item['deliveredAt']))->diff(new DateTime($item['deliveryAt']))->format("%a");
                        }
                    } catch (Exception) {
                    }
                } else if (!$item['deliveredAt'] && $item['orderStatus'] !== OrderStatus::DONE) {
                    try {
                        if ($item['deliveryAt'] <= (new DateTime())->format('Y-m-d')) {
                            $item['overdueDays'] = (new DateTime())->diff(new DateTime($item['deliveryAt']))->format("%a");
                        }
                    } catch (Exception) {
                    }
                }
            }

            /** @var ProductDto|null $product */
            $product = $productsByOffers[$item['offerId']]['product'] ?? null;
            if ($product) {
                /** @var CategoryDto $category */
                $category = $categories[$product->category_id] ?? null;
                /** @var BrandDto $brand */
                $brand = $brands[$product->brand_id] ?? null;

                $item['brandId'] = $product->brand_id ?? null;
                $item['brandName'] = $brand->name ?? null;
                $item['categoryId'] = $product->category_id ?? null;
                $item['categoryName'] = $category->name ?? null;
                $item['vendorCode'] = $product->vendor_code ?? null;
                $item['productId'] = $product->id ?? null;
                if ($item['productName'] !== $product->name) {
                    $optionsValue = str_replace($product->name, '', $item['productName']);
                    $item['optionsValue'] = $optionsValue;
                    //$item['productName'] = $product->name;
                }
            }

            /** @var OfferDto|null $offer */
            $offer = $productsByOffers[$item['offerId']]['offer'] ?? null;
            if ($offer) {
                $merchant = $merchants[$offer->merchant_id] ?? null;

                $item['merchantId'] = $offer->merchant_id ?? null;
                $item['merchantName'] = $merchant->legal_name ?? null;
            }

            /** @var CustomerDto|null $customer */
            $customer = $customers[$item['customerId']] ?? null;

            if ($customer) {
                unset($item['customerId']);
                $item['customerId'] = $customer->id ?? null;
                $customerRegistration = $customer->created_at ?? null;
                $item['customerRegistration'] = $customerRegistration ? (new DateTime($customerRegistration))->format('Y-m-d') : null;
                $item['userId'] = $customer->user_id ?? null;

                /** @var UserDto|null $user */
                //$user = $users[$customer->user_id] ?? null;
                //$item['phone'] = $user->phone ?? null;
                //$item['email'] = $user->email ?? null;
            }

            /** @var PointDto|null $point */
            $point = $points[$item['pointId']] ?? null;
            if ($point) {
                //$item['cityGuid'] = $point->city_guid ?? null;
                /** @var CityDto $city */
                $city = $cities[$point->city_guid] ?? null;
                $item['cityName'] = $city->name ?? null;
            }

            $deliveryServiceIds = explode(",", $item['deliveryService']);
            $item['deliveryService'] = null;

            foreach ($deliveryServiceIds as $id) {
                /** @var DeliveryService|null $deliveryService */
                $deliveryService = $deliveryServices[$id] ?? null;
                if ($deliveryService instanceof DeliveryService) {
                    $item['deliveryService'] = ($item['deliveryService'] ? ', ' : '') . $deliveryService->name;
                }
            }

            $results[] = $item;
        }

        return [
            'results' => array_values($results),
            '__count' => $this->getCountRow($request),
        ];
    }

    private function getCountRow(AnalyticsRequest $request): int
    {
        if ($request->count) {
            $ordersQuery = Order::query()
                ->select([
                    DB::raw('COUNT(DISTINCT basket_items.id) AS count'),
                ])
                ->join(DB::raw('basket_items'), function ($join) {
                    $join->on('orders.basket_id', '=', 'basket_items.basket_id');
                })
                ->where('orders.is_canceled', '=', 0)
                ->where('orders.created_at', '>=', $request->createdStart)
                ->where('orders.created_at', '<=', $request->createdEnd);

            if ($request->paymentStatus) {
                $ordersQuery->whereIn('orders.payment_status', explode(",", $request->paymentStatus));
            }

            $orders = $ordersQuery->get();

            return ($orders->toArray())[0]['count'] ?? 0;
        }

        return 0;
    }

    private function getProductsByOffers(array $offersIds): Collection
    {
        $offersIds = array_unique($offersIds);

        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);

        $productsByOffers = collect();
        $offersIds = array_values(array_unique($offersIds));

        foreach (array_chunk($offersIds, 500) as $chunkedOffersIds) {
            $offersQuery = $offerService->newQuery()
                ->setFilter('id', $chunkedOffersIds)
                ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id', 'xml_id');
            $offers = $offerService->offers($offersQuery);

            $productsIds = $offers->pluck('product_id')->toArray();
            $productQuery = $productService->newQuery()
                ->addFields(ProductDto::entity(), 'id', 'vendor_code', 'name', 'brand_id', 'category_id')
                ->setFilter('id', $productsIds);
            $products = $productService->products($productQuery)->keyBy('id');

            foreach ($offers as $offer) {
                //if (!isset($products[$offer->product_id])) {
                //    continue;
                //}

                $productByOffer = new ProductByOfferDto();
                $productByOffer->offer = $offer;
                $productByOffer->product = $products[$offer->product_id] ?? null;
                $productsByOffers->put($offer->id, $productByOffer);
            }
        }

        return $productsByOffers;
    }

    private function getDeliveryServices(): Collection
    {
        /** @var ListsService $listService */
        $listService = resolve(ListsService::class);
        $deliveryServiceQuery = $listService->newQuery();

        $deliveryServices = $listService->deliveryServices($deliveryServiceQuery)->keyBy('id')->all();

        return collect($deliveryServices);
    }

    private function getMerchants(array $merchantsIds): Collection
    {
        $results = collect();

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);

        foreach (array_chunk(array_unique($merchantsIds), 500) as $chunkedMerchantsIds) {
            $merchantQuery = $merchantService->newQuery()
                ->addFields(MerchantDto::entity(), 'id', 'legal_name')
                ->setFilter('id', $chunkedMerchantsIds);

            try {
                $merchants = $merchantService->merchants($merchantQuery)->keyBy('id');
            } catch (Exception) {
                continue;
            }

            /** @var MerchantDto $merchant */
            foreach ($merchants as $merchant) {
                $results->put($merchant->id, $merchant);
            }
        }

        return $results;
    }

    private function getCategories(array $categoriesId): Collection
    {
        $results = collect();

        /** @var CategoryService $categoryService */
        $categoryService = resolve(CategoryService::class);

        foreach (array_chunk(array_unique($categoriesId), 500) as $chunkedCategoriesIds) {
            $categoryQuery = $categoryService->newQuery()
                ->addFields(CategoryDto::entity(), 'id', 'name')
                ->setFilter('id', $chunkedCategoriesIds);

            try {
                $categories = $categoryService->categories($categoryQuery)->keyBy('id');
            } catch (Exception) {
                continue;
            }

            /** @var CategoryDto $category */
            foreach ($categories as $category) {
                $results->put($category->id, $category);
            }
        }

        return $results;
    }

    private function getBrands(array $brandsIds): Collection
    {
        $results = collect();

        /** @var BrandService $brandService */
        $brandService = resolve(BrandService::class);

        foreach (array_chunk(array_unique($brandsIds), 500) as $chunkedBrandsIds) {
            $brandQuery = $brandService->newQuery()
                ->addFields(BrandDto::entity(), 'id', 'name')
                ->setFilter('id', $chunkedBrandsIds);

            try {
                $brands = $brandService->brands($brandQuery)->keyBy('id');
            } catch (Exception) {
                continue;
            }

            /** @var BrandDto $brand */
            foreach ($brands as $brand) {
                $results->put($brand->id, $brand);
            }
        }

        return $results;
    }

    private function getCustomers(array $customersIds): Collection
    {
        $results = collect();

        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);

        foreach (array_chunk(array_unique($customersIds), 500) as $chunkedCustomersIds) {
            $customerQuery = $customerService->newQuery()
                ->addFields(CustomerDto::entity(), 'id', 'created_at', 'user_id')
                ->setFilter('id', $chunkedCustomersIds);

            try {
                $customers = $customerService->customers($customerQuery);
            } catch (Exception) {
                continue;
            }

            /** @var CustomerDto $customer */
            foreach ($customers as $customer) {
                $results->put($customer->id, $customer);
            }
        }

        return $results;
    }

    private function getUsers(array $usersIds): Collection
    {
        $results = collect();

        /** @var UserService $userService */
        $userService = resolve(UserService::class);

        foreach (array_chunk(array_unique($usersIds), 500) as $chunkedUsersIds) {
            $userQuery = $userService->newQuery()
                ->addFields(CustomerDto::entity(), 'id', 'phone', 'email')
                ->setFilter('id', $chunkedUsersIds);

            try {
                $users = $userService->users($userQuery)->keyBy('id');
            } catch (Exception) {
                continue;
            }

            /** @var UserDto $user */
            foreach ($users as $user) {
                $results->put($user->id, $user);
            }
        }

        return $results;
    }

    private function getPoints(array $pointsIds): Collection
    {
        $pointsIds = array_unique($pointsIds);

        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);
        $pointQuery = $listsService->newQuery()
            ->setFilter('id', $pointsIds)
            ->addFields(PointDto::entity(), 'id', 'city_guid');

        return $listsService->points($pointQuery)->keyBy('id');
    }

    private function getCities(array $cityGuids): Collection
    {
        $cityGuids = array_unique($cityGuids);

        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);
        $citiesQuery = $listsService->newQuery()
            ->setFilter('guid', $cityGuids)
            ->addFields(CityDto::entity(), 'name', 'guid');

        return $listsService->cities($citiesQuery)->keyBy('guid');
    }

    private function getOrderTypeName(?int $orderType): ?string
    {
        return match ($orderType) {
            Basket::TYPE_PRODUCT => 'Товар',
            Basket::TYPE_MASTER => 'МК',
            Basket::TYPE_CERTIFICATE => 'Сертификат',
            default => null,
        };
    }
}
