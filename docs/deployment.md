# Deployment (Linux)

Target production stack:

- **Linux** host
- **Nginx** reverse proxy (catch-all server for custom domains)
- **PHP-FPM** (PHP 8.3+)
- **Queue workers** (`php artisan queue:work`)
- **Scheduler** (`* * * * * php artisan schedule:run`)
- **Certbot** (or other ACME) for TLS
- Database + Redis/cache as configured

## Nginx sketch

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/hasem/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Use a catch-all / wildcard approach so customer domains hit Laravel; do **not** require one vhost file per tenant. Preserve `Host` and `X-Forwarded-*`.

## TLS / Certbot

SSL for custom domains is **not fully automatic inside the app alone**.

- App layer: `SslService` + provisioning jobs are abstractions.
- Infra: run Certbot (or CDN/ACME) on Linux against Nginx. Windows-only environments cannot complete real public certificate issuance the same way.

Example:

```bash
certbot --nginx -d example-customer.com -d www.example-customer.com
```

Automate renewals (`certbot renew` via cron/systemd timer).

## Queue & scheduler

Workers should process domain/DNS/SSL/payment/WhatsApp jobs. Scheduler covers:

- `domains:sync-status`
- appointment reminders prepare/dispatch
- other maintenance commands in `routes/console.php`

## Environment

Configure app URL, DB, cache, queue, mail, Namecheap, WhatsApp, AI keys, and payment webhook secrets before go-live.

HyperPay (merchant settlement — optional until contract ready):

```
HYPERPAY_ENABLED=false
HYPERPAY_ENTITY_ID=
HYPERPAY_ACCESS_TOKEN=
HYPERPAY_MERCHANT_ONBOARDING_ENABLED=false
HYPERPAY_MERCHANT_SANDBOX_AUTO_APPROVE=false
```

Never set `HYPERPAY_MERCHANT_SANDBOX_AUTO_APPROVE=true` in production.

Private merchant documents live under `storage/app/private` — ensure disk permissions and backups.

## Related docs

- `docs/website-deployment.md`
- `docs/custom-domains.md`
- `docs/webhook-setup.md`
- `docs/hyperpay.md`
- `docs/merchant-payments.md`
- `docs/billing.md`
