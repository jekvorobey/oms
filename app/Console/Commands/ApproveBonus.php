<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderBonus;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;
use Carbon\Carbon;
use Greensight\Marketing\Dto\Option\OptionDto;
use Greensight\Marketing\Services\OptionService\OptionService as MarketingOptionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ApproveBonus extends Command
{
    public const DEFAULT_OFFSET = 14;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order-bonus:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда для подтверждения удержанных бонусов';

    /**
     * Execute the console command.
     * @throws \Exception
     */
    public function handle()
    {
        /** @var MarketingOptionService $marketingOptionService */
        $marketingOptionService = resolve(MarketingOptionService::class);
        $offset = $marketingOptionService->get(OptionDto::KEY_ORDER_ACTIVATION_BONUS_DELAY, self::DEFAULT_OFFSET);

        $statusAt = Carbon::now()->addDays(-$offset)->endOfDay();
        $orders = Order::query()
            ->with(['bonuses'])
            ->where('status', OrderStatus::DONE)
            ->where('payment_status', PaymentStatus::PAID)
            ->where('status_at', '<=', $statusAt)
            ->where('payment_status_at', '<=', $statusAt)
            ->whereHas('bonuses', function (Builder $query) {
                $query->where('status', OrderBonus::STATUS_ON_HOLD);
            })
            ->get();

        foreach ($orders as $order) {
            /** @var OrderBonus $orderBonus */
            foreach ($order->bonuses as $orderBonus) {
                $orderBonus->approveBonus();
            }
        }
    }
}
