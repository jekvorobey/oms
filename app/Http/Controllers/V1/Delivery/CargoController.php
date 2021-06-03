<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Services\DeliveryService as OmsDeliveryService;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\CreateAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class CargoController
 * @package App\Http\Controllers\V1\Delivery
 */
class CargoController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/cargos/count",
     *     tags={"Груз"},
     *     description="Количество сущностей вариантов значений груз",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="pages", type="integer"),
     *             @OA\Property(property="pageSize", type="integer"),
     *         )
     *     )
     * )
     */
    use CountAction;

    /**
     * @OA\Post(
     *     path="api/v1/cargos",
     *     tags={"Груз"},
     *     description="Добавить груз",
     *     @OA\RequestBody(
     *         @OA\JsonContent(ref="#/components/schemas/Cargo")
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="400", description="Ошибка валидации"),
     *     @OA\Response(response="404", description=""),
     *     @OA\Response(response="500", description="Не удалось сохранить данные"),
     * )
     */
    use CreateAction;

    /**
     * @OA\Get(
     *     path="api/v1/cargos",
     *     tags={"Груз"},
     *     description="Получить список грузов",
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Cargo"))
     *         )
     *     )
     * )
     *
     * @OA\Get(
     *     path="api/v1/cargos/{id}",
     *     tags={"Груз"},
     *     description="Получить значение груза с ID",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Cargo"))
     *         )
     *     )
     * )
     */
    use ReadAction;

    /**
     * @OA\Put(
     *     path="api/v1/cargos/{id}",
     *     tags={"Груз"},
     *     description="Изменить значения груза.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="Изменить значение для public event types.",
     *          @OA\JsonContent(ref="#/components/schemas/Cargo")
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description="product not found"),
     * )
     */
    use UpdateAction;

    /**
     * @OA\Delete(
     *     path="api/v1/cargos/{id}",
     *     tags={"Груз"},
     *     description="Удалить груз",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="Сущность не найдена"),
     *     @OA\Response(response="500", description="Не удалось удалить сущность"),
     * )
     */
    use DeleteAction;

    /**
     * @inheritDoc
     */
    public function modelClass(): string
    {
        return Cargo::class;
    }

    /**
     * @inheritDoc
     */
    protected function writableFieldList(): array
    {
        return Cargo::FILLABLE;
    }

    /**
     * @inheritDoc
     */
    protected function inputValidators(): array
    {
        return [
            'merchant_id' => [new RequiredOnPost(), 'integer'],
            'store_id' => [new RequiredOnPost(), 'integer'],
            'status' => ['nullable', Rule::in(CargoStatus::validValues())],
            'delivery_service' => [new RequiredOnPost(), Rule::in(array_keys(DeliveryService::allServices()))],
            'xml_id' => ['nullable', 'string'],
        ];
    }

    /**
     * @OA\Put(
     *     path="api/v1/cargos/{id}/cancel",
     *     tags={"Груз"},
     *     description="Отменить груз.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="product not found"),
     * )
     * Отменить груз
     * @param  int  $id
     * @param  OmsDeliveryService  $deliveryService
     * @return Response
     * @throws \Exception
     */
    public function cancel(int $id, OmsDeliveryService $deliveryService): Response
    {
        $cargo = $deliveryService->getCargo($id);
        if (!$cargo) {
            throw new NotFoundHttpException('cargo not found');
        }
        if (!$deliveryService->cancelCargo($cargo)) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * @OA\Post(
     *     path="api/v1/cargos/{id}/courier-call",
     *     tags={"Груз"},
     *     description="Создать заявку на вызов курьера для забора груза",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="cargo not found"),
     *     @OA\Response(response="500", description=""),
     * )
     * Создать заявку на вызов курьера для забора груза
     * @param  int  $id
     * @param  OmsDeliveryService  $deliveryService
     * @return Response
     * @throws \Exception
     */
    public function createCourierCall(int $id, OmsDeliveryService $deliveryService): Response
    {
        $cargo = $deliveryService->getCargo($id);
        if (!$cargo) {
            throw new NotFoundHttpException('cargo not found');
        }
        $deliveryService->createCourierCall($cargo);

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/cargos/{id}/courier-call/cancel",
     *     tags={"Груз"},
     *     description="Отменить заявку на вызов курьера для забора груза.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="cargo not found"),
     * )
     * Отменить заявку на вызов курьера для забора груза
     * @param  int  $id
     * @param  OmsDeliveryService  $deliveryService
     * @return Response
     */
    public function cancelCourierCall(int $id, OmsDeliveryService $deliveryService): Response
    {
        $cargo = $deliveryService->getCargo($id);
        if (!$cargo) {
            throw new NotFoundHttpException('cargo not found');
        }
        $deliveryService->cancelCourierCall($cargo);

        return response('', 204);
    }

    /**
     * @OA\Get(
     *     path="api/v1/cargos/{id}/courier-call/check",
     *     tags={"Груз"},
     *     summary="Проверить наличие ошибок в заявке на вызов курьера во внешнем сервисе.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="cargo not found"),
     * )
     * Проверить наличие ошибок в заявке на вызов курьера во внешнем сервисе
     * @param int $id
     * @param OmsDeliveryService $deliveryService
     * @return Application|ResponseFactory|Response
     */
    public function checkExternalStatus(int $id, OmsDeliveryService $deliveryService)
    {
        $cargo = $deliveryService->getCargo($id);
        if (!$cargo) {
            throw new NotFoundHttpException('cargo not found');
        }
        $deliveryService->checkExternalStatus($cargo);

        return response('', 204);
    }
}
