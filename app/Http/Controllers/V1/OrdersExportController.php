<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderExport;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OrdersExportController
 * @package App\Http\Controllers\V1
 */
class OrdersExportController extends Controller
{
    use CountAction {
        count as countTrait;
    }
    use ReadAction {
        read as readTrait;
    }
    use DeleteAction;
    use UpdateAction;

    public function modelClass(): string
    {
        return OrderExport::class;
    }

    /**
     * @inheritDoc
     */
    protected function writableFieldList(): array
    {
        return OrderExport::FILLABLE;
    }

    /**
     * @inheritDoc
     */
    protected function inputValidators(): array
    {
        return [
            'order_id' => [new RequiredOnPost(), 'int'],
            'merchant_integration_id' => [new RequiredOnPost(), 'int'],
            'order_xml_id' => [new RequiredOnPost(), 'string'],
        ];
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request, RequestInitiator $client)
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();

        $restQuery = new RestQuery($request);
        $orderId = $request->route('id');
        $exportId = $request->route('exportId');
        if ($orderId) {
            if ($exportId) {
                $query = $modelClass::modifyQuery(
                    $modelClass::query()
                    ->where('order_id', $orderId)
                    ->where('id', $exportId),
                    $restQuery
                );

                /** @var RestSerializable $model */
                $model = $query->first();
                if (!$model) {
                    throw new NotFoundHttpException();
                }

                $items = [
                    $model->toRest($restQuery),
                ];
            } else {
                $pagination = $restQuery->getPage();
                $baseQuery = $modelClass::query();
                if ($pagination) {
                    $baseQuery->offset($pagination['offset'])->limit($pagination['limit']);
                }
                $query = $modelClass::modifyQuery($baseQuery->where('order_id', $orderId), $restQuery);

                $items = $query->get()
                    ->map(function (RestSerializable $model) use ($restQuery) {
                        return $model->toRest($restQuery);
                    });
            }

            return response()->json([
                'items' => $items,
            ]);
        } else {
            return $this->readTrait($request, $client);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request, RequestInitiator $client)
    {
        $orderId = $request->route('id');
        if ($orderId) {
            /** @var Model|RestSerializable $modelClass */
            $modelClass = $this->modelClass();
            $restQuery = new RestQuery($request);

            $pagination = $restQuery->getPage();
            $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;

            $query = $modelClass::modifyQuery($modelClass::query()->where('order_id', $orderId), $restQuery);
            $total = $query->count();

            $pages = ceil($total / $pageSize);

            return response()->json([
                'total' => $total,
                'pages' => $pages,
                'pageSize' => $pageSize,
            ]);
        } else {
            return $this->countTrait($request, $client);
        }
    }

    public function create(int $orderId, Request $request): JsonResponse
    {
        /** @var Model $modelClass */
        $modelClass = $this->modelClass();
        $data = $request->only($this->writableFieldList());
        $data['order_id'] = $orderId;
        if ($this->isInvalid($data)) {
            throw new BadRequestHttpException($this->validationErrors->first());
        }
        /** @var OrderExport $model */
        $model = new $modelClass($data);
        $ok = $model->save();

        if (!$ok) {
            throw new HttpException(500);
        }

        return response()->json([
            'id' => $model->id,
        ], 201);
    }
}
