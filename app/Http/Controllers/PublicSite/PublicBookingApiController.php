<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Website\Website;
use App\Services\Website\PublicBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PublicBookingApiController extends Controller
{
    public function __construct(
        private readonly PublicBookingService $publicBookingService,
    ) {}

    public function servicesResolved(Request $request): JsonResponse
    {
        $resolved = $this->resolvedWebsiteFromRequest($request);

        return response()->json([
            'data' => $this->publicBookingService->listServices($resolved),
        ]);
    }

    public function services(string $website): JsonResponse
    {
        $resolved = $this->resolveWebsite($website);

        return response()->json([
            'data' => $this->publicBookingService->listServices($resolved),
        ]);
    }

    public function staffResolved(Request $request, int $service): JsonResponse
    {
        $resolved = $this->resolvedWebsiteFromRequest($request);

        return response()->json([
            'data' => $this->publicBookingService->listStaffForService($resolved, $service),
        ]);
    }

    public function staff(string $website, int $service): JsonResponse
    {
        $resolved = $this->resolveWebsite($website);

        return response()->json([
            'data' => $this->publicBookingService->listStaffForService($resolved, $service),
        ]);
    }

    public function availabilityResolved(Request $request): JsonResponse
    {
        $resolved = $this->resolvedWebsiteFromRequest($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
        ]);

        try {
            $data = $this->publicBookingService->availability($resolved, $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $data]);
    }

    public function availability(Request $request, string $website): JsonResponse
    {
        $resolved = $this->resolveWebsite($website);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
        ]);

        try {
            $data = $this->publicBookingService->availability($resolved, $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $data]);
    }

    public function validateBookingResolved(Request $request): JsonResponse
    {
        $resolved = $this->resolvedWebsiteFromRequest($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->publicBookingService->validateBooking($resolved, $validated);

        return response()->json(['data' => $result], ($result['valid'] ?? false) ? 200 : 422);
    }

    public function validateBooking(Request $request, string $website): JsonResponse
    {
        $resolved = $this->resolveWebsite($website);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->publicBookingService->validateBooking($resolved, $validated);

        return response()->json(['data' => $result], ($result['valid'] ?? false) ? 200 : 422);
    }

    public function storeBookingResolved(Request $request): JsonResponse
    {
        $resolved = $this->resolvedWebsiteFromRequest($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $booking = $this->publicBookingService->createBooking($resolved, $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $booking], 201);
    }

    public function storeBooking(Request $request, string $website): JsonResponse
    {
        $resolved = $this->resolveWebsite($website);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $booking = $this->publicBookingService->createBooking($resolved, $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $booking], 201);
    }

    public function showBookingResolved(Request $request, string $reference): JsonResponse
    {
        $resolved = $this->resolvedWebsiteFromRequest($request);
        $booking = $this->publicBookingService->bookingReference($resolved, $reference);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        return response()->json(['data' => $booking]);
    }

    public function showBooking(string $website, string $reference): JsonResponse
    {
        $resolved = $this->resolveWebsite($website);
        $booking = $this->publicBookingService->bookingReference($resolved, $reference);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        return response()->json(['data' => $booking]);
    }

    private function resolveWebsite(string $slug): Website
    {
        return Website::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function resolvedWebsiteFromRequest(Request $request): Website
    {
        $website = $request->attributes->get('website');
        abort_unless($website instanceof Website, 404);
        abort_unless($website->status === 'published', 404);

        return $website;
    }
}
