<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The store API answers many refusals with HTTP 200 and `result: false` because
 * the legacy Flutter app reads them that way. A client that sends
 * `X-Status-Mode: http` gets 422 for each of them instead, with the same body,
 * so it can branch on the status alone. Other answers (401, 403, 404, 429, 426)
 * and clients without the header are unchanged.
 */
class StrictResultStatus
{
    public const string HEADER = 'X-Status-Mode';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->header(self::HEADER) !== 'http'
            || ! $response instanceof JsonResponse
            || $response->getStatusCode() !== Response::HTTP_OK
        ) {
            return $response;
        }

        $data = $response->getData(true);

        if (is_array($data) && array_key_exists('result', $data) && $data['result'] === false) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }
}
