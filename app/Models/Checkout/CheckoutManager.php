<?php

namespace App\Models\Checkout;

use App\Models\Cart\Cart;
use App\Models\Cart\CartManager;
use Carbon\Carbon;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketInput\DeliveryBasketInputDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketInput\DeliveryStoreDto;
use Greensight\Logistics\Dto\Calculator\DeliveryBasketItemDto;
use Greensight\Logistics\Dto\Lists\DeliveryMethod as LogisticsDeliveryMethod;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Greensight\Logistics\Services\CalculatorService\CalculatorService;
use Greensight\Oms\Dto\Delivery\ShipmentDto;
use Greensight\Oms\Dto\DeliveryType as OmsDeliveryType;
use Greensight\Oms\Dto\OrderDto;
use Greensight\Oms\Dto\Payment\PaymentDto;
use Greensight\Oms\Dto\Payment\PaymentSystem;
use Greensight\Oms\Services\BasketService\BasketService;
use Greensight\Oms\Services\OrderService\OrderService;
use Greensight\Oms\Services\PaymentService\PaymentService;
use Greensight\Oms\Services\ShipmentService\ShipmentService;

class CheckoutManager
{
    const RECEIVE_METHOD_DELIVERY = 1;
    const RECEIVE_METHOD_PICKUP = 3;
    /** @var CartManager */
    private $cartManager;
    /** @var string */
    private $cartType;
    /** @var CalculatorService */
    private $calculatorService;
    
    public function __construct(RequestInitiator $user, string $cartType)
    {
        $this->cartManager = new CartManager($user);
        $this->cartType = $cartType;
        $this->calculatorService = resolve(CalculatorService::class);
    }
    
    public function commit(RequestInitiator $user, CheckoutDataDto $checkout): ?string
    {
        $orderService = resolve(OrderService::class);
        $paymentService = resolve(PaymentService::class);
        $basketService = resolve(BasketService::class);
        
        $cart = $this->cartManager->getCart($checkout->input);
        $cart->commitPrices($basketService);
        
        /** @var OrderDto $order */
        $order = $this->saveGeneralOrderData($user, $checkout, $cart, $orderService);
        
        $url = null;
        if ($order) {
            //$this->createShipments($checkout, $order);
            $payment = $this->createPayment($checkout, $order, $orderService, $paymentService);
            if ($payment) {
                $url = $paymentService->start($payment->id, 'https://master-front.ibt-mas.greensight.ru/');
            } else {
                $order = new OrderDto();
                $order->is_problem = 1;
                $order->manager_comment = 'При оформлении не создалась оплата';
                $orderService->updateOrder($order->id, $order);
            }
        }

        return $url;
    }
    
    public function addUnconditionalData(CheckoutInputDto $input, RequestInitiator $user)
    {
        // 1 add user specific data
        if (!$input->addresses) {
            $input->addresses = [
                new UserAddress(1, "г Москва, ул Красная, дом 45", 'some-fias-guid-1'),
                new UserAddress(2, "г зеленоград, корпус 305, этаж 3", 'some-fias-guid-2'),
            ];
        }
        
        if (!$input->recipients) {
            $input->recipients = [
                new Recipient(1, 'Пётр', '79995554422', 'petya@mail.com'),
                //new Recipient(2, 'Василий Геннадьевич Друзь', '799961278219', 'druz@www.xyz'),
            ];
        }
    
        $choice = new CheckoutDataDto();
        $choice->input = $input;
        
        $choice->availableBonus = 441;
        
        // 2 add static choices data
        
        $choice->paymentMethods = [
            new PaymentMethod(1, 'Банковской картой онлайн', 'card')
        ];
        
        $choice->confirmationTypes = [
            new ConfirmationType(1, 'Подтвердить заказ по SMS', 'sms'),
            new ConfirmationType(2, 'Подтвердить заказ через звонок оператора', 'call'),
        ];
        
        return $choice;
    }
    
    public function selectDefaultValues(CheckoutDataDto $checkout)
    {
        if (!$checkout->input->paymentMethodID) {
            $checkout->input->paymentMethodID = $checkout->paymentMethods ? $checkout->paymentMethods[0]->id : 0;
        }
        if (!$checkout->input->confirmationTypeID) {
            $checkout->input->confirmationTypeID = $checkout->confirmationTypes ? $checkout->confirmationTypes[0]->id : 0;
        }
        
        if (!$checkout->input->recipient) {
            $checkout->input->recipient = $checkout->input->recipients ? $checkout->input->recipients[0] : null;
        }
        
        return $checkout;
    }
    
