<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Basket;
use App\Models\BasketItem;
use Greensight\CommonMsa\Rest\Controller\CreateAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * Class BasketController
 * @package App\Http\Controllers\V1
 */
class BasketController extends Controller
{
    use DeleteAction;
    use CreateAction;
    use ReadAction;

    /**
     * Получить список полей, которые можно редактировать через стандартные rest действия.
     * Пример return ['name', 'status'];
     * @return array
     */
    protected function writableFieldList(): array
    {
        return Basket::FILLABLE;
    }

    /**
     * Получить класс модели в виде строки
     * Пример: return MyModel::class;
     * @return string
     */
    public function modelClass(): string
    {
        return Basket::class;
    }

    /**
     * Задать права для выполнения стандартных rest действий.
     * Пример: return [ RestAction::$DELETE => 'permission' ];
     * @return array
     */
    public function permissionMap(): array
    {
        return [
            // todo добавить необходимые права
        ];
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function items(int $id)
    {
        $items = BasketItem::where('basket_id', $id)->get();
        if(!$items) {
            throw new NotFoundHttpException('No items for this basket');
        }

        return response()->json(['items' => $items]);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function additem(int $id, Request $request)
    {
        /** @var BasketItem $item */
        $item = BasketItem::where(['basket_id' => $id, 'offer_id' => $request->offer_id])->first();
        if ($item) {
            $item->qty += $request->qty;
            if (!$item->save()) {
                throw new HttpException(500);
            }
        } else {
            $item = new BasketItem();
            $item->fill($request->all());
            $item->basket_id = $id;
            if (!$item->save()) {
                throw new HttpException(500);
            }
        }

        return response()->json(null, 204);
    }
}
