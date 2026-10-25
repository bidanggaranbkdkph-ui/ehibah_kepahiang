<?php

namespace App\Http\Middleware;

use App\Support\Permission;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pemeriksa matriks hak akses: ->middleware('perm:lpj,V'). */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $feature, string $level = 'V'): Response
    {
        $u = $request->user();
        if (! $u || ! Permission::allows($u, $feature, $level)) {
            throw new AuthorizationException("Tidak berwenang mengakses fitur '$feature'.");
        }

        return $next($request);
    }
}
