<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Services\DocumentService\OrderTicketsCreator;
use Illuminate\Console\Command;

/**
 * Команда для тестирования формирование pdf-файла с билетами заказа с мастер-классами
 * Class OrderTicket2Pdf
 * @package App\Console\Commands
 */
class OrderTicket2Pdf extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:ticket2pdf {orderId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Сформировать pdf-файл с билетами на мастер-класс для заказа';

    /**
     * Execute the console command.
     * @throws \Throwable
     */
    public function handle(OrderTicketsCreator $orderTicketsCreator)
    {
        $orderId = $this->argument('orderId');
        /** @var Order $order */
        $order = Order::query()->where('id', $orderId)->with('basket.items')->first();
        if (!$order) {
            throw new \Exception("Заказ с id=$orderId не найден");
        }

        $documentDto = $orderTicketsCreator->setOrder($order)->create();

        $this->output->writeln($documentDto->success ? $documentDto->file_id : $documentDto->message);
    }
}
