<?php

namespace App\Http\Middleware\Mobile;

use App\Services\Mobile\IdempotencyService;
use App\Support\Tenancy\WorkspaceContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function __construct(
        private readonly IdempotencyService $idempotencyService,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $user = $request->user();
        $key = $this->idempotencyService->keyFromRequest($request);

        if (! $user || $key === null) {
            return $next($request);
        }

        $existing = $this->idempotencyService->find($user, $key, $request->path());
        if ($existing) {
            return $this->idempotencyService->replay($existing);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            $content = json_decode($response->getContent() ?: 'null', true);
            if (is_array($content)) {
                $this->idempotencyService->store(
                    user: $user,
                    key: $key,
                    route: $request->path(),
                    statusCode: $response->getStatusCode(),
                    body: $content,
                    workspace: $this->workspaceContext->workspace(),
                );
            }
        }

        return $response;
    }
}
