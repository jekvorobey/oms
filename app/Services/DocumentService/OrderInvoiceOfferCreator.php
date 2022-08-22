<?php

namespace App\Services\DocumentService;

use App\Models\Basket\BasketItem;
use App\Services\OrderService;
use Cms\Services\OptionService\OptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\TemplateProcessor;

class OrderInvoiceOfferCreator extends OrderDocumentsCreator
{
    public function __construct(OrderService $orderService, OptionService $optionService)
    {
        parent::__construct($orderService, $optionService);
    }

    public function documentName(): string
    {
        return 'invoice-offer.docx';
    }

    public function title(): string
    {
        $today = OrderDocumentCreatorHelper::formatDate(Carbon::today());

        return "Счёт-оферта № {$this->order->number} от {$today}";
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     * @throws Exception
     */
    protected function createDocument(): string
    {
        $pathToTemplate = Storage::disk(self::DISK)->path($this->documentName());
        $templateProcessor = new TemplateProcessor($pathToTemplate);

        $templateProcessor->setValue('full_name', $this->organizationInfo['full_name']);
        $templateProcessor->setValue('legal_address', $this->organizationInfo['legal_address']);
        $templateProcessor->setValue('inn', $this->organizationInfo['inn']);
        $templateProcessor->setValue('kpp', $this->organizationInfo['kpp']);
        $templateProcessor->setValue('payment_account', $this->organizationInfo['payment_account']);
        $templateProcessor->setValue('bank_bik', $this->organizationInfo['bank_bik']);
        $templateProcessor->setValue('correspondent_account', $this->organizationInfo['correspondent_account']);
        $templateProcessor->setValue('bank_name', $this->organizationInfo['bank_name']);
        $templateProcessor->setValue('offer_number', $this->order->number);
        $templateProcessor->setValue('offer_date', OrderDocumentCreatorHelper::formatDate($this->order->created_at));
        $templateProcessor->setValue('customer_legal_info_company_name', $this->customer->legal_info_company_name);
        $templateProcessor->setValue('customer_legal_info_inn', $this->customer->legal_info_inn);
        $templateProcessor->setValue('seo', $this->getCEOInitials());
        $templateProcessor->setValue('general_accountant', $this->getGeneralAccountantInitials());
        $values = $this->order->basket->items->map(function (BasketItem $basketItem, $number) {
            return [
                'number' => $number + 1,
                'itemName' => htmlspecialchars($basketItem->name, ENT_QUOTES | ENT_XML1),
                'itemMeasure' => 'шт.',
                'itemQTY' => qty_format($basketItem->qty),
                'itemPrice' => number_format($basketItem->unit_price, 2, '.', ' '),
                'itemSum' => number_format($basketItem->price, 2, '.', ' '),
            ];
        })->all();
        $templateProcessor->cloneRowAndSetValues('number', $values);
        $templateProcessor->setValue('items_price', number_format($this->order->price - $this->order->delivery_price, 2, '.', ' '));
        $templateProcessor->setValue('delivery_price', number_format($this->order->delivery_price, 2, '.', ' '));
        $templateProcessor->setValue('order_price', number_format($this->order->price, 2, '.', ' '));
        $path = $this->generateDocumentPath();
        $templateProcessor->saveAs($path);

        return $path;
    }
}
