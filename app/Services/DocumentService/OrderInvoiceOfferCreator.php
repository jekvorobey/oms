<?php

namespace App\Services\DocumentService;

use App\Models\Basket\BasketItem;
use App\Services\OrderService;
use Cms\Core\CmsException;
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
     * @throws CmsException
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     * @throws Exception
     */
    protected function createDocument(): string
    {
        $pathToTemplate = Storage::disk(self::DISK)->path($this->documentName());
        $templateProcessor = new TemplateProcessor($pathToTemplate);

        $organizationInfo = $this->getOrganizationInfo();

        $templateProcessor->setValue('full_name', $organizationInfo['full_name']);
        $templateProcessor->setValue('legal_address', $organizationInfo['legal_address']);
        $templateProcessor->setValue('inn', $organizationInfo['inn']);
        $templateProcessor->setValue('kpp', $organizationInfo['kpp']);
        $templateProcessor->setValue('payment_account', $organizationInfo['payment_account']);
        $templateProcessor->setValue('bank_bik', $organizationInfo['bank_bik']);
        $templateProcessor->setValue('correspondent_account', $organizationInfo['correspondent_account']);
        $templateProcessor->setValue('bank_name', $organizationInfo['bank_name']);
        $templateProcessor->setValue('offer_number', $this->order->number);
        $templateProcessor->setValue('offer_date', OrderDocumentCreatorHelper::formatDate($this->order->created_at));
        $templateProcessor->setValue('customer_legal_info_company_name', $this->customer->legal_info_company_name);
        $templateProcessor->setValue('customer_legal_info_inn', $this->customer->legal_info_inn);
        $values = $this->order->basket->items->map(function (BasketItem $basketItem, $number) {
            return [
                'number' => $number + 1,
                'itemName' => htmlspecialchars($basketItem->name, ENT_QUOTES | ENT_XML1),
                'itemMeasure' => 'шт.',
                'itemQTY' => qty_format($basketItem->qty),
                'itemPrice' => price_format($basketItem->unit_price),
                'itemSum' => price_format($basketItem->price),
            ];
        })->all();
        $templateProcessor->cloneRowAndSetValues('number', $values);
        $templateProcessor->setValue('order_price', price_format($this->order->price));
        $path = $this->generateDocumentPath();
        $templateProcessor->saveAs($path);

        return $path;
    }
}
