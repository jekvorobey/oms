<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CargoController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/cargo",
     *     tags={"cargo"},
     *     summary="Создать новый груз",
     *     operationId="createCargo",
     *     @OA\Response(
     *         response=500,
     *         description="Ошибка при сохранении"
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *          description="Успешное создание груза",
     *          @OA\JsonContent(ref="#/components/schemas/CreateResult"),
     *     ),
     * )
     */
    public function create(Request $request)
    {
        $cargo = new Cargo();
        $ok = $cargo->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save cargo');
        }
        
        return response()->json([
            'id' => $cargo->id
        ]);
    }
}
