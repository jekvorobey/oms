<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Class DocumentTemplatesController
 * @package App\Http\Controllers\V1
 */
class DocumentTemplatesController extends Controller
{
    /**
     * Получить шаблон "Акт-претензия по отправлению/грузу"
     * @return JsonResponse
     */
    public function claimAct(): JsonResponse
    {
        return $this->getResponse(DocumentService::CLAIM_ACT);
    }

    /**
     * Получить шаблон "Акт приема-передачи по отправлению"
     * @return JsonResponse
     */
    public function acceptanceAct(): JsonResponse
    {
        return $this->getResponse(DocumentService::ACCEPTANCE_ACT);
    }

    /**
     * Получить шаблон "Опись отправления заказа"
     * @return JsonResponse
     */
    public function inventory(): JsonResponse
    {
        return $this->getResponse(DocumentService::INVENTORY);
    }

    /**
     * Получить шаблон "Карточка сборки отправления"
     * @return JsonResponse
     */
    public function assemblingCard(): JsonResponse
    {
        return $this->getResponse(DocumentService::ASSEMBLING_CARD);
    }

    /**
     * @param  string  $template
     * @return JsonResponse
     */
    protected function getResponse(string $template): JsonResponse
    {
        return response()->json([
            'absolute_url' => Storage::disk('document-templates')->url($template),
            'original_name' => $template,
            'size' => Storage::disk('document-templates')->size($template),
        ]);
    }
}
