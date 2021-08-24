<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderReturnReason;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\CreateAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;

/**
 * Class OrderReturnReasonController
 * @package App\Http\Controllers\V1
 */
class OrderReturnReasonController extends Controller
{
    /** @OA\Delete (
     *     path="/api/v1/orders/return-reasons/{id}",
     *     operationId="return_reasons_delete",
     *     tags={"Причины отмены заказа"},
     *     summary="Удалить причину отмены заказа",
     *     description="Удалить причину отмены заказа",
     *     @OA\Parameter(
     *          name="id",
     *          description="Id причины",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *     @OA\Response(response="204", description="No Content"),
     *     @OA\Response(response="400", description="Bad Request"),
     *     @OA\Response(response="404", description="Not Found"),
     *     @OA\Response(response="500", description="Internal Server Error"),
     *     security={ {"bearer": {} }},
     * )
     */
    use DeleteAction;
    /** @OA\Post (
     *     path="/api/v1/orders/return-reasons/{id}",
     *     operationId="return_reasons_add",
     *     tags={"Причины отмены заказа"},
     *     summary="Добавление причины отмены заказа",
     *     description="Добавление причины отмены заказа",
     *     @OA\RequestBody(
     *      required=true,
     *      description="Добавление причины отмены заказа",
     *      @OA\JsonContent(
     *          required={"text"},
     *          @OA\Property(property="text", type="varchar", format="text", example="ExampleOrderReasonReturn"),
     *      ),
     *     ),
     *     @OA\Response(response="200", description="JSON"),
     *     @OA\Response(response="400", description="Bad request"),
     *     security={ {"bearer": {} }},
     * )
     */
    use CreateAction;
    /** @OA\Put (
     *     path="/api/v1/orders/return-reasons/{id}",
     *     operationId="return_reasons_update",
     *     tags={"Причины отмены заказа"},
     *     summary="Обновление причины отмены заказа",
     *     description="Обновление причины отмены заказа",
     *     @OA\Parameter(
     *          name="id",
     *          description="Id записи",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *     @OA\RequestBody(
     *      required=true,
     *      description="Обоновление причины отмены заказа",
     *      @OA\JsonContent(
     *           required={"text"},
     *          @OA\Property(property="text", type="varchar", format="text", example="ExampleOrderReasonReturn"),
     *      ),
     *     ),
     *     @OA\Response(response="200", description="JSON"),
     *     @OA\Response(response="400", description="Bad request"),
     *     security={ {"bearer": {} }},
     * )
     */
    use UpdateAction;
    /** @OA\Get(
     *     path="/api/v1/orders/return-reasons",
     *     operationId="return_reasons_get",
     *     tags={"Причины отмены заказа"},
     *     summary="Отобразить список причин отмены заказа",
     *     description="Отобразить список причин отмены заказа",
     *     @OA\Response(response="200", description="JSON"),
     *     @OA\Response(response="400", description="Bad request"),
     *     security={ {"bearer": {} }},
     * )
     * @OA\Get(
     *     path="/api/v1/orders/return-reasons/{id}",
     *     operationId="brand_get",
     *     tags={"Причины отмены заказа"},
     *     summary="Отобразить причину отмены заказа с ID",
     *     description="Получить причину отмены заказа с ID",
     *     @OA\Parameter(
     *          name="id",
     *          description="Id записи",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *     @OA\Response(response="200", description="JSON"),
     *     @OA\Response(response="400", description="Bad request"),
     *     security={ {"bearer": {} }},
     * )
     */
    use ReadAction;
    /** @OA\Get(
     *     path="/api/v1/orders/return-reasons/count",
     *     operationId="return_reasons_count",
     *     tags={"Причины отмены заказа"},
     *     summary="Параметры постраничной навигации",
     *     description="Отображет параметры постраничной навигации для причин отмены заказа",
     *     @OA\Response(response="200", description="JSON"),
     *     @OA\Response(response="400", description="Bad request"),
     *     security={ {"bearer": {} }},
     * )
     */
    use CountAction;

    public function modelClass(): string
    {
        return OrderReturnReason::class;
    }

    protected function writableFieldList(): array
    {
        return OrderReturnReason::FILLABLE;
    }
}
