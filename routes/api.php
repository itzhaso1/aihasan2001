<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AppointmentAiActionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\Cashier\V1\SyncController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('/social/token', [SocialAuthController::class, 'exchangeToken'])->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/employee-invitations/{token}/accept', [EmployeeController::class, 'acceptInvitation']);

    Route::get('/workspaces', [WorkspaceController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'workspace.resolve', 'workspace.member'])
    ->group(function (): void {
        Route::get('/workspace/{workspace}/current', [WorkspaceController::class, 'current']);

        Route::apiResource('/categories', CategoryController::class);
        Route::apiResource('/products', ProductController::class);
        Route::apiResource('/customers', CustomerController::class);
        Route::apiResource('/orders', OrderController::class);
        Route::apiResource('/conversations', ConversationController::class);
        Route::apiResource('/messages', MessageController::class)->only(['index', 'store', 'show']);
        Route::apiResource('/payments', PaymentController::class)->only(['index', 'store', 'show']);
        Route::apiResource('/subscriptions', SubscriptionController::class)->only(['index', 'store']);

        Route::get('/inventory/movements', [InventoryController::class, 'index']);
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);

        Route::get('/ai/settings', [AiController::class, 'settings']);
        Route::put('/ai/settings', [AiController::class, 'updateSettings']);
        Route::post('/ai/reply', [AiController::class, 'generateReply']);

        Route::get('/whatsapp/accounts', [WhatsAppController::class, 'index']);
        Route::post('/whatsapp/accounts', [WhatsAppController::class, 'storeAccount']);
        Route::post('/whatsapp/phone-numbers', [WhatsAppController::class, 'storePhoneNumber']);

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees/invite', [EmployeeController::class, 'invite']);
        Route::patch('/employees/{membership}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{membership}', [EmployeeController::class, 'destroy']);

        Route::get('/roles-permissions', [RolePermissionController::class, 'index']);
        Route::post('/roles-permissions/assign-role', [RolePermissionController::class, 'assignRole']);
        Route::post('/roles-permissions/sync-permissions', [RolePermissionController::class, 'syncPermissions']);

        Route::post('/appointments/ai/actions', [AppointmentAiActionController::class, 'execute']);
    });

Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');

/*
|--------------------------------------------------------------------------
| Hasim Mobile API v1
|--------------------------------------------------------------------------
| Final paths: /api/mobile/v1/...
| (Laravel already prefixes this file with /api + api middleware.)
*/
Route::prefix('mobile/v1')
    ->name('mobile.v1.')
    ->group(base_path('routes/mobile.php'));

/*
|--------------------------------------------------------------------------
| Hasim Cashier API v1 — كاشير حاسم
|--------------------------------------------------------------------------
| Final paths: /api/cashier/v1/...
| Separate product surface from Hasim Chat mobile API.
*/
Route::prefix('cashier/v1')
    ->name('cashier.v1.')
    ->group(base_path('routes/cashier.php'));

/*
|--------------------------------------------------------------------------
| POS sync aliases — same handlers as /api/cashier/v1/sync/*
|--------------------------------------------------------------------------
*/
Route::prefix('pos/sync')
    ->name('pos.sync.')
    ->middleware([
        'auth:sanctum',
        'workspace.resolve',
        'workspace.member',
        'throttle:cashier-api',
    ])
    ->group(function (): void {
        Route::post('/push', [SyncController::class, 'push'])->middleware('throttle:mobile-write');
        Route::post('/pull', [SyncController::class, 'pull']);
    });
