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
use YooKassa\Model\Metadata;
use YooKassa\Model\Notification\AbstractNotification;
use YooKassa\Model\NotificationEventType;
use YooKassa\Request\Payments\AbstractPaymentResponse;
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
            'sum' => $faker->numberBetween(1, 10000),
        ]);

        $mockYandexClientReturn = $this->mockHttpNotification($payment, YooKassaPaymentStatus::WAITING_FOR_CAPTURE);
        $paymentSystem = $payment->paymentSystem();
        $paymentSystem->createExternalPayment($payment, $faker->url);

        $this->assertDatabaseHas(
            (new Payment())->getTable(),
            [
                'id' => $payment->id,
                'data' => json_encode([
                    'externalPaymentId' => $mockYandexClientReturn['id'],
                    'paymentLink' => $mockYandexClientReturn['confirmation']['confirmation_url'],
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
            'sum' => $faker->numberBetween(1, 10000),
        ]);

        $mockYandexClientReturn = $this->mockHttpNotification($payment, YooKassaPaymentStatus::WAITING_FOR_CAPTURE);
        $paymentSystem = $payment->paymentSystem();
        $paymentSystem->handlePushPayment([
            'event' => NotificationEventType::PAYMENT_WAITING_FOR_CAPTURE,
            'object' => [
                'id' => $payment->external_payment_id,
                'status' => YooKassaPaymentStatus::WAITING_FOR_CAPTURE,
                'recipient' => [
                    'account_id' => $faker->randomNumber(),
                    'gateway_id' => $faker->randomNumber(),
                ],
                'amount' => [
                    'value' => $payment->sum,
                    'currency' => CurrencyCode::RUB,
                ],
                'created_at' => $payment->created_at,
                'paid' => true,
                'refundable' => false,
            ],
        ]);

        $this->assertDatabaseHas(
            (new Payment())->getTable(),
            [
                'id' => $payment->id,
                'status' => PaymentStatus::HOLD,
                'data' => json_encode([
                    'externalPaymentId' => $mockYandexClientReturn['id'],
                ], JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function testSucceededNotification(): void
    {
        $faker = Factory::create();
        /** @var Payment $payment */
        $payment = factory(Payment::class)->create([
            'payment_system' => PaymentSystem::YANDEX,
            'status' => PaymentStatus::NOT_PAID,
            'sum' => $faker->numberBetween(1, 10000),
        ]);

        $mockYandexClientReturn = $this->mockHttpNotification($payment, YooKassaPaymentStatus::SUCCEEDED);
        $paymentSystem = $payment->paymentSystem();
        $paymentSystem->handlePushPayment([
            'event' => NotificationEventType::PAYMENT_SUCCEEDED,
            'object' => [
                'id' => $payment->external_payment_id,
                'status' => YooKassaPaymentStatus::SUCCEEDED,
                'recipient' => [
                    'account_id' => $faker->randomNumber(),
                    'gateway_id' => $faker->randomNumber(),
                ],
                'amount' => [
                    'value' => $payment->sum,
                    'currency' => CurrencyCode::RUB,
                ],
                'created_at' => $payment->created_at,
                'paid' => true,
                'refundable' => false,
            ],
        ]);

        $this->assertDatabaseHas(
            (new Payment())->getTable(),
            [
                'id' => $payment->id,
                'status' => PaymentStatus::PAID,
                'data' => json_encode([
                    'externalPaymentId' => $mockYandexClientReturn['id'],
                ], JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function testCancelledNotification(): void
    {
        $faker = Factory::create();
        /** @var Payment $payment */
        $payment = factory(Payment::class)->create([
            'payment_system' => PaymentSystem::YANDEX,
            'status' => PaymentStatus::NOT_PAID,
            'sum' => $faker->numberBetween(1, 10000),
        ]);

        $mockYandexClientReturn = $this->mockHttpNotification($payment, YooKassaPaymentStatus::CANCELED);
        $paymentSystem = $payment->paymentSystem();
        $paymentSystem->handlePushPayment([
            'event' => NotificationEventType::PAYMENT_CANCELED,
            'object' => [
                'id' => $payment->external_payment_id,
                'status' => YooKassaPaymentStatus::CANCELED,
                'recipient' => [
                    'account_id' => $faker->randomNumber(),
                    'gateway_id' => $faker->randomNumber(),
                ],
                'amount' => [
                    'value' => $payment->sum,
                    'currency' => CurrencyCode::RUB,
                ],
                'created_at' => $payment->created_at,
                'paid' => true,
                'refundable' => false,
            ],
        ]);

        $this->assertDatabaseHas(
            (new Payment())->getTable(),
            [
                'id' => $payment->id,
                'status' => PaymentStatus::TIMEOUT,
                'data' => json_encode([
                    'externalPaymentId' => $mockYandexClientReturn['id'],
                ], JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function mockHttpNotification(Payment $payment, string $paymentStatus): array
    {
        $faker = Factory::create();
        $mockYandexClientReturn = [
            'id' => $payment->external_payment_id,
            'confirmation' => [
                'confirmation_url' => $faker->url,
                'type' => ConfirmationType::REDIRECT,
            ],
            'status' => $paymentStatus,
            'amount' => [
                'value' => $payment->sum,
                'currency' => CurrencyCode::RUB,
            ],
            'created_at' => $payment->created_at,
            'paid' => true,
            'refundable' => false,
        ];

        $paymentYooKassaParams = [
            'dateExpiresAt' => $faker->dateTimeBetween('+1 day', '+3 day'),
            'paymentId' => $payment->external_payment_id,
            'metadata' => [
                'source' => $faker->url,
            ],
            'status' => $paymentStatus,
        ];

        $this->mock(CustomerService::class, function ($mock) {
            $mock->makePartial()
                ->shouldReceive('customers')
                ->andReturn(collect([new CustomerDto()]));
        });

        $paymentYooKassa = $this->mock(AbstractPaymentResponse::class, function ($mock) use ($paymentYooKassaParams) {
            $mock
                ->makePartial()
                ->shouldReceive([
                    'getExpiresAt' => $paymentYooKassaParams['dateExpiresAt'],
                    'getId' => $paymentYooKassaParams['paymentId'],
                    'getMetadata' => new Metadata($paymentYooKassaParams['metadata']),
                    'getStatus' => $paymentYooKassaParams['status'],
                ]);
        });

        $this->mock(Client::class, function ($mock) use ($mockYandexClientReturn, $paymentYooKassa) {
            $mock
                ->shouldReceive([
                    'capturePayment' => new CreateCaptureResponse($mockYandexClientReturn),
                    'getPaymentInfo' => $paymentYooKassa,
                    'createPayment' => new CreatePaymentResponse($mockYandexClientReturn),
                ]);
        });

        $this->mock(AbstractNotification::class, function ($mock) use ($paymentYooKassa) {
            $mock
                ->makePartial()
                ->shouldReceive('getObject')
                ->andReturn($paymentYooKassa);
        });

        return $mockYandexClientReturn;
    }
}
