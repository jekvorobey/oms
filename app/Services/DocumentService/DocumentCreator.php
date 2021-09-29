<?php

namespace App\Services\DocumentService;

use App\Services\Dto\Out\DocumentDto;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Support\Facades\Storage;

abstract class DocumentCreator
{
    public const DISK = 'document-templates';
    public const FILE_SERVICE_FOLDER = 'oms-documents';

    /** Создать документ с заполненными данными */
    public function create(): DocumentDto
    {
        $documentDto = new DocumentDto();

        $this->rescue($documentDto, function () use ($documentDto) {
            $path = $this->createDocument();
            $this->saveDocument($documentDto, $path);
        });

        return $documentDto;
    }

    /** Название документа */
    abstract public function documentName(): string;

    abstract protected function createDocument(): string;

    protected function generateDocumentPath(): string
    {
        $filename = $this->getFileWithSuffix($this->documentName(), $this->resultDocSuffix());

        return Storage::disk(self::DISK)->path($filename);
    }

    /** Сформировать имя файла с необходимых суффиксом */
    protected function getFileWithSuffix(string $template, string $suffix): string
    {
        $pathParts = pathinfo($template);

        return $pathParts['filename'] . '-' . $suffix . '.' . $pathParts['extension'];
    }

    /** Суффикс для название сформированного документа */
    abstract protected function resultDocSuffix(): string;

    /** Загрузить и сохранить документ */
    private function saveDocument(DocumentDto $documentDto, string $path): void
    {
        $this->uploadFile($documentDto, $path, pathinfo($path, PATHINFO_BASENAME));

        Storage::delete($path);
    }

    /**
     * Сохранить сформированный документ в сервис file
     */
    private function uploadFile(DocumentDto $documentDto, string $path, string $name): void
    {
        $this->rescue($documentDto, function () use ($documentDto, $path, $name) {
            /** @var FileService $fileService */
            $fileService = resolve(FileService::class);
            $fileId = $fileService->uploadFile(self::FILE_SERVICE_FOLDER, $name, $path);

            if (!$fileId) {
                throw new \Exception('Ошибка при сохранении документа');
            }

            $documentDto->success = true;
            $documentDto->file_id = $fileId;
        });
    }

    /** Обертка для сохранения ошибок в DocumentDto */
    private function rescue(DocumentDto $documentDto, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            report($e);

            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }
    }
}
