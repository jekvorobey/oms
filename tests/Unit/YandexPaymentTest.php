<?php

namespace Tests\Unit;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentSystem;
use App\Services\PaymentService\PaymentSystems\Yandex\SDK\Client;
use Faker\Factory;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Foundation\Testing\WithoutEvents;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use YooKassa\Model\ConfirmationType;
use YooKassa\Model\CurrencyCode;
use YooKassa\Model\Notification\AbstractNotification;
use YooKassa\Model\PaymentInterface;
use YooKassa\Request\Payments\CreatePaymentResponse;
use YooKassa\Model\PaymentStatus as YooKassaPaymentStatus;
use YooKassa\Request\Payments\Payment\CreateCaptureResponse;

class YandexPaymentTest extends TestCase
{
    use RefreshDatabase, WithoutEvents, MockeryPHPUnitIntegration;

    public function testCreateExternalPayment(): void
    {
        $faker = Factory::create();
        /** @var Payment $payment */
        $payment = factory(Payment::class)->create([
            'payment_system' => PaymentSystem::YANDEX,
            'status' => PaymentStatus::NOT_PAID,
        ]);

        $mockYandexClientReturn = [
            'id' => $faker->uuid,
            'confirmation' => [
                'confirmation_url' => $faker->url,
                'type' => ConfirmationType::REDIRECT,
            ],
            'status' => YooKassaPaymentStatus::WAITING_FOR_CAPTURE,
            'amount' => [
                'value' => $payment->sum,
                'currency' => CurrencyCode::RUB,
            ],
            'created_at' => $payment->created_at,
            'paid' => true,
            'refundable' => false,
        ];

        $this->mock(Client::class, function ($mock) use ($mockYandexClientReturn) {
            $mock
                ->shouldReceive('createPayment')
                ->andReturn(new CreatePaymentResponse($mockYandexClientReturn));
        });

        $this->mock(CustomerService::class, function ($mock) {
            $mock->makePartial()
                ->shouldReceive('customers')
                ->andReturn(collect([new CustomerDto()]));
        });

        $paymentSystem = $payment->paymentSystem();
        $paymentSystem->createExternalPayment($payment, $faker->url);

        $this->assertDatabaseHas(
            (new Payment())->getTable(),
            [
                'id' => $payment->id,
                'data' => json_encode([
                    'paymentId' => $payment->data['paymentId'],
                    'externalPaymentId' => $mockYandexClientReturn['id'],
                    'paymentUrl' => $mockYandexClientReturn['confirmation']['confirmation_url'],
                ], JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function testWaitingForCaptureNotification(): void
    {
        $faker = Factory::create();
        /** @var Payment $payment */
        $payment = factory(Payment::class)->create([
            'payment_system' => PaymentSystem::YANDEX,
            'status' => PaymentStatus::NOT_PAID,
        ]);

        $mockYandexClientReturn = [
            'id' => $faker->uuid,
            'confirmation' => [
                'confirmation_url' => $faker->url,
                'type' => ConfirmationType::REDIRECT,
            ],
            'status' => YooKassaPaymentStatus::WAITING_FOR_CAPTURE,
            'amount' => [
                'value' => $payment->sum,
                'currency' => CurrencyCode::RUB,
            ],
            'created_at' => $payment->created_at,
            'paid' => true,
            'refundable' => false,
        ];

        $this->mock(Client::class, function ($mock) use ($mockYandexClientReturn) {
            $mock
                ->shouldReceive('capturePayment')
                ->andReturn(new CreateCaptureResponse($mockYandexClientReturn));
        });

        $this->mock(CustomerService::class, function ($mock) {
            $mock->makePartial()
                ->shouldReceive('customers')
                ->andReturn(collect([new CustomerDto()]));
        });

        $paymentYooKassaParams = [
            'dateExpiresAt' => $faker->dateTimeBetween('+1 day', '+3 day'),
            'paymentId' => $faker->uuid,
        ];

        $paymentYooKassa = $this->mock(PaymentInterface::class, function ($mock) use ($paymentYooKassaParams) {
            $mock
                ->shouldReceive([
                    'getExpiresAt' => $paymentYooKassaParams['dateExpiresAt'],
                    'getId' => $paymentYooKassaParams['paymentId'],
                ]);
        });

        $notification = $this->mock(AbstractNotification::class, function ($mock) use ($paymentYooKassa) {
            $mock
                ->makePartial()
                ->shouldReceive('getObject')
                ->andReturn($paymentYooKassa);
        });

        $paymentSystem = $payment->paymentSystem();
        $paymentSystem->handlePushPayment([
            'event' => 'payment.waiting_for_capture',
        ]);
    }
}
