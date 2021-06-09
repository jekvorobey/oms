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
     * @OA\Get(
     *     path="api/v1/document-templates/claim-act",
     *     tags={"Шаблоны документов"},
     *     description="Получить шаблон Акт-претензия по отправлению",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Получить шаблон "Акт-претензия по отправлению"
     */
    public function claimAct(): JsonResponse
    {
        return $this->getResponse(DocumentService::CLAIM_ACT);
    }

    /**
     *  @OA\Get(
     *     path="api/v1/document-templates/acceptance-act",
     *     tags={"Шаблоны документов"},
     *     description="Получить шаблон Акт приема-передачи по отправлению/грузу",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Получить шаблон "Акт приема-передачи по отправлению/грузу"
     */
    public function acceptanceAct(): JsonResponse
    {
        return $this->getResponse(DocumentService::ACCEPTANCE_ACT);
    }

    /**
     * @OA\Get(
     *     path="api/v1/document-templates/inventory",
     *     tags={"Шаблоны документов"},
     *     description="Получить шаблон Опись отправления заказа",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Получить шаблон "Опись отправления заказа"
     */
    public function inventory(): JsonResponse
    {
        return $this->getResponse(DocumentService::INVENTORY);
    }

    /**
     * @OA\Get(
     *     path="api/v1/document-templates/assembling-card",
     *     tags={"Шаблоны документов"},
     *     description="Получить шаблон Карточка сборки отправления",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     *
     * Получить шаблон "Карточка сборки отправления"
     */
    public function assemblingCard(): JsonResponse
    {
        return $this->getResponse(DocumentService::ASSEMBLING_CARD);
    }

    protected function getResponse(string $template): JsonResponse
    {
        return response()->json([
            'absolute_url' => Storage::disk('document-templates')->url($template),
            'original_name' => $template,
            'size' => Storage::disk('document-templates')->size($template),
        ]);
    }
}
