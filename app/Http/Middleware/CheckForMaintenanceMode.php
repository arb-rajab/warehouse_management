<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckForMaintenanceMode
{
    /**
     * The application implementation.
     *
     * @var Application
     */
    protected $app;

    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array
     */
    protected $except = [
        '/admin*','/login','/logout', '/subcategories*', '/subsubcategories*', '/home_categories*', '/aiz-uploader*',
    ];

    /**
     * Create a new middleware instance.
     *
     * @param  Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws HttpException
     */
    public function handle($request, Closure $next)
    {
        if ($this->app->isDownForMaintenance()){
            if((Auth::check() && Auth::user()->user_type == 'admin') || (Auth::check() && Auth::user()->user_type == 'staff') || $this->inExceptArray($request)) {
                return $next($request);
            }
            else {
                return abort(503);
            }
        }

        return $next($request);
    }

    /**
     * Determine if the request has a URI that should be accessible in maintenance mode.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
