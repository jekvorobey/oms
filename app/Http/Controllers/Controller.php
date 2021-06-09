<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @OA\Info(title="Документация к API LENNUF-OMS", version="1.0")
 * @OA\Server(
 *      url="http://localhost/",
 *      description="L5 Swagger OpenApi Localhost Server"
 * )
 * Class Controller
 * @package App\Http\Controllers
 */
class Controller extends BaseController
{
    protected function validate(Request $request, array $rules): array
    {
        $data = $request->all();
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        return $data;
    }
}
