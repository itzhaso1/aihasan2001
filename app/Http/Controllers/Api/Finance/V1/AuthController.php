<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Exceptions\Api\ApiErrorCode;
use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Feature\FeatureAccessService;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Mobile\MobileAuthService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Sanctum\NewAccessToken;

class AuthController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly MobileAuthService $mobileAuthService,
        private readonly FeatureAccessService $featureAccessService,
        private readonly FinanceClientPresenter $presenter,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without_all:phone,email_or_phone', 'nullable', 'string'],
            'phone' => ['required_without_all:email,email_or_phone', 'nullable', 'string'],
            'email_or_phone' => ['required_without_all:email,phone', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'workspace_id' => ['nullable', 'integer'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:32'],
        ]);

        $identifier = trim((string) ($validated['email_or_phone'] ?? $validated['email'] ?? $validated['phone'] ?? ''));
        if ($identifier === '') {
            return $this->fail('يرجى إدخال البريد الإلكتروني أو رقم الجوال.', ApiErrorCode::ValidationFailed, 422);
        }

        try {
            $result = $this->mobileAuthService->loginWithPassword(
                emailOrPhone: $identifier,
                password: $validated['password'],
                workspaceId: isset($validated['workspace_id']) ? (int) $validated['workspace_id'] : null,
                device: [
                    'device_name' => $validated['device_name'] ?? 'حاسم للمالية',
                    'device_type' => $validated['device_type'] ?? 'finance',
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                ],
            );
        } catch (AuthenticationException) {
            return $this->fail('بيانات الدخول غير صحيحة.', ApiErrorCode::Unauthorized, 401);
        } catch (ModelNotFoundException $exception) {
            return $this->fail($exception->getMessage(), ApiErrorCode::NotFound, 404);
        }

        return $this->sessionEnvelope($result, 'تم تسجيل الدخول بنجاح.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return $this->ok(message: 'تم إرسال رابط إعادة تعيين كلمة المرور.');
        }

        return $this->fail('تعذر إرسال رابط إعادة التعيين. تحقق من البريد الإلكتروني.', ApiErrorCode::ValidationFailed, 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->ok(message: 'تم إعادة تعيين كلمة المرور بنجاح.');
        }

        return $this->fail('تعذر إعادة تعيين كلمة المرور. تحقق من الرابط أو البريد.', ApiErrorCode::ValidationFailed, 422);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->mobileAuthService->logoutCurrent($user, $user->currentAccessToken());
        }

        return $this->ok(message: 'تم تسجيل الخروج بنجاح.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaces = $user->workspaces()->wherePivot('status', 'active')->get();
        $workspaceId = $user->currentAccessToken()?->workspace_id;
        $workspace = $workspaceId
            ? $workspaces->firstWhere('id', $workspaceId)
            : $workspaces->first();

        if ($workspace) {
            $this->workspaceContext->set($workspace);
        }

        return $this->ok($this->sessionPayload($user, $workspace, $workspaces));
    }

    /**
     * @param  array{user:User,workspace:Workspace|null,workspaces:Collection,token:NewAccessToken}  $result
     */
    private function sessionEnvelope(array $result, string $message): JsonResponse
    {
        $payload = $this->sessionPayload($result['user'], $result['workspace'], $result['workspaces']);
        $payload['token'] = $result['token']->plainTextToken;
        $payload['token_type'] = 'Bearer';
        $payload['expires_at'] = optional($result['token']->accessToken->expires_at)?->toIso8601String();

        return $this->ok($payload, message: $message);
    }

    /**
     * @param  Collection<int, Workspace>  $workspaces
     * @return array<string, mixed>
     */
    private function sessionPayload(User $user, mixed $workspace, $workspaces): array
    {
        return [
            'user' => $this->presenter->user($user),
            'workspace' => $workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug ?? null,
                'type' => $workspace->type,
                'finance_enabled' => $this->featureAccessService->workspaceHasFeature($workspace, 'finance'),
            ] : null,
            'workspaces' => $this->presenter->workspaces(
                $workspaces,
                fn ($item) => $this->featureAccessService->workspaceHasFeature($item, 'finance'),
            ),
            'permissions' => $workspace ? $this->financePermissionMap($user, $workspace) : [],
            'finance_enabled' => $workspace
                ? $this->featureAccessService->workspaceHasFeature($workspace, 'finance')
                : false,
        ];
    }
}
