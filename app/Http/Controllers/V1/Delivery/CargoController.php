<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\DeliveryService;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\CreateAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Class CargoController
 * @package App\Http\Controllers\V1\Delivery
 */
class CargoController extends Controller
{
    use CountAction {
        count as countTrait;
    }
    use CreateAction {
        create as createTrait;
    }
    use ReadAction {
        read as readTrait;
    }
    use UpdateAction {
        update as updateTrait;
    }
    use DeleteAction {
        delete as deleteTrait;
    }
    
    /**
     * Получить класс модели в виде строки
     * Пример: return MyModel::class;
     * @return string
     */
    public function modelClass(): string
    {
        return Cargo::class;
    }
    
    /**
     * Задать права для выполнения стандартных rest действий.
     * Пример: return [ RestAction::$DELETE => 'permission' ];
     * @return array
     */
    public function permissionMap(): array
    {
        return [
            //todo Права доступа
        ];
    }
    
    /**
     * Получить список полей, которые можно редактировать через стандартные rest действия.
     * Пример return ['name', 'status'];
     * @return array
     */
    protected function writableFieldList(): array
    {
        return Cargo::FILLABLE;
    }
    
    /**
     * @return array
     */
    protected function inputValidators(): array
    {
        return [
            'merchant_id' => [new RequiredOnPost(), 'integer'],
            'store_id' => [new RequiredOnPost(), 'integer'],
            'status' => ['nullable', Rule::in(CargoStatus::validValues())],
            'delivery_service' => [new RequiredOnPost(), Rule::in(DeliveryService::validValues())],
            'xml_id' => ['nullable', 'string'],
        ];
    }
    
    /**
     * Подсчитать кол-во грузов
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->countTrait($request, $client);
    }
    
    /**
     * Создать груз
     * @param  Request  $request
     * @param  RequestInitiator $client
     * @return JsonResponse
     *
     * @OA\Post(
     *     path="/api/v1/cargo",
     *     tags={"cargo"},
     *     summary="Создать груз",
     *     operationId="createDelivery",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             //todo swagger
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="OK",
     *     ),
     * )
     */
    public function create(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->createTrait($request, $client);
    }
    
    /**
     * Список грузов / информация о грузе
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->readTrait($request, $client);
    }
    
    /**
     * Изменить груз
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @OA\Put(
     *     path="/api/v1/cargo/{id}",
     *     tags={"cargo"},
     *     summary="Изменить груз",
     *     operationId="updateСargo",
     *     @OA\Parameter(description="ID груза", in="path", name="id", required=true, @OA\Schema(type="integer")),
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             //todo swagger
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="OK",
     *     ),
     * )
     */
    public function update(int $id, Request $request, RequestInitiator $client): Response
    {
        return $this->updateTrait($id, $request, $client);
    }
    
    /**
     * Удалить груз
     * @param  int  $id
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \Exception
     *
     * @OA\Delete(
     *     path="/api/v1/cargo/{id}",
     *     tags={"cargo"},
     *     summary="Удалить груз",
     *     operationId="deleteСargo",
     *     @OA\Parameter(description="ID груза", in="path", name="id", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=204,
     *         description="OK",
     *     ),
     * )
     */
    public function delete(int $id, RequestInitiator $client): Response
    {
        return $this->deleteTrait($id, $client);
    }
}
