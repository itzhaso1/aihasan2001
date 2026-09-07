<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileWorkspace;
use App\Http\Controllers\Api\Mobile\MobileController;
use App\Http\Resources\Mobile\AppointmentBookingResource;
use App\Models\Appointment\AppointmentBooking;
use App\Policies\Concerns\ChecksWorkspaceMembership;
use App\Services\Appointments\AppointmentService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Exceptions\HttpResponseException;
use RuntimeException;

class AppointmentController extends MobileController
{
    use ChecksWorkspaceMembership;
    use ResolvesMobileWorkspace;

    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function today(Request $request): JsonResponse
    {
        if (! Schema::hasTable('appointment_bookings')) {
            return $this->ok([]);
        }

        $this->requireWorkspace($this->workspaceContext);
        $perPage = max(1, min(50, (int) $request->input('per_page', 20)));

        $paginator = $this->appointmentService->listBookings([
            'date' => now()->toDateString(),
            'search' => $request->input('search'),
        ], $perPage);

        return $this->ok(AppointmentBookingResource::collection($paginator->items()), [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        if (! Schema::hasTable('appointment_bookings')) {
            return $this->ok([]);
        }

        $this->requireWorkspace($this->workspaceContext);
        $perPage = max(1, min(50, (int) $request->input('per_page', 20)));

        $paginator = $this->appointmentService->listBookings([
            'from_date' => now()->addDay()->toDateString(),
            'search' => $request->input('search'),
        ], $perPage);

        return $this->ok(AppointmentBookingResource::collection($paginator->items()), [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
        ]);
    }

    public function show(Request $request, AppointmentBooking $booking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);
        $booking->load(['service', 'staff', 'customer']);

        return $this->ok(new AppointmentBookingResource($booking));
    }

    public function confirm(Request $request, AppointmentBooking $booking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        try {
            $updated = $this->appointmentService->updateBookingStatus($booking, [
                'appointment_status' => 'confirmed',
            ]);
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        return $this->ok(new AppointmentBookingResource($updated->load(['service', 'staff', 'customer'])), message: 'تم تأكيد الموعد.');
    }

    public function cancel(Request $request, AppointmentBooking $booking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $updated = $this->appointmentService->cancelBooking($booking, $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        return $this->ok(new AppointmentBookingResource($updated->load(['service', 'staff', 'customer'])), message: 'تم إلغاء الموعد.');
    }

    public function reschedule(Request $request, AppointmentBooking $booking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        $validated = $request->validate([
            'starts_at' => ['required', 'string'],
            'ends_at' => ['nullable', 'string'],
            'staff_id' => ['nullable', 'integer'],
            'allow_custom_duration' => ['nullable', 'boolean'],
        ]);

        try {
            $updated = $this->appointmentService->rescheduleBooking(
                $booking,
                $validated,
                $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            return $this->fail($exception->getMessage(), 422);
        }

        return $this->ok(new AppointmentBookingResource($updated->load(['service', 'staff', 'customer'])), message: 'تمت إعادة جدولة الموعد.');
    }

    private function authorizeBooking(Request $request, AppointmentBooking $booking): void
    {
        $user = $request->user();
        $booking->loadMissing('workspace');

        if (! $user || ! $booking->workspace || ! $this->hasMembership($user, $booking->workspace)) {
            throw new HttpResponseException($this->fail('غير مصرح بالوصول إلى هذا الموعد.', 403));
        }
    }
}
