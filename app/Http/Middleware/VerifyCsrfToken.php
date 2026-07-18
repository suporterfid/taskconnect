<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken extends ValidateCsrfToken
{
    public function handle($request, Closure $next): Response
    {
        if ($this->app->runningUnitTests()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
