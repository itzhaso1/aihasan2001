# Public Booking

## Goal

Expose a tenant-safe public booking funnel while reusing the existing appointments engine.

Core service:

- `App\Services\Website\PublicBookingService`

Reused core:

- `App\Services\Appointments\AppointmentService`
- `App\Services\Appointments\AppointmentBillingService`

## Public Endpoints

### Slug based (`/public/{website}`)

- `GET /api/services`
- `GET /api/services/{service}/staff`
- `GET /api/availability`
- `POST /api/booking/validate`
- `POST /api/booking`
- `GET /api/booking/{reference}`

### Host resolved (`/api/public/*`)

- `GET /services`
- `GET /services/{service}/staff`
- `GET /availability`
- `POST /booking/validate`
- `POST /booking`
- `GET /booking/{reference}`

## Validation and Security

- service and staff are constrained by website workspace
- booking total is never trusted from frontend
- booking is created from server-side service price/rules
- past slots are always rejected (even when min notice is 0)
- concurrent bookings use cache locks when available plus DB transactions/`lockForUpdate`
- unstaffed ("any staff") bookings enforce service capacity against overlapping active bookings
- guest customers are matched by phone then email within the same workspace before create
- booking dates in the past are rejected
- public writes are throttled

## Availability Rules

`AppointmentService::availableSlots` now applies:

- business timezone normalization
- service/staff availability
- slot interval
- booking window rules (min notice / max advance)
- holiday blocking (`appointment_holidays`)
- overlap checks against existing bookings
- past slot filtering

## Concurrency Protection

During public booking creation:

1. acquire cache lock by workspace/service/staff/slot key
2. run transactional booking creation
3. execute overlap/duplicate checks inside transaction with row locks

## Guest Booking

Customer can submit name/phone/email without dashboard authentication.
If email or phone maps to an existing workspace customer, booking links to that customer; otherwise a guest profile is stored on booking.

## Payment-required Services

If service requires payment:

- booking remains pending payment
- `AppointmentBillingService` creates invoice/order with `payment_context=merchant_booking`
- payment link is created **only if** merchant eligibility passes (plan feature + verification approved + provider active)
- if merchant is not eligible: booking/invoice may still exist, but **no** customer payment link toward Platform account; blocked reason stored in metadata
- Merchant GMV ≠ Platform Revenue (see `docs/merchant-payments.md`)

# Public Booking Flow

## Goal

Expose customer-facing booking on public websites while reusing the existing appointment engine.

## Endpoints

Under `/public/{website}/api`:

- `GET /services`
- `GET /services/{service}/staff`
- `GET /availability`
- `POST /booking/validate`
- `POST /booking`
- `GET /booking/{reference}`

Under host-resolved mode (custom domain / platform subdomain):

- `GET /api/public/services`
- `GET /api/public/services/{service}/staff`
- `GET /api/public/availability`
- `POST /api/public/booking/validate`
- `POST /api/public/booking`
- `GET /api/public/booking/{reference}`

Controllers:

- `App\Http\Controllers\PublicSite\PublicBookingApiController`

Middleware:

- `public.website.resolve`
- route throttling for abuse control

## Booking sequence

1. Client fetches services.
2. Client optionally fetches staff per service.
3. Client requests available slots for service/date/staff.
4. Client submits booking validation.
5. Client submits booking create.
6. Backend creates booking through `AppointmentService::createBooking`.
7. If payment required by service policy, booking billing flow is triggered via `AppointmentBillingService`.

## Security controls

- Website resolved first, then tenant context is set for request lifecycle.
- Service/staff/customer/resource lookups are workspace-bound.
- User payload cannot set `workspace_id`, price, duration, or booking status transitions.
- Public endpoints are rate limited.
- Past-time slots are rejected server-side.
- Final booking creation revalidates slot constraints in backend.

## Availability logic reuse

Availability uses existing appointment logic (`AppointmentService::availableSlots`) including:

- timezone
- working windows
- booking rules (notice/advance/buffer/interval)
- staff availability
- holiday blocks (`appointment_holidays`)
- overlap checks

## Concurrency protection

- Public flow uses lock key per workspace+service+staff+slot when lock-capable cache is available.
- Booking service performs final server-side overlap validation immediately before insert.
- Booking creation runs inside DB transaction.

## Frontend

Public booking UI is rendered in:

- `resources/views/public/website/show.blade.php`
- `resources/views/public/website/sections/booking_funnel.blade.php`

The funnel consumes the API endpoints above and never trusts computed totals from browser input.
