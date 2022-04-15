<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Services\CargoService;
use App\Services\DeliveryService;
use App\Services\DocumentService\CargoAcceptanceActCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CargoDocumentsController extends DocumentController
{
    /**
     * @OA\Get(
     *     path="api/v1/cargos/{id}/documents/acceptance-act",
     *     tags={"Акт приема-передачи по грузу"},
     *     description="Сформировать акт приема-передачи по грузу",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="as_pdf", required=false, in="query", @OA\Schema(type="boolean"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="absolute_url"),
     *             @OA\Property(property="original_name"),
     *             @OA\Property(property="size")
     *         )
     *     )
     *  )
     * Сформировать "Акт приема-передачи по грузу"
     */
    public function acceptanceAct(
        int $cargoId,
        Request $request,
        CargoService $cargoService,
        CargoAcceptanceActCreator $cargoAcceptanceActCreator
    ): JsonResponse {
        $cargo = $cargoService->getCargo($cargoId);
        if (!$cargo) {
            throw new NotFoundHttpException('cargo not found');
        }

        $documentDto = $cargoAcceptanceActCreator->setCargo($cargo)->setAsPdf($request->as_pdf ?: false)->create();

        return $this->documentResponse($documentDto);
    }
}
