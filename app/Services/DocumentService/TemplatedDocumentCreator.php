<?php

namespace App\Services\DocumentService;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use NcJoes\OfficeConverter\OfficeConverter;
use PhpOffice\PhpWord\TemplateProcessor;
use Pim\Dto\Product\ProductByOfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\ProductService\ProductService;

abstract class TemplatedDocumentCreator extends DocumentCreator
{
    /** окончание в имени файла с шаблоном для программного заполнения данными */
    public const TEMPLATE_SUFFIX = 'template';

    protected bool $asPdf = false;

    /** @return static */
    public function setAsPdf(bool $asPdf): self
    {
        $this->asPdf = $asPdf;

        return $this;
    }

    protected function createDocument(): string
    {
        $templateProcessor = $this->initTemplateProcessor();

        $this->fillTemplate($templateProcessor);

        $path = $this->generateDocumentPath();

        $templateProcessor->saveAs($path);

        if ($this->asPdf) {
            $path = $this->convertToPdf($path);
        }

        return $path;
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

    protected function convertToPdf(string $path): string
    {
        if (!$bin = config('libreoffice.bin')) {
            throw new \Exception('libreoffice.bin is empty!');
        }

        $converter = new OfficeConverter($path, null, $bin, false);

        $filename = pathinfo($path, PATHINFO_FILENAME);

        $pdfPath = $converter->convertTo("$filename.pdf");

        unlink($path);

        return $pdfPath;
    }
}
