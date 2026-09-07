<?php

namespace App\Services\Mobile;

use App\Models\ApiIdempotencyKey;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyService
{
    public function find(User $user, string $key, ?string $route = null): ?ApiIdempotencyKey
    {
        return ApiIdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->when($route, fn ($q) => $q->where('route', $route))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function store(
        User $user,
        string $key,
        string $route,
        int $statusCode,
        array $body,
        ?Workspace $workspace = null,
        int $ttlHours = 24,
    ): ApiIdempotencyKey {
        return ApiIdempotencyKey::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'key' => $key,
                'route' => $route,
            ],
            [
                'workspace_id' => $workspace?->id,
                'status_code' => $statusCode,
                'response_body' => $body,
                'expires_at' => now()->addHours($ttlHours),
            ]
        );
    }

    public function replay(ApiIdempotencyKey $record): JsonResponse
    {
        return response()->json($record->response_body, $record->status_code);
    }

    public function keyFromRequest(Request $request): ?string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        return $key !== '' ? $key : null;
    }
}
