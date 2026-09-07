<?php

use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Http\Middleware\EnsureWorkspaceMembership;
use App\Http\Middleware\ResolvePublicWebsite;
use App\Http\Middleware\ResolveWorkspaceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('platform')
                ->group(base_path('routes/platform.php'));

            // Mobile API v1 is loaded from routes/api.php (prefix: api/mobile/v1)
            // so it is always registered with the primary api route file.
        },
    )
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/resend',
            'whatsapp-webhook',
        ]);

        $middleware->alias([
            'workspace.resolve' => ResolveWorkspaceContext::class,
            'workspace.member' => EnsureWorkspaceMembership::class,
            'workspace.selected' => EnsureWorkspaceSelected::class,
            'workspace.feature' => EnsureFeatureAccess::class,
            'public.website.resolve' => ResolvePublicWebsite::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'mobile.idempotency' => \App\Http\Middleware\Mobile\EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/mobile/*') || $request->is('api/mobile/v1/*') || $request->is('api/cashier/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'انتهت جلسة تسجيل الدخول.',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->is('api/mobile/*') || $request->is('api/cashier/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا تملك صلاحية تنفيذ هذا الإجراء.',
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/mobile/*') || $request->is('api/cashier/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'العنصر المطلوب غير موجود.',
                ], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/mobile/*') || $request->is('api/cashier/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صالحة.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
