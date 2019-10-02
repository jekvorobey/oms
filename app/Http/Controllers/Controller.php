<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="IEP OMS API",
 *      description="API сервиса OMS",
 *      @OA\Contact(
 *          email="koryukov@greensight.ru"
 *      )
 * )
 *
 * @OA\Schema(
 *     schema="CreateResult",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int32"
 *     )
 * )
 *  @OA\Schema(
 *     schema="CountResult",
 *     @OA\Property(
 *         property="total",
 *         type="integer",
 *         format="int32"
 *     ),
 *     @OA\Property(
 *         property="page",
 *         type="integer",
 *         format="int32"
 *     ),
 *     @OA\Property(
 *         property="pageSize",
 *         type="integer",
 *         format="int32"
 *     )
 * )
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
