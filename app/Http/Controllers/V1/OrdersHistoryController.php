<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderHistoryEvent;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Http\Request;


/**
 * Class OrdersController
 * @package App\Http\Controllers\V1
 */
class OrdersHistoryController extends Controller
{
    public function list(Request $request)
    {
        $restQuery = new RestQuery($request);
        return response()->json([
            'items' => OrderHistoryEvent::findByRest($restQuery)->get(),
        ]);
    }
    
    public function count(Request $request)
    {
        $restQuery = new RestQuery($request);
        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : 10;
    
        $query = OrderHistoryEvent::findByRest($restQuery);
        $total = $query->count();
        $pages = ceil($total / $pageSize);
        
        return response()->json([
            'total' => $total,
            'pages' => $pages,
            'pageSize' => $pageSize,
        ]);
    }
}
