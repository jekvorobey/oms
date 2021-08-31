<?php

namespace App\Services\DocumentService;

use App\Services\Dto\Out\DocumentDto;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Support\Facades\Storage;

abstract class DocumentCreator
{
    public const DISK = 'document-templates';

    /** окончание в имени файла с шаблоном для программного заполнения данными */
    public const TEMPLATE_SUFFIX = 'template';

    /** Создать документ с заполненными данными */
    public function create(): DocumentDto
    {
        $documentDto = new DocumentDto();

        $this->rescue($documentDto, function () use ($documentDto) {
            $template = $this->createTemplate();
            $this->saveDocument($documentDto, $template);
        });

        return $documentDto;
    }

    /** Название документа */
    abstract public function documentName(): string;

    /** Обертка для сохранения ошибок в DocumentDto */
    protected function rescue(DocumentDto $documentDto, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            report($e);

            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }
    }

    abstract protected function createTemplate();

    /** Сформировать имя файла с необходимых суффиксом */
    protected function getFileWithSuffix(string $template, string $suffix): string
    {
        $pathParts = pathinfo($template);

        return $pathParts['filename'] . '-' . $suffix . '.' . $pathParts['extension'];
    }

    /** Загрузить и сохранить документ */
    protected function saveDocument(DocumentDto $documentDto, $template): void
    {
        $documentName = $this->getFileWithSuffix($this->documentName(), $this->resultDocSuffix());

        $documentPath = Storage::disk(self::DISK)->path($documentName);

        $this->saveTmpDoc($template, $documentPath);

        $this->uploadFile($documentDto, $documentPath, $documentName);

        Storage::disk(self::DISK)->delete($documentName);
    }

    /** Суффикс для название сформированного документа */
    abstract protected function resultDocSuffix(): string;

    /** Сохранить локально временный сформированный документ */
    abstract protected function saveTmpDoc($template, string $path): void;

    /**
     * Сохранить сформированный документ в сервис file
     */
    protected function uploadFile(DocumentDto $documentDto, string $documentPath, string $documentName): void
    {
        $this->rescue($documentDto, function () use ($documentDto, $documentPath, $documentName) {
            /** @var FileService $fileService */
            $fileService = resolve(FileService::class);
            $fileId = $fileService->uploadFile('oms-documents', $documentName, $documentPath);

            if (!$fileId) {
                throw new \Exception('Ошибка при сохранении документа');
            }

            $documentDto->success = true;
            $documentDto->file_id = $fileId;
        });
    }
}
