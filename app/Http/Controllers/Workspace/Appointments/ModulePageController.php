<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentRequestSlot;
use App\Models\Appointment\AppointmentResource;
use App\Models\Appointment\AppointmentReminder;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WorkspaceUser;
use App\Services\Appointments\AppointmentRequestService;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ModulePageController extends AppointmentsBaseController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentRequestService $appointmentRequestService,
    ) {}

    public function overview(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.view');
        $workspace = $this->currentWorkspace();
        $this->appointmentService->ensureSetup($workspace);

        $setting = AppointmentSetting::query()->first();
        $timezone = $this->appointmentService->workspaceTimezone($workspace->id, $setting);
        [$todayStartUtc, $todayEndUtc] = $this->utcDayRange(now($timezone));

        $bookingsToday = AppointmentBooking::query()
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->count();

        $todayByStatus = AppointmentBooking::query()
            ->selectRaw('appointment_status, COUNT(*) as total')
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->groupBy('appointment_status')
            ->pluck('total', 'appointment_status')
            ->all();

        $pendingRequests = AppointmentRequest::query()
            ->whereIn('status', ['new', 'reviewing', 'awaiting_customer'])
            ->count();
        $needsConfirmationCount = AppointmentBooking::query()
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->where('appointment_status', 'scheduled')
            ->count();
        $inProgressCount = AppointmentBooking::query()
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->where('appointment_status', 'in_progress')
            ->count();
        $unpaidTodayCount = AppointmentBooking::query()
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->whereIn('payment_status', ['unpaid', 'pending', 'partially_paid'])
            ->count();

        $upcoming = AppointmentBooking::query()
            ->where('starts_at', '>=', now('UTC'))
            ->whereIn('appointment_status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->count();

        $noShowCount = AppointmentBooking::query()
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->where('appointment_status', 'no_show')
            ->count();
        $paymentByStatus = AppointmentBooking::query()
            ->selectRaw('payment_status, COUNT(*) as total')
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status')
            ->all();
        $todayInvoiceIds = AppointmentBooking::query()
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->whereNotNull('finance_invoice_id')
            ->pluck('finance_invoice_id')
            ->all();
        $revenueToday = $todayInvoiceIds === [] ? 0.0 : (float) FinanceInvoice::query()
            ->whereIn('id', $todayInvoiceIds)
            ->sum('amount_paid');
        $newRequestsCount = AppointmentRequest::query()->where('status', 'new')->count();
        $awaitingCustomerCount = AppointmentRequest::query()->where('status', 'awaiting_customer')->count();

        $latestBookings = AppointmentBooking::query()
            ->with(['service:id,name', 'staff:id,name'])
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $latestRequests = AppointmentRequest::query()
            ->with(['service:id,name', 'staff:id,name'])
            ->whereIn('status', ['new', 'reviewing', 'awaiting_customer'])
            ->latest('id')
            ->limit(6)
            ->get();

        return view('workspace.appointments.overview', [
            'timezone' => $timezone,
            'todayCards' => [
                'today' => $bookingsToday,
                'pending_requests' => $pendingRequests,
                'upcoming' => $upcoming,
                'needs_confirmation' => $needsConfirmationCount,
                'in_progress' => $inProgressCount,
                'confirmed' => (int) ($todayByStatus['confirmed'] ?? 0),
                'completed' => (int) ($todayByStatus['completed'] ?? 0),
                'cancelled' => (int) ($todayByStatus['cancelled'] ?? 0),
                'no_show' => $noShowCount,
            ],
            'todayByStatus' => $todayByStatus,
            'paymentCards' => [
                'paid' => (int) ($paymentByStatus['paid'] ?? 0),
                'pending' => (int) ($paymentByStatus['pending'] ?? 0),
                'unpaid' => (int) ($paymentByStatus['unpaid'] ?? 0),
                'revenue' => $revenueToday,
            ],
            'requestCards' => [
                'new' => $newRequestsCount,
                'awaiting_customer' => $awaitingCustomerCount,
                'needs_attention' => $pendingRequests,
            ],
            'attentionCards' => [
                'waiting_requests' => $pendingRequests,
                'unpaid_bookings' => $unpaidTodayCount,
                'needs_confirmation' => $needsConfirmationCount,
            ],
            'latestBookings' => $latestBookings,
            'latestRequests' => $latestRequests,
            'statusLabels' => $this->appointmentStatusLabels(),
            'requestStatusLabels' => $this->requestStatusLabels(),
        ]);
    }

    public function bookings(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.view');
        $workspace = $this->currentWorkspace();
        $setting = AppointmentSetting::query()->first();
        $timezone = $this->appointmentService->workspaceTimezone($workspace->id, $setting);

        $filters = [
            'date' => trim((string) $request->string('date')),
            'from_date' => trim((string) $request->string('from_date')),
            'to_date' => trim((string) $request->string('to_date')),
            'status' => trim((string) $request->string('status')),
            'payment_status' => trim((string) $request->string('payment_status')),
            'payment_bucket' => trim((string) $request->string('payment_bucket')),
            'staff_id' => $request->integer('staff_id') ?: null,
            'service_id' => $request->integer('service_id') ?: null,
            'source' => trim((string) $request->string('source')),
            'search' => trim((string) $request->string('search')),
            'timezone' => $timezone,
        ];

        if ($this->isStaffScoped($request)) {
            $filters['staff_user_id'] = (int) optional($request->user())->id;
        }

        $bookings = $this->appointmentService->listBookings($filters, 20);
        $stats = $this->bookingStats($timezone);
        $activeFiltersCount = collect([
            $filters['search'] ?? '',
            $filters['date'] ?? '',
            $filters['from_date'] ?? '',
            $filters['to_date'] ?? '',
            $filters['status'] ?? '',
            $filters['payment_status'] ?? '',
            $filters['payment_bucket'] ?? '',
            $filters['staff_id'] ?? null,
            $filters['service_id'] ?? null,
            $filters['source'] ?? '',
        ])->filter(function ($value): bool {
            if (is_numeric($value)) {
                return (int) $value > 0;
            }

            return trim((string) $value) !== '';
        })->count();

        return view('workspace.appointments.bookings.index', [
            'timezone' => $timezone,
            'filters' => $filters,
            'bookings' => $bookings,
            'bookingStats' => $stats,
            'activeFiltersCount' => $activeFiltersCount,
            'allServices' => AppointmentServiceModel::query()->orderBy('name')->get(['id', 'name', 'duration_minutes']),
            'allStaff' => AppointmentStaff::query()->orderBy('name')->get(['id', 'name']),
            'allResources' => AppointmentResource::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'statusLabels' => $this->appointmentStatusLabels(),
            'paymentStatusLabels' => $this->paymentStatusLabels(),
            'sourceLabels' => $this->sourceLabels(),
            'canManageBookings' => $this->hasPermission($request, 'appointments.manage'),
            'canManageBilling' => $this->hasPermission($request, 'appointments.billing.manage'),
            'canManageRequests' => $this->hasPermission($request, 'appointments.requests.manage'),
        ]);
    }

    public function bookingDetails(Request $request, AppointmentBooking $booking): View
    {
        $this->authorizeAppointments($request, 'appointments.view');
        if ($this->isStaffScoped($request) && (int) optional($booking->staff)->user_id !== (int) optional($request->user())->id) {
            abort(403);
        }

        $booking->load([
            'service',
            'staff',
            'customer',
            'request.slots',
            'resources',
            'booker',
            'invoice.payments',
            'order',
            'latestPayment',
        ]);

        $timezone = $this->appointmentService->workspaceTimezone($booking->workspace_id);
        $customerUpcomingBookings = collect();
        $customerPastBookings = collect();
        $lastCustomerAppointment = null;
        if ($booking->customer_id) {
            $customerUpcomingBookings = AppointmentBooking::query()
                ->where('customer_id', $booking->customer_id)
                ->where('starts_at', '>=', now('UTC'))
                ->where('id', '!=', $booking->id)
                ->orderBy('starts_at')
                ->limit(5)
                ->get(['id', 'booking_number', 'starts_at', 'appointment_status']);
            $customerPastBookings = AppointmentBooking::query()
                ->where('customer_id', $booking->customer_id)
                ->where('starts_at', '<', now('UTC'))
                ->where('id', '!=', $booking->id)
                ->orderByDesc('starts_at')
                ->limit(5)
                ->get(['id', 'booking_number', 'starts_at', 'appointment_status']);
            $lastCustomerAppointment = $customerPastBookings->first();
        }

        return view('workspace.appointments.bookings.show', [
            'booking' => $booking,
            'timezone' => $timezone,
            'statusLabels' => $this->appointmentStatusLabels(),
            'paymentStatusLabels' => $this->paymentStatusLabels(),
            'sourceLabels' => $this->sourceLabels(),
            'timelineEntries' => $this->buildBookingTimeline($booking),
            'customerUpcomingBookings' => $customerUpcomingBookings,
            'customerPastBookings' => $customerPastBookings,
            'lastCustomerAppointment' => $lastCustomerAppointment,
            'allStaff' => AppointmentStaff::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManageBookings' => $this->hasPermission($request, 'appointments.manage'),
            'canManageBilling' => $this->hasPermission($request, 'appointments.billing.manage'),
            'canManageRequests' => $this->hasPermission($request, 'appointments.requests.manage'),
        ]);
    }

    public function calendar(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.calendar.view');
        $workspace = $this->currentWorkspace();
        $timezone = $this->appointmentService->workspaceTimezone($workspace->id);

        return view('workspace.appointments.calendar', [
            'timezone' => $timezone,
            'defaultDate' => now($timezone)->toDateString(),
            'allStaff' => AppointmentStaff::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function requests(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.requests.view');
        $workspace = $this->currentWorkspace();
        $timezone = $this->appointmentService->workspaceTimezone($workspace->id);

        $filters = [
            'date' => trim((string) $request->string('date')),
            'status' => trim((string) $request->string('status')),
            'source' => trim((string) $request->string('source')),
            'search' => trim((string) $request->string('search')),
            'timezone' => $timezone,
        ];

        if ($this->isStaffScoped($request)) {
            $filters['staff_user_id'] = (int) optional($request->user())->id;
        }

        $requestSummary = AppointmentRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('workspace.appointments.requests.index', [
            'timezone' => $timezone,
            'filters' => $filters,
            'appointmentRequests' => $this->appointmentRequestService->listRequests($filters, 20),
            'requestSummary' => $requestSummary,
            'allServices' => AppointmentServiceModel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'allStaff' => AppointmentStaff::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'requestStatusLabels' => $this->requestStatusLabels(),
            'sourceLabels' => $this->sourceLabels(),
            'canManageRequests' => $this->hasPermission($request, 'appointments.requests.manage'),
        ]);
    }

    public function requestDetails(Request $request, AppointmentRequest $appointmentRequest): View
    {
        $this->authorizeAppointments($request, 'appointments.requests.view');
        if ($this->isStaffScoped($request) && (int) optional($appointmentRequest->staff)->user_id !== (int) optional($request->user())->id) {
            abort(403);
        }

        $appointmentRequest->load(['service', 'staff', 'customer', 'slots', 'booking', 'conversation']);
        $timezone = $this->appointmentService->workspaceTimezone($appointmentRequest->workspace_id);

        return view('workspace.appointments.requests.show', [
            'appointmentRequest' => $appointmentRequest,
            'timezone' => $timezone,
            'requestStatusLabels' => $this->requestStatusLabels(),
            'statusLabels' => $this->appointmentStatusLabels(),
            'sourceLabels' => $this->sourceLabels(),
            'timelineEntries' => $this->buildRequestTimeline($appointmentRequest),
            'canManageRequests' => $this->hasPermission($request, 'appointments.requests.manage'),
        ]);
    }

    public function customers(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.view');
        $search = trim((string) $request->string('search'));
        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $customerIds = $customers->pluck('id')->all();
        $bookingCounts = AppointmentBooking::query()
            ->selectRaw('customer_id, COUNT(*) as total')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id')
            ->all();
        $upcomingCounts = AppointmentBooking::query()
            ->selectRaw('customer_id, COUNT(*) as total')
            ->whereIn('customer_id', $customerIds)
            ->where('starts_at', '>=', now('UTC'))
            ->whereIn('appointment_status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id')
            ->all();
        $lastAppointments = AppointmentBooking::query()
            ->selectRaw('customer_id, MAX(starts_at) as last_starts_at')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->pluck('last_starts_at', 'customer_id')
            ->all();

        $requestCounts = AppointmentRequest::query()
            ->selectRaw('customer_id, COUNT(*) as total')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id')
            ->all();
        $invoicePaidTotals = FinanceInvoice::query()
            ->selectRaw('customer_id, SUM(amount_paid) as total_paid')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->pluck('total_paid', 'customer_id')
            ->all();
        $outstandingTotals = FinanceInvoice::query()
            ->selectRaw('customer_id, SUM(amount_due) as total_due')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->pluck('total_due', 'customer_id')
            ->all();

        return view('workspace.appointments.customers.index', [
            'customers' => $customers,
            'search' => $search,
            'bookingCounts' => $bookingCounts,
            'upcomingCounts' => $upcomingCounts,
            'lastAppointments' => $lastAppointments,
            'requestCounts' => $requestCounts,
            'invoicePaidTotals' => $invoicePaidTotals,
            'outstandingTotals' => $outstandingTotals,
            'timezone' => $this->appointmentService->workspaceTimezone(),
        ]);
    }

    public function settings(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $workspace = $this->currentWorkspace();
        $this->appointmentService->ensureSetup($workspace);

        $setting = AppointmentSetting::query()->first();
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
        $businessHours = $metadata['business_hours'] ?? [];
        $bookingRules = $metadata['booking_rules'] ?? [];
        $cancellationRules = $metadata['cancellation_rules'] ?? [];
        $reminderChannels = is_array($metadata['reminder_channels'] ?? null) ? $metadata['reminder_channels'] : ['in_app'];

        return view('workspace.appointments.settings.index', [
            'setting' => $setting,
            'businessHours' => $businessHours,
            'bookingRules' => $bookingRules,
            'cancellationRules' => $cancellationRules,
            'reminderChannels' => $reminderChannels,
            'services' => AppointmentServiceModel::query()
                ->with('staffMembers:id,name')
                ->latest('id')
                ->paginate(10, ['*'], 'services_page'),
            'staff' => AppointmentStaff::query()
                ->with(['user', 'services:id,name'])
                ->latest('id')
                ->paginate(10, ['*'], 'staff_page'),
            'resources' => AppointmentResource::query()
                ->latest('id')
                ->paginate(10, ['*'], 'resources_page'),
            'allServices' => AppointmentServiceModel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'allStaff' => AppointmentStaff::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workspaceUsers' => WorkspaceUser::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->with('user')
                ->orderBy('membership_role')
                ->get(),
            'weekDays' => $this->weekDays(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function appointmentStatusLabels(): array
    {
        return [
            'scheduled' => 'مجدول',
            'confirmed' => 'مؤكد',
            'checked_in' => 'تم تسجيل الحضور',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            'no_show' => 'لم يحضر',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function requestStatusLabels(): array
    {
        return [
            'new' => 'جديد',
            'reviewing' => 'قيد المراجعة',
            'awaiting_customer' => 'بانتظار العميل',
            'approved' => 'تمت الموافقة',
            'rejected' => 'مرفوض',
            'expired' => 'منتهي',
            'cancelled' => 'ملغي',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentStatusLabels(): array
    {
        return [
            'unpaid' => 'غير مدفوع',
            'pending' => 'قيد الانتظار',
            'paid' => 'مدفوع',
            'failed' => 'فشل الدفع',
            'refunded' => 'تم الاسترجاع',
            'partially_paid' => 'مدفوع جزئيًا',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sourceLabels(): array
    {
        return [
            'dashboard' => 'لوحة التحكم',
            'phone' => 'هاتف',
            'walk_in' => 'زيارة مباشرة',
            'website' => 'الموقع',
            'whatsapp' => 'واتساب',
            'ai_chat' => 'مساعد AI',
            'email' => 'البريد',
            'api' => 'API',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildBookingTimeline(AppointmentBooking $booking): array
    {
        $entries = [];

        $entityMap = [
            AppointmentBooking::class => ['id' => $booking->id, 'label' => 'الحجز'],
        ];

        if ($booking->request_id) {
            $entityMap[AppointmentRequest::class] = ['id' => $booking->request_id, 'label' => 'الطلب'];
            $slotIds = AppointmentRequestSlot::query()
                ->where('request_id', $booking->request_id)
                ->pluck('id')
                ->all();
            foreach ($slotIds as $slotId) {
                $entityMap[AppointmentRequestSlot::class.'#'.$slotId] = ['id' => $slotId, 'label' => 'الوقت المقترح', 'entity_type' => AppointmentRequestSlot::class];
            }
        }
        if ($booking->finance_invoice_id) {
            $entityMap[FinanceInvoice::class] = ['id' => $booking->finance_invoice_id, 'label' => 'الفاتورة'];
            $invoicePaymentIds = FinanceInvoicePayment::query()
                ->where('invoice_id', $booking->finance_invoice_id)
                ->pluck('id')
                ->all();
            foreach ($invoicePaymentIds as $invoicePaymentId) {
                $entityMap[FinanceInvoicePayment::class.'#'.$invoicePaymentId] = [
                    'id' => $invoicePaymentId,
                    'label' => 'دفعة الفاتورة',
                    'entity_type' => FinanceInvoicePayment::class,
                ];
            }
        }
        if ($booking->order_id) {
            $entityMap[Order::class] = ['id' => $booking->order_id, 'label' => 'طلب الدفع'];
        }
        if ($booking->latest_payment_id) {
            $entityMap[Payment::class] = ['id' => $booking->latest_payment_id, 'label' => 'الدفع'];
        }
        $reminderIds = AppointmentReminder::query()
            ->where('booking_id', $booking->id)
            ->pluck('id')
            ->all();
        foreach ($reminderIds as $reminderId) {
            $entityMap[AppointmentReminder::class.'#'.$reminderId] = [
                'id' => $reminderId,
                'label' => 'التذكير',
                'entity_type' => AppointmentReminder::class,
            ];
        }

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where(function ($query) use ($entityMap): void {
                foreach ($entityMap as $key => $entity) {
                    $entityType = (string) ($entity['entity_type'] ?? $key);
                    $query->orWhere(function ($inner) use ($entityType, $entity): void {
                        $inner->where('entity_type', $entityType)->where('entity_id', $entity['id']);
                    });
                }
            })
            ->orderBy('occurred_at')
            ->get();

        foreach ($logs as $log) {
            $entries[] = [
                'time' => $log->occurred_at,
                'title' => $this->auditTitle($log->action, $log->entity_type),
                'description' => $this->auditDescription($log->old_values, $log->new_values),
                'actor' => $log->user?->name ?: 'النظام',
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRequestTimeline(AppointmentRequest $appointmentRequest): array
    {
        $entityMap = [
            [AppointmentRequest::class, $appointmentRequest->id],
        ];
        foreach ($appointmentRequest->slots as $slot) {
            $entityMap[] = [AppointmentRequestSlot::class, $slot->id];
        }
        if ($appointmentRequest->booking) {
            $entityMap[] = [AppointmentBooking::class, $appointmentRequest->booking->id];
        }

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where(function ($query) use ($entityMap): void {
                foreach ($entityMap as [$type, $id]) {
                    $query->orWhere(function ($inner) use ($type, $id): void {
                        $inner->where('entity_type', $type)->where('entity_id', $id);
                    });
                }
            })
            ->orderBy('occurred_at')
            ->get();

        $entries = [];
        foreach ($logs as $log) {
            $entries[] = [
                'time' => $log->occurred_at,
                'title' => $this->auditTitle($log->action, $log->entity_type),
                'description' => $this->auditDescription($log->old_values, $log->new_values),
                'actor' => $log->user?->name ?: 'النظام',
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, int>
     */
    private function bookingStats(string $timezone): array
    {
        [$todayStartUtc, $todayEndUtc] = $this->utcDayRange(now($timezone));
        $nowUtc = now('UTC');

        $today = AppointmentBooking::query()
            ->whereBetween('starts_at', [$todayStartUtc, $todayEndUtc])
            ->count();
        $upcoming = AppointmentBooking::query()
            ->where('starts_at', '>', $nowUtc)
            ->whereIn('appointment_status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->count();
        $completed = AppointmentBooking::query()->where('appointment_status', 'completed')->count();
        $cancelled = AppointmentBooking::query()->where('appointment_status', 'cancelled')->count();
        $noShow = AppointmentBooking::query()->where('appointment_status', 'no_show')->count();
        $needsConfirmation = AppointmentBooking::query()
            ->where('appointment_status', 'scheduled')
            ->where('starts_at', '>=', $nowUtc)
            ->count();
        $paymentAttention = AppointmentBooking::query()
            ->whereIn('payment_status', ['unpaid', 'pending', 'partially_paid'])
            ->whereIn('appointment_status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->count();

        return compact('today', 'upcoming', 'needsConfirmation', 'completed', 'cancelled', 'noShow', 'paymentAttention');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utcDayRange(Carbon $localDate): array
    {
        return [
            $localDate->copy()->startOfDay()->utc(),
            $localDate->copy()->endOfDay()->utc(),
        ];
    }

    private function auditTitle(string $action, string $entityType): string
    {
        $typeLabel = match ($entityType) {
            AppointmentBooking::class => 'الحجز',
            AppointmentRequest::class => 'طلب الموعد',
            AppointmentRequestSlot::class => 'الوقت المقترح',
            FinanceInvoice::class => 'الفاتورة',
            Order::class => 'طلب الدفع',
            Payment::class => 'الدفعة',
            FinanceInvoicePayment::class => 'دفعة الفاتورة',
            AppointmentReminder::class => 'تذكير الموعد',
            default => 'العنصر',
        };

        $actionLabel = match ($action) {
            'created' => 'تم الإنشاء',
            'updated' => 'تم التحديث',
            'deleted' => 'تم الحذف',
            default => $action,
        };

        return "{$actionLabel} - {$typeLabel}";
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function auditDescription(?array $oldValues, ?array $newValues): string
    {
        if ($newValues === null || $newValues === []) {
            return 'لا توجد تفاصيل إضافية.';
        }

        $keys = collect(array_keys($newValues))
            ->reject(fn ($key): bool => in_array($key, ['updated_at', 'created_at'], true))
            ->take(4)
            ->implode('، ');

        if ($keys === '') {
            return 'تم تحديث السجل.';
        }

        return 'تغييرات على: '.$keys;
    }

    /**
     * @return array<string, string>
     */
    private function weekDays(): array
    {
        return [
            'sun' => 'الأحد',
            'mon' => 'الاثنين',
            'tue' => 'الثلاثاء',
            'wed' => 'الأربعاء',
            'thu' => 'الخميس',
            'fri' => 'الجمعة',
            'sat' => 'السبت',
        ];
    }

    private function hasPermission(Request $request, string $permission): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return $user->can($permission)
            || $user->can('workspace.manage')
            || in_array($this->activeMembershipRole($request), ['owner', 'admin', 'manager'], true);
    }
}