    public function setSelectedLocation(CheckoutDataDto $checkout)
    {
        if ($checkout->input->receiveMethodID == self::RECEIVE_METHOD_PICKUP) {
            $checkout->input->address = null;
        } else {
            $checkout->input->pickupPoint = null;
            if (!$checkout->input->address && $checkout->input->addresses) {
                $checkout->input->address = $checkout->input->addresses[0];
            }
        }
        return $checkout;
    }
    
    public function addAvailableDeliveryOptions(CheckoutDataDto $checkout, Cart $cart)
    {
        $checkout = $this->findDeliveryMethods($checkout, $cart);
        
        if ($checkout->input->pickupPoint) {
            $checkout->input->pickupPoint = $checkout->pickupById($checkout->input->pickupPoint->id);
        }
        
        // if no receive method selected, select first
        if (!$checkout->input->receiveMethodID && $checkout->receiveMethods) {
            $checkout->input->receiveMethodID = $checkout->receiveMethods[0]->id;
        }
        // if selected only receive method, select first option by default
        if (!$checkout->input->deliveryMethodID) {
            $receiveMethod = $checkout->receiveMethodById($checkout->input->receiveMethodID);
            if ($receiveMethod && isset($receiveMethod->methods[0])) {
                $checkout->input->deliveryMethodID = $receiveMethod->methods[0]['id'];
            }
        }
        
        $checkout = $this->findDeliveryTypes($checkout, $cart);
        if ($checkout->deliveryTypes) {
            $inputType = $checkout->input->deliveryType;
            if ($inputType) {
                $originalType = $checkout->deliveryTypeById($inputType->id);
                if ($originalType) {
                    foreach ($originalType->items as $originalShipment) {
                        $inputShipment = $inputType->shipmentById($originalShipment->id);
                        if ($inputShipment && in_array($inputShipment->selectedDate, $originalShipment->availableDates)) {
                            $originalShipment->selectedDate = $inputShipment->selectedDate;
                        }
                    }
                    $checkout->input->deliveryType = $originalType;
                }
            }
            if (!$checkout->input->deliveryType) {
                $checkout->input->deliveryType = $checkout->deliveryTypes[0];
            }
        }
        
        return $checkout;
    }
    
    public function getCart(CheckoutInputDto $input)
    {
        return $this->cartManager->getCart($input);
    }
    
    public function calculateSummary(CheckoutDataDto $checkout, Cart $cart)
    {
        $summary = $cart->summary(Cart::TYPE_PRODUCT);
        if ($checkout->input->receiveMethodID) {
            $method = $checkout->receiveMethodById($checkout->input->receiveMethodID);
            if ($method) {
                $summary->deliveryCost = $method->price;
            }
        }
        // todo унести расчёт скидок в маркетинг
        $summary->bonusDiscount = $checkout->input->bonus;
        $summary->spentBonus = $checkout->input->bonus;
        $summary->newBonus = ceil($summary->cartCost / 100);
        
        switch ($checkout->input->promocode) {
            case 'ADMITAD700':
                $summary->promoDiscount += 500;
                break;
            default:
                $checkout->input->promocode = null;
        }
        
        foreach ($checkout->input->certificates as $i => $cert) {
            $summary->certDiscount += $cert->amount;
        }
        
        $checkout->summary = $summary;
        return $checkout;
    }
    
