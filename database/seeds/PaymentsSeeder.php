<?php

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Class PaymentsSeeder
 */
class PaymentsSeeder extends Seeder
{
    /** @var int */
    const FAKER_SEED = 123456;

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $faker = Faker\Factory::create('ru_RU');
        $faker->seed(self::FAKER_SEED);

        /** @var Collection|Order[] $orders */
        $orders = Order::query()->get();
        foreach ($orders as $order) {
            $payment = new Payment();
            $payment->created_at = $order->created_at;
            $payment->order_id = $order->id;
            $payment->payment_system = $faker->randomElement(PaymentSystem::validValues());
            $payment->status = PaymentStatus::NOT_PAID;
            $payment->sum = $order->price;
            $payment->payment_method = $faker->randomElement(PaymentMethod::validValues());
            $payment->save();

            $payment->start('https://dev_front.ibt-mas.greensight.ru/');
            if ($payment->payment_system == PaymentSystem::YANDEX) {
                /** Для увеличения интервала между запросами к Яндекс.Кассе */
                sleep($faker->randomFloat(0, 5, 10));
            }

            $payment->status = $faker->randomElement(PaymentStatus::validValues());
            if ($payment->status == PaymentStatus::PAID) {
                $payment->payed_at = $faker->dateTimeBetween($payment->created_at, $payment->expires_at);
            }
            $payment->save();
        }
    }
}
