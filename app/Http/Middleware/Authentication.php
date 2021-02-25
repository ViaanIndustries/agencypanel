<?php

namespace App\Http\Middleware;

use Closure;
use Session;
use Redirect;


class Authentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Session::has('user_id')) return Redirect::to('/');

        return $next($request);
    }
}