    private function findDeliveryMethods(CheckoutDataDto $checkout, Cart $cart): CheckoutDataDto
    {
        //$iots = new CalculatedDeliveryOptions($checkout, $this->cartManager);
        //$iots->deliveries();
    
        
//        if ($checkout->input->address) {
//            $cityId = $checkout->input->address->cityId;
//        } else {
//            // todo сделать чтобы в случае отсутсвия адресов в ЛК выбирался город геолокации
//            $cityId = 'user\'s geolocation city fias id';
//        }
        
        // todo тут надо из калькулятора получить варианты доставок
        
        $methodDelivery = new DeliveryMethod(self::RECEIVE_METHOD_DELIVERY, 'Доставка курьером');
        $methodDelivery->setInfo(500, 'Ближайшая доставка в понедельник, 29 декабря');
        $methodDelivery->addOption(LogisticsDeliveryMethod::METHOD_DELIVERY, 'Доставка');
        
        $methodPickup = new DeliveryMethod(self::RECEIVE_METHOD_PICKUP, 'Самовывоз из 12 пунктов');
        $methodPickup->setInfo(200, 'Ближайший самовывоз в четверг, 3 декабря');
        $methodPickup->addOption(LogisticsDeliveryMethod::METHOD_OUTPOST_PICKUP, 'Outpost');
        $methodPickup->addOption(LogisticsDeliveryMethod::METHOD_POSTOMAT_PICKUP, 'Postomat');
        
        $checkout->receiveMethods = [
            $methodDelivery,
            $methodPickup
        ];
        
        if ($checkout->input->receiveMethodID == self::RECEIVE_METHOD_PICKUP) {
            $pickup = new PickupPoint(1, LogisticsDeliveryMethod::METHOD_OUTPOST_PICKUP, 'Пункт выдачи посылок', 'Банковские карты, наличные');
            $pickup->setDescription(
                'г. Москва, ул. Стратонавтов, д. 11',
                "Остановка — Физтех-лицей.\nПримерное расстояние от остановки до отделения — 200 м.\nОтделение расположено в 19-ти этажном доме. Расположение входа в отделение — нежилое помещение со стороны улицы, секция ближе к круглым домам.",
                '+7 800 222-80-00',
                [55.82737306892227, 37.43724449999994]
            );
            $pickup->setDate('Можно забрать с 26 июня, среда');
            $pickup->addScheduleItem(1, 'Будни', '10:00 — 20:00');
            $pickup->addScheduleItem(2, 'Суббота', '10:00 — 18:00');
            $pickup->addScheduleItem(3, 'Воскресенье', '10:00 — 15:00');
            $checkout->pickupPoints[] = $pickup;
    
            $pickup = new PickupPoint(2, LogisticsDeliveryMethod::METHOD_POSTOMAT_PICKUP, 'Пункт выдачи посылок', 'Банковские карты, наличные');
            $pickup->setDescription(
                'г. Москва, ул. Пятницкая, д. 3/4, корп. 2',
                "Остановка — Физтех-лицей.\nПримерное расстояние от остановки до отделения — 200 м.\nОтделение расположено в 19-ти этажном доме. Расположение входа в отделение — нежилое помещение со стороны улицы, секция ближе к круглым домам.",
                '+7 800 333-11-33',
                [55.734862141870614, 37.613880184687815]
            );
            $pickup->setDate('Можно забрать с 26 июня, среда');
            $pickup->addScheduleItem(1, 'Будни', '10:00 — 20:00');
            $pickup->addScheduleItem(2, 'Воскресенье', '10:00 — 15:00');
            $checkout->pickupPoints[] = $pickup;
    
            $pickup = new PickupPoint(3, LogisticsDeliveryMethod::METHOD_POSTOMAT_PICKUP, 'Пункт выдачи посылок', 'Банковские карты, наличные');
            $pickup->setDescription(
                'г. Москва, ул. Стратонавтов, д. 11',
                "Остановка — Физтех-лицей.\nПримерное расстояние от остановки до отделения — 200 м.\nОтделение расположено в 19-ти этажном доме. Расположение входа в отделение — нежилое помещение со стороны улицы, секция ближе к круглым домам.",
                '+7 800 222-80-00',
                [55.74551356898018, 37.627750499999976]
            );
            $pickup->setDate('Можно забрать с 26 июня, среда');
            $pickup->addScheduleItem(1, 'Будни', '10:00 — 15:00');
            $checkout->pickupPoints[] = $pickup;
            
        }
        
        return $checkout;
    }
    
    private function findDeliveryTypes(CheckoutDataDto $checkout, Cart $cart)
    {
        if ( (!$checkout->input->receiveMethodID) ||
            $checkout->input->receiveMethodID == self::RECEIVE_METHOD_DELIVERY ||
            ($checkout->input->receiveMethodID == self::RECEIVE_METHOD_PICKUP && $checkout->input->pickupPoint)
        ) {
            $cartItems = $cart->getItems(Cart::TYPE_PRODUCT);
            $cartItemsCount = count($cartItems);
            if ($cartItemsCount < 4) {
                $typeCons = new DeliveryType(
                    OmsDeliveryType::TYPE_CONSOLIDATION,
                    LogisticsDeliveryMethod::METHOD_DELIVERY,
                    "Все товары в один день",
                    "Одним отправлением"
                );
                $singleShipment = new DeliveryShipment(
                    1,
                    Carbon::now()->addDays(3)->toDateString(),
                    [
                        Carbon::now()->addDays(3)->toDateString(),
                        Carbon::now()->addDays(4)->toDateString(),
                        Carbon::now()->addDays(5)->toDateString(),
                        Carbon::now()->addDays(9)->toDateString(),
                        Carbon::now()->addDays(10)->toDateString(),
                        Carbon::now()->addDays(12)->toDateString(),
                        Carbon::now()->addDays(20)->toDateString(),
                        Carbon::now()->addDays(25)->toDateString(),
                        Carbon::now()->addDays(40)->toDateString(),
                    ]
                );
            
                foreach ($cartItems as $cartItem) {
                    $singleShipment->addItem($cartItem->product);
                }
                $typeCons->addShipment($singleShipment);
                $checkout->deliveryTypes[] = $typeCons;
            }
        
            if ($cartItemsCount > 1) {
                $typeSplit = new DeliveryType(
                    OmsDeliveryType::TYPE_SPLIT,
                    LogisticsDeliveryMethod::METHOD_DELIVERY,
                    'Поскорее',
                    'Несколько отправлений'
                );
                $firstShipment = new DeliveryShipment(
                    2,
                    Carbon::now()->addDays(3)->toDateString(),
                    [
                        Carbon::now()->addDays(3)->toDateString(),
                        Carbon::now()->addDays(4)->toDateString(),
                        Carbon::now()->addDays(5)->toDateString(),
                        Carbon::now()->addDays(7)->toDateString(),
                        Carbon::now()->addDays(9)->toDateString(),
                        Carbon::now()->addDays(13)->toDateString(),
                    ]
                );
                $firstShipment->addItem($cartItems[0]->product);
            
                $secondShipment = new DeliveryShipment(
                    3,
                    Carbon::now()->addDays(2)->toDateString(),
                    [
                        Carbon::now()->addDays(2)->toDateString(),
                        Carbon::now()->addDays(6)->toDateString(),
                        Carbon::now()->addDays(8)->toDateString(),
                        Carbon::now()->addDays(10)->toDateString(),
                    ]
                );
                for ($i = 1; $i < $cartItemsCount; $i++) {
                    $secondShipment->addItem($cartItems[$i]->product);
                }
                $typeSplit->addShipment($firstShipment);
                $typeSplit->addShipment($secondShipment);
                $checkout->deliveryTypes[] = $typeSplit;
            }
        }
        
        return $checkout;
    }
    
