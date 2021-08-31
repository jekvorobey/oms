<?php

namespace App\Services\DocumentService;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Pim\Dto\Product\ProductByOfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\ProductService\ProductService;

abstract class TemplatedDocumentCreator extends DocumentCreator
{
    /** окончание в имени файла с шаблоном для программного заполнения данными */
    public const TEMPLATE_SUFFIX = 'template';

    protected function createTemplate(): TemplateProcessor
    {
        $templateProcessor = $this->initTemplateProcessor();

        $this->fillTemplate($templateProcessor);

        return $templateProcessor;
    }

    /** Инициализация сервиса для работы с docx-шаблоном */
    protected function initTemplateProcessor(): TemplateProcessor
    {
        $programTemplate = $this->getFileWithSuffix($this->documentName(), self::TEMPLATE_SUFFIX);

        return new TemplateProcessor(Storage::disk(self::DISK)->path($programTemplate));
    }

    /** Заполнить шаблон документа */
    abstract protected function fillTemplate(TemplateProcessor $templateProcessor): void;

    /**
     * @return ProductByOfferDto[]|Collection
     */
    protected function getProductsByOffers(array $offersIds): Collection
    {
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        $productQuery = $productService->newQuery()
            ->addFields(ProductDto::entity(), 'id', 'vendor_code');

        return $productService->productsByOffers($productQuery, $offersIds);
    }

    /**
     * @param TemplateProcessor $template
     */
    protected function saveTmpDoc($template, string $path): void
    {
        $template->saveAs($path);

//      $converter = new OfficeConverter($documentPath, null, '/Applications/LibreOffice.app/Contents/MacOS/soffice', false);
//      $converter->convertTo($documentPath . '.pdf');
    }
}
