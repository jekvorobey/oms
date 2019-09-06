<?php

namespace App\Http\Controllers\V1;

use App\Core\OrderReader;
use App\Http\Controllers\Controller;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Http\Request;

/**
 * Class OrdersController
 * @package App\Http\Controllers\V1
 */
class OrdersController extends Controller
{
    public function read(Request $request)
    {
        $reader = new OrderReader();
        return response()->json([
            'items' => $reader->list(new RestQuery($request)),
        ]);
    }
    
    public function count(Request $request)
    {
        $reader = new OrderReader();
        return response()->json($reader->count(new RestQuery($request)));
    }
}
