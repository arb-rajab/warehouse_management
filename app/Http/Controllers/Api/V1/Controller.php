<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * How many rows a list answers: the optional `per_page` parameter, at
     * least 1 and at most `$max`, else `$default` (what every list answered
     * before the parameter existed). A value over the cap is lowered to it.
     */
    protected function perPage(Request $request, int $default = 10, int $max = 50): int
    {
        $requested = filter_var($request->input('per_page'), FILTER_VALIDATE_INT);

        return $requested === false || $requested < 1 ? $default : min($requested, $max);
    }

    public function success($message)
    {
        return response()->json([
            'result' => true,
            'message' => $message
        ]);
    }

    public function failed($message)
    {
        return response()->json([
            'result' => false,
            'message' => $message
        ], 401);
    }
}
