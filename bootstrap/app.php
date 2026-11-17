<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->trustProxies(at: ['127.0.0.1', '172.16.0.0/12']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->inertia() && in_array($status, [419, 429], true)) {
                $message = $status === 419
                    ? 'This page was open a while. Please try again.'
                    : sprintf("You're moving fast. Try again in %d seconds.", $response->headers->get('Retry-After', '60'));

                return Inertia::flash('toast', ['type' => 'error', 'message' => $message])->back();
            }

            if (app()->environment(['local', 'testing']) || ! in_array($status, [403, 404, 500, 503], true)) {
                return $response;
            }

            if ($request->inertia() && ! $request->isMethod('GET')) {
                return Inertia::flash('toast', ['type' => 'error', 'message' => 'Something went wrong on our end. Please try again.'])->back();
            }

            return Inertia::render('ErrorPage', ['status' => $status])->toResponse($request)->setStatusCode($status);
        });
    })->create();
