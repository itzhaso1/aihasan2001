# Custom Domains

## Overview

The custom domain module is implemented around `website_domains` with provider-agnostic services.

Core classes:

- `App\Services\Domain\DomainService`
- `App\Services\Domain\DnsService`
- `App\Services\Domain\SslService`
- `App\Services\Domain\Contracts\DomainRegistrarInterface`
- `App\Services\Domain\NamecheapRegistrar`

## Database Tables

- `website_domains`
- `website_domain_contacts`
- `website_domain_operations`

## Domain Types

- `platform_subdomain`
- `custom_domain`

## Domain Status Flow

Supported statuses:

- `pending`
- `registering`
- `registered`
- `dns_pending`
- `dns_configured`
- `verifying`
- `verified`
- `ssl_pending`
- `active`
- `failed`
- `expired`
- `cancelled`

## Dashboard Routes

Under `workspace/appointments/website/{website}/domains`:

- `GET /domains`
- `POST /domains/search`
- `POST /domains/purchase`
- `POST /domains/{domain}/set-primary`
- `POST /domains/{domain}/verify`
- `POST /domains/{domain}/renew`
- `POST /domains/{domain}/sync`
- `DELETE /domains/{domain}`

## Async Jobs

- `RegisterDomainJob`
- `ConfigureDomainDnsJob`
- `VerifyDomainJob`
- `ProvisionSslJob`
- `RenewDomainJob`
- `SyncDomainStatusJob`

## Scheduler

`routes/console.php` includes:

- `domains:sync-status`
- scheduled daily execution (`02:10`)

## DNS Provisioning

`DnsService` follows read-modify-write behavior:

1. read existing hosts
2. preserve non-platform records
3. enforce apex record (`WEBSITE_DNS_TARGET`)
4. enforce `www` CNAME target (`WEBSITE_DNS_WWW_TARGET`)
5. write full host set back via registrar

This prevents accidental deletion of unrelated customer DNS records.

## Primary Domain Rules

Only one domain per website can be primary (`is_primary = true`) and linked in `websites.primary_domain_id`.

Resolver cache is invalidated on website/domain save/delete via `WebsiteResolverObserver`.
# Custom Domains (Appointments Website Builder)

## Domain model

Domain data is stored in `website_domains` and includes:

- `workspace_id`, `website_id`
- `domain`, `normalized_domain`
- `type` (`platform_subdomain`, `custom_domain`)
- `provider`, `provider_domain_id`
- status fields:
  - `status`
  - `verification_status`
  - `dns_status`
  - `ssl_status`
- `expires_at`, `auto_renew`, `is_primary`, `metadata`

`normalized_domain` is unique to prevent collisions across websites.

## Domain statuses used

`pending`, `registering`, `registered`, `dns_pending`, `dns_configured`, `verifying`, `verified`, `ssl_pending`, `active`, `failed`, `expired`, `cancelled`

## Main flow

1. Search availability/pricing (`DomainService::searchDomains`).
2. Purchase domain (`DomainService::purchaseDomain`).
3. Register domain via queue job (`RegisterDomainJob`).
4. Configure DNS via queue job (`ConfigureDomainDnsJob`).
5. Verify DNS (`VerifyDomainJob` / manual verify action).
6. Request SSL provisioning (`ProvisionSslJob` abstraction).
7. Activate and optionally set primary domain.

## DNS handling

DNS configuration is implemented by `DnsService`:

1. Read existing hosts from registrar.
2. Preserve non-website records.
3. Upsert required website records (`@` and `www`) using configured targets.
4. Set merged record list with registrar API.
5. Verify DNS state after write.

Target values come from config/env (`WEBSITE_DNS_TARGET`, `WEBSITE_DNS_TARGET_TYPE`, etc.), never hardcoded in code.

## Background jobs

- `RegisterDomainJob`
- `ConfigureDomainDnsJob`
- `VerifyDomainJob`
- `ProvisionSslJob`
- `RenewDomainJob`
- `SyncDomainStatusJob`

Scheduled sync command:

- `domains:sync-status` (scheduled daily)

DNS verification retries:

- If verification does not pass immediately, the system keeps domain status in `dns_pending`.
- Retries are queued every `WEBSITE_DOMAIN_VERIFICATION_RETRY_SECONDS` (default 600).
- After `WEBSITE_DOMAIN_VERIFICATION_MAX_ATTEMPTS` (default 12), status is marked `failed`.

## Security/authorization

- Domain management routes require dashboard auth/workspace membership.
- Feature flags required: `website_builder`, `custom_domains`.
- Policies:
  - `WebsitePolicy::manageDomains`
  - `WebsiteDomainPolicy` actions (`setPrimary`, `renew`, `delete`, etc.)
- Domain search/purchase actions are rate limited.

## Notes on SSL

`SslService` provisions real certificates via Certbot when `WEBSITE_SSL_ENABLED=true`.
It inspects `/etc/letsencrypt/live/{domain}` and sets `ssl_status=active` **only** after OpenSSL validates the certificate host + expiry.
With SSL disabled, status remains honest `pending` (never fake-active).
See `docs/website-deployment.md` for Linux/Nginx requirements.

## Billing context

Purchasing a custom domain through the platform is **not** a subscription charge and **not** merchant GMV.
It is Platform commerce (see `docs/billing.md`, `docs/namecheap-integration.md`).
