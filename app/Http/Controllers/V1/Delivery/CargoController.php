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
    use CountAction;
    use CreateAction;
    use ReadAction;
    use UpdateAction;
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
}
