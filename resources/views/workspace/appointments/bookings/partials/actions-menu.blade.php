@php
    $status = (string) $booking->appointment_status;
    $actionKeys = match ($status) {
        'scheduled' => ['confirm', 'reschedule', 'cancel', 'reminder', 'payment'],
        'confirmed' => ['check_in', 'reschedule', 'cancel', 'reminder', 'payment'],
        'checked_in' => ['start'],
        'in_progress' => ['complete'],
        default => [],
    };
    $hasActions = ($canManageBookings || $canManageBilling) && $actionKeys !== [];
@endphp

@if($hasActions)
    <details class="relative">
        <summary class="flex cursor-pointer list-none items-center justify-center rounded-md border border-slate-300 px-2 py-1 text-sm font-semibold text-slate-700 hover:bg-slate-100">
            •••
        </summary>
        <div class="absolute left-0 z-20 mt-1 w-44 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
            @foreach($actionKeys as $actionKey)
                @if($actionKey === 'confirm' && $canManageBookings)
                    <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                        @csrf
                        <input type="hidden" name="status" value="confirmed">
                        <button class="block w-full rounded-md px-2 py-1 text-right text-xs text-slate-700 hover:bg-slate-100">Confirm</button>
                    </form>
                @endif

                @if($actionKey === 'check_in' && $canManageBookings)
                    <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                        @csrf
                        <input type="hidden" name="status" value="checked_in">
                        <button class="block w-full rounded-md px-2 py-1 text-right text-xs text-slate-700 hover:bg-slate-100">Check-in</button>
                    </form>
                @endif

                @if($actionKey === 'start' && $canManageBookings)
                    <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                        @csrf
                        <input type="hidden" name="status" value="in_progress">
                        <button class="block w-full rounded-md px-2 py-1 text-right text-xs text-slate-700 hover:bg-slate-100">Start appointment</button>
                    </form>
                @endif

                @if($actionKey === 'complete' && $canManageBookings)
                    <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                        @csrf
                        <input type="hidden" name="status" value="completed">
                        <button class="block w-full rounded-md px-2 py-1 text-right text-xs text-slate-700 hover:bg-slate-100">Complete</button>
                    </form>
                @endif

                @if($actionKey === 'cancel' && $canManageBookings)
                    <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                        @csrf
                        <input type="hidden" name="status" value="cancelled">
                        <button class="block w-full rounded-md px-2 py-1 text-right text-xs text-rose-700 hover:bg-rose-50">Cancel</button>
                    </form>
                @endif

                @if($actionKey === 'reschedule' && $canManageBookings)
                    <a href="{{ route('workspace.appointments.bookings.show', $booking) }}#reschedule-form" class="block rounded-md px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Reschedule</a>
                @endif

                @if($actionKey === 'reminder' && $canManageBookings)
                    <form method="POST" action="{{ route('workspace.appointments.bookings.send-reminder', $booking) }}">
                        @csrf
                        <input type="hidden" name="channel" value="in_app">
                        <input type="hidden" name="minutes_before" value="5">
                        <button class="block w-full rounded-md px-2 py-1 text-right text-xs text-slate-700 hover:bg-slate-100">Send reminder</button>
                    </form>
                @endif

                @if($actionKey === 'payment' && $canManageBilling)
                    @if($booking->payment_link)
                        <a href="{{ $booking->payment_link }}" target="_blank" class="block rounded-md px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Send payment link</a>
                    @else
                        <form method="POST" action="{{ route('workspace.appointments.bookings.payment-link', $booking) }}">
                            @csrf
                            <button class="block w-full rounded-md px-2 py-1 text-right text-xs text-slate-700 hover:bg-slate-100">Send payment link</button>
                        </form>
                    @endif
                @endif
            @endforeach
        </div>
    </details>
@endif
