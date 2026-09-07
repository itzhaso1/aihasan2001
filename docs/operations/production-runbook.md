# Production Operations Runbook

## Monitoring

- **Application logs**: `storage/logs/laravel.log`
- **Queue failures**: `failed_jobs` table + `php artisan queue:failed`
- **Webhook events**: `webhook_events` table (`status`, `failed_reason`, `processed_at`)
- **AI usage/events**: `ai_logs` table
- **Audit trail**: `audit_logs` table
- **Health check**: `GET /up`

## Queue Workers

Run dedicated workers in production:

```bash
php artisan queue:work --queue=default --tries=3 --backoff=5
```

Recommended: Supervisor/Systemd with auto-restart.

## Backups

### Database
- Frequency: every 6 hours
- Retention: 14 days hot + 90 days cold storage
- Verify restore weekly in staging

### Files
- Include `storage/app` (especially `public/workspaces/*`)
- Frequency: daily snapshots
- Retention: 30 days

### Restore Strategy
1. Restore DB snapshot to point-in-time target.
2. Restore storage snapshot matching or nearest to DB timestamp.
3. Run smoke checks:
   - Login
   - Workspace access
   - Order + payment flow
   - WhatsApp webhook endpoint reachability

## Security Checklist

- Set all provider credentials in `.env`, never in code.
- Enforce HTTPS and secure cookies.
- Rotate webhook secrets and API keys periodically.
- Restrict file upload MIME and size (implemented for product images).
- Enable central error tracking (Sentry/Bugsnag) before production launch.

