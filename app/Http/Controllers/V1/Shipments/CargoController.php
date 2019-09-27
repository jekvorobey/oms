<?php

namespace App\Http\Controllers\V1\Shipments;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CargoController extends Controller
{
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
