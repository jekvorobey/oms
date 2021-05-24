<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\PaymentService\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class SetWaitingStatus2Payment
 * @package App\Console\Commands
 */
class SetWaitingStatus2Payment extends Command
{
    /**
     * Кол-во минут от даты создания оплаты, по истечению которого в случае неоплаты,
     * она должна перейти в статус "Ожидает оплаты"
     */
    protected const MINUTES_FOR_WAITING = 10;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:set_waiting_status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Установить статус оплаты "Ожидает оплаты" через ' .
        SetWaitingStatus2Payment::MINUTES_FOR_WAITING .
        ' мин после создания и неоплаты';

    /**
     * Execute the console command.
     */
    public function handle(PaymentService $paymentService)
    {
        $dateTimeMinutesAgo = (new Carbon())->modify('-' . self::MINUTES_FOR_WAITING . ' minutes');
        /** @var Collection|Payment[] $payments */
        $payments = Payment::query()
            ->where('status', PaymentStatus::NOT_PAID)
            ->where('created_at', '<=', $dateTimeMinutesAgo->format('Y-m-d H:i:s'))
            ->get();
        if ($payments->isNotEmpty()) {
            foreach ($payments as $payment) {
                $paymentService->waiting($payment);
            }
        }
    }
}
