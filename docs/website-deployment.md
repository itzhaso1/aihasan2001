# Website + Domain Deployment Notes (Linux / Nginx)

## Architecture (no per-customer Nginx vhost required)

Custom domains are accepted by a **catch-all** Nginx server and resolved inside Laravel:

```
Host header
  → Nginx (catch-all server_name _)
  → PHP-FPM / Laravel
  → ResolvePublicWebsite middleware
  → website_domains.normalized_domain
  → Website + WorkspaceContext
  → Public Blade render / Public Booking API
```

You do **not** need a separate Nginx `server {}` block per customer domain.
You **do** need:

1. Wildcard DNS for platform subdomains (`*.WEBSITE_PLATFORM_DOMAIN`)
2. `WEBSITE_DNS_TARGET` pointing apex custom domains at your edge IP/CNAME
3. HTTPS certificates for each custom hostname (Certbot HTTP-01 recommended)
4. Queue workers + scheduler

Slug fallback always works: `/public/{slug}`

## Nginx sketch (catch-all + ACME webroot)

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root /var/www/app/public;
    index index.php;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/certbot;
        default_type "text/plain";
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}

# TLS termination can use:
# - a second catch-all 443 server with ssl_certificate map, or
# - Certbot nginx plugin / certbot certificates per domain with a shared template.
```

## Real SSL flow (Certbot)

App-side (`SslService` + `ProvisionSslJob`):

1. Domain DNS verified
2. Job runs `certbot certonly --webroot`
3. Reads `/etc/letsencrypt/live/{domain}/fullchain.pem`
4. Parses certificate with OpenSSL
5. Sets `ssl_status=active` **only** when certificate is present, covers host, and not expired

Required env:

```dotenv
WEBSITE_SSL_ENABLED=true
WEBSITE_SSL_DRIVER=certbot
WEBSITE_SSL_EMAIL=ops@your-domain.com
WEBSITE_SSL_WEBROOT=/var/www/certbot
WEBSITE_SSL_LIVE_PATH=/etc/letsencrypt/live
WEBSITE_SSL_RELOAD_COMMAND="systemctl reload nginx"
```

Helper script: `scripts/ssl/provision-domain.sh`

Linux host requirements:

- `certbot` installed
- webroot writable and served at `/.well-known/acme-challenge/`
- PHP process user allowed to execute certbot (sudoers or dedicated deploy user)
- Nginx reload permission for `WEBSITE_SSL_RELOAD_COMMAND`

If `WEBSITE_SSL_ENABLED=false`, domain stays `ssl_status=pending` (honest pending — never fake active).

## Queue workers

```bash
php artisan queue:work --queue=default --sleep=1 --tries=5
```

Jobs:

- `RegisterDomainJob`
- `ConfigureDomainDnsJob`
- `VerifyDomainJob`
- `ProvisionSslJob`
- `RenewDomainJob`
- `SyncDomainStatusJob`

## Scheduler

Crontab:

```bash
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled domain tasks:

- `domains:sync-status` daily
- `domains:expiration-reminders` daily
- `domains:auto-renew` daily
- `domains:ssl-maintain` daily

## Key environment variables

```dotenv
WEBSITE_PLATFORM_DOMAIN=your-platform-domain.com
WEBSITE_DNS_TARGET=203.0.113.10
WEBSITE_DNS_TARGET_TYPE=A
WEBSITE_DNS_TTL=300
WEBSITE_DNS_WWW_TARGET=@
NAMECHEAP_ENV=sandbox
NAMECHEAP_API_USER=
NAMECHEAP_API_KEY=
NAMECHEAP_USERNAME=
NAMECHEAP_CLIENT_IP=
```

## External / BYO domains

Bring-your-own domain DNS provider OAuth/connect is **not implemented**.
Registrar-purchased Namecheap domains are supported end-to-end.
Future BYO can attach rows in `website_domains` with `provider=external` once a safe verification path exists — do not fake verification.

## Troubleshooting

| Symptom | Check |
|---|---|
| Custom host 404 | Domain status published? Resolver cache? `normalized_domain`? |
| SSL stays pending | `WEBSITE_SSL_ENABLED`, certbot logs, ACME webroot reachability |
| DNS verify fails | Apex A/AAAA/CNAME to `WEBSITE_DNS_TARGET`, www CNAME to `WEBSITE_DNS_WWW_TARGET` |
| Domain purchase stuck `recovery_required` | Provider getInfo ownership, then re-run register job / sync |
| Prices show `-` | Namecheap `users.getPricing` credentials + IP whitelist |
