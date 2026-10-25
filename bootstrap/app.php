<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureMfa;
use App\Http\Middleware\SecurityHeaders;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias([
            'active' => EnsureAccountActive::class,
            'mfa' => EnsureMfa::class,
            'perm' => CheckPermission::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // proksi tepercaya diatur lewat env TRUSTED_PROXIES bila di belakang reverse proxy
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Akses ditolak = peristiwa keamanan: wajib tercatat di audit trail (UAT-03/04).
        // (AuthorizationException tidak di-report oleh framework, maka dicatat saat dirender.)
        $denied = function (\Throwable $e, Request $request) {
            try {
                app(AuditService::class)->access('ACCESS_DENIED', $request->user(), [
                    'method' => $request->method(), 'path' => $request->path(), 'reason' => $e->getMessage(),
                ]);
            } catch (\Throwable) {
                // pencatatan tidak boleh menutupi respons 403
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return response()->view('errors.403', ['message' => $e->getMessage()], 403);
        };
        // framework mengubah AuthorizationException menjadi AccessDeniedHttpException sebelum render
        $exceptions->render(fn (AuthorizationException $e, Request $r) => $denied($e, $r));
        $exceptions->render(fn (AccessDeniedHttpException $e, Request $r) => $denied($e, $r));
    })->create();
