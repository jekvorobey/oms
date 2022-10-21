<?php

namespace Database\Seeders;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use App\Services\PaymentService\PaymentService;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Class PaymentsSeeder
 */
class PaymentsSeeder extends Seeder
{
    public const FAKER_SEED = 123456;

    /**
     * Run the database seeders.
     * @throws \App\Services\PaymentService\PaymentSystems\Exceptions\Payment
     */
    public function run()
    {
        $faker = Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);

        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        /** @var Collection|Order[] $orders */
        $orders = Order::query()->get();
        foreach ($orders as $order) {
            if ($order->payments->isEmpty()) {
                $payment = new Payment();
                $payment->created_at = $order->created_at;
                $payment->order_id = $order->id;
                $payment->payment_system = $faker->randomElement(PaymentSystem::validValues());
                $payment->status = $faker->randomElement(PaymentStatus::validValues());
                $payment->sum = $order->price;
                $payment->payment_method = $faker->randomElement(PaymentMethod::validValues());
                $payment->save();

                if ($payment->status == PaymentStatus::PAID) {
                    $paymentService->start($payment->id, 'https://dev_front.ibt-mas.greensight.ru/');
                    if ($payment->payment_system == PaymentSystem::YANDEX) {
                        /** Для увеличения интервала между запросами к Яндекс.Кассе */
                        sleep($faker->randomFloat(0, 5, 10));
                    }
                    $payment->payed_at = $faker->dateTimeBetween($payment->created_at, $payment->expires_at);
                }
                $payment->save();
            }
        }
    }
}
