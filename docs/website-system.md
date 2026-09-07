# Website System

## Overview

This module adds a **public booking website layer** on top of the existing appointments core.

It does **not** replace appointment models/services.  
It reuses:

- `AppointmentService` for booking creation and availability rules
- `AppointmentBillingService` for payment-required bookings (merchant eligibility gated; see `docs/merchant-payments.md`)
- existing workspace tenancy (`workspace_id` + `WorkspaceScopedModel`)
- existing audit observer infrastructure

## Main Entities

- `website_templates`
- `websites`
- `website_pages`
- `website_sections`
- `website_assets`

## Lifecycle

1. Create website (`draft`)
2. Select template
3. Customize settings/theme/sections
4. Preview via secure preview token
5. Publish (`published`)
6. Unpublish (`unpublished`) or suspend (`suspended`)

## Dashboard Routes

Under `workspace/appointments`:

- `GET website` → overview
- `POST website` → create
- `GET website/{website}/templates`
- `POST website/{website}/template`
- `GET website/{website}/customize`
- `POST website/{website}/customize`
- `POST website/{website}/sections`
- `GET website/{website}/preview`
- `POST website/{website}/publish`
- `POST website/{website}/unpublish`

## Public Rendering

- Host-based resolver (`ResolvePublicWebsite` middleware):
  - `/`
  - `/booking`
  - `/contact`
  - `/robots.txt`
  - `/sitemap.xml`
- Slug-based fallback:
  - `/public/{website}`
  - `/public/{website}/booking`
  - `/public/{website}/contact`

## Section System

Sections are config-driven with `section_key`, `component_key`, `position`, `is_enabled`, and `config` JSON.

Current components:

- `hero`
- `about`
- `services_grid`
- `staff_grid`
- `booking_cta`
- `testimonials`
- `faq`
- `gallery`
- `contact`
- `business_hours`
- `footer`

No raw HTML is stored in DB.

## Templates

Seeded dynamically through `TemplateService`:

1. Dental Clinic - Modern
2. Medical Clinic - Classic
3. Beauty & Salon - Elegant
4. Law Firm - Professional
5. Consultant - Professional

## Feature Flags

Website module uses workspace features:

- `website_builder`
- `custom_domains`
- `public_booking`

Routes are protected with `workspace.feature` middleware.
# Website System (Appointments Module)

## Scope

The website module is layered on top of the existing appointment core. It does not replace booking models/services.

## Main entities

- `website_templates`
- `websites`
- `website_pages`
- `website_sections`
- `website_domains`
- `website_assets`
- `website_domain_operations`
- `website_domain_contacts`

All tenant-owned website entities include `workspace_id` and use workspace-scoped models.

## Lifecycle

Website states:

- `draft`
- `published`
- `unpublished`
- `suspended`

Publish checks:

1. Template selected.
2. Business name exists in settings.
3. At least one active/verified domain exists.

## Services

- `App\Services\Website\TemplateService`
  - seeds default templates
  - bootstraps sections/pages
- `App\Services\Website\WebsiteService`
  - create site, select template, customize, publish/unpublish
- `App\Services\Website\WebsiteResolverService`
  - resolve site by host or slug with cache
- `App\Services\Website\PublicWebsiteService`
  - builds section/view data from workspace + appointment data
- `App\Services\Website\PublicBookingService`
  - public services/staff/availability/booking APIs

## Dashboard routes

Under: `workspace.appointments.website.*`

- overview
- create
- templates/select
- customize/update
- sections/update
- preview
- publish/unpublish
- domains management pages/actions

Website and domain routes are guarded by:

- auth + workspace middleware
- `workspace.feature:website_builder`
- `workspace.feature:custom_domains` for domain actions
- policy checks (`WebsitePolicy`, `WebsiteDomainPolicy`)

## Public rendering routes

- Host-resolved mode:
  - `/` (home)
  - `/booking`
  - `/contact`
  - `/api/public/*` (public booking API on resolved host)
- Slug mode:
  - `/public/{website}`
  - `/public/{website}/{page}`
- Preview mode:
  - `/public-preview/{token}/{page?}`

Middleware `public.website.resolve` maps request host/slug to website + workspace context.

## SEO endpoints

- Host-resolved:
  - `/robots.txt`
  - `/sitemap.xml`
- Slug mode:
  - `/public/{website}/robots.txt`
  - `/public/{website}/sitemap.xml`

Draft/unpublished websites return restrictive robots behavior.

## Multi-tenancy

- Website models extend `WorkspaceScopedModel`.
- Public middleware sets workspace context per resolved website.
- Controllers enforce same-workspace checks and policy authorization.

## Caching

`WebsiteResolverService` caches host and slug lookups and invalidates on website/domain changes.

## Production hardening notes

- Logo renders in the public header (with text fallback).
- Social links are editable in the dashboard and rendered in the footer (`target=_blank`, `rel=noopener noreferrer`).
- Testimonials / FAQ / Gallery use structured dashboard editors (not raw JSON for end users).
- Public booking rejects past slots and enforces concurrency/capacity for unstaffed bookings.
- Domain lifecycle includes purchase recovery (`recovery_required`), auto-renew, expiration reminders, and real Certbot SSL verification (never fake-active).
- External/BYO DNS-provider connect is not implemented; Namecheap-registered domains are supported.
