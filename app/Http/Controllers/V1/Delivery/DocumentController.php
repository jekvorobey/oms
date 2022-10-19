<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Services\Dto\Out\DocumentDto;
use Greensight\CommonMsa\Dto\FileDto;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class DocumentController extends Controller
{
    protected function documentResponse(DocumentDto $documentDto): JsonResponse
    {
        if (!$documentDto->success || !$documentDto->file_id) {
            throw new HttpException(500, $documentDto->message);
        }

        /** @var FileService $fileService */
        $fileService = resolve(FileService::class);

        /** @var FileDto $fileDto */
        $fileDto = $fileService->getFiles([$documentDto->file_id])->firstOrFail();

        return response()->json([
            'id' => $fileDto->id,
            'absolute_url' => $fileDto->absoluteUrl(),
            'original_name' => $fileDto->original_name,
            'size' => $fileDto->size,
        ]);
    }
}