    /**
     * @param RequestInitiator $user
     * @param CheckoutDataDto $checkout
     * @param Cart $cart
     * @param OrderService $orderService
     * @return array
     */
    protected function saveGeneralOrderData(RequestInitiator $user, CheckoutDataDto $checkout, Cart $cart, OrderService $orderService): ?OrderDto
    {
        $order = new OrderDto();
        $order->customer_id = $user->userId();
        $order->basket_id = $cart->getBasketId(Cart::TYPE_PRODUCT);
        
        $order->cost = $checkout->summary->cartCost;
        $order->price = $checkout->summary->price();
        $order->delivery_cost = $checkout->summary->deliveryCost;
        $order->spent_bonus = $checkout->summary->spentBonus ?? 0;
        $order->added_bonus = $checkout->summary->newBonus ?? 0;
        $order->promocode = $checkout->input->promocode;
        // todo add certificates
        
        $order->delivery_type = $checkout->input->deliveryType->id;
        $order->delivery_method = $checkout->input->deliveryMethodID;
        $order->delivery_service = DeliveryService::SERVICE_B2CPL;// todo чё-то с этим надо делать
        $order->delivery_address = $checkout->input->address;
        
        $receiver = $checkout->input->recipient;
        [
            'name' => $order->receiver_name,
            'phone' => $order->receiver_phone,
            'email' => $order->receiver_email
        ] = $receiver;
        
        $orderId = $orderService->createOrder($order);
        /** @var OrderDto $savedOrder */
        $savedOrder = $orderService->orders($orderService->newQuery()->addFields(OrderDto::entity(), 'id', 'number')->setFilter('id', $orderId))->first();
        if (!$savedOrder) {
            return null;
        }
        $order->number = $savedOrder->number;
        $order->id = $savedOrder->id;
        return $order;
    }
    
    /**
     * @param CheckoutDataDto $checkout
     * @param OrderDto $order
     */
    protected function createShipments(CheckoutDataDto $checkout, OrderDto $order): void
    {
//        $shipmentService = resolve(ShipmentService::class);
//        /** @var DeliveryShipment $shipmentInput */
//        foreach ($checkout->input->deliveryType->items as $i => $shipmentInput) {
//            $omsShipment = new ShipmentDto();
//            $n = $i + 1;
//            $omsShipment->number = "{$order->number}/{$n}";
//            $omsShipment->cost = 123;
//            $omsShipment->merchant_id = 1;
//            $omsShipment->store_id = 1;
//            $omsShipment->id = $shipmentService->createShipment($order->id, $omsShipment);
//
//            foreach ($shipmentInput->items as $product) {
//
//            }
//        }
    }
    
    /**
     * @param CheckoutDataDto $checkout
     * @param OrderDto $order
     * @param OrderService $orderService
     * @param PaymentService $paymentService
     * @return PaymentDto|null
     */
    protected function createPayment(CheckoutDataDto $checkout, OrderDto $order, OrderService $orderService, PaymentService $paymentService)
    {
        $payment = new PaymentDto();
        $payment->order_id = $order->id;
        $payment->sum = $order->price;
        $payment->type = $checkout->input->paymentMethodID;
        $payment->payment_system = PaymentSystem::YANDEX;
        $orderService->setPayments($order->id, [$payment]);
        
        $payment = $paymentService->byOrder($order->id, $checkout->input->paymentMethodID);
        return $payment;
    }
}
