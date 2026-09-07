#!/usr/bin/env bash
# Linux helper for Certbot HTTP-01 provisioning used by SslService (certbot driver).
# Laravel may also invoke certbot directly when WEBSITE_SSL_* is configured.
set -euo pipefail

DOMAIN="${1:-}"
EMAIL="${WEBSITE_SSL_EMAIL:-}"
WEBROOT="${WEBSITE_SSL_WEBROOT:-/var/www/certbot}"
INCLUDE_WWW="${WEBSITE_SSL_INCLUDE_WWW:-1}"
RELOAD_CMD="${WEBSITE_SSL_RELOAD_COMMAND:-systemctl reload nginx}"

if [[ -z "$DOMAIN" || -z "$EMAIL" ]]; then
  echo "Usage: WEBSITE_SSL_EMAIL=ops@example.com $0 example.com" >&2
  exit 1
fi

ARGS=(certbot certonly --non-interactive --agree-tos --email "$EMAIL" --webroot -w "$WEBROOT" --cert-name "$DOMAIN" --keep-until-expiring -d "$DOMAIN")
if [[ "$INCLUDE_WWW" == "1" ]]; then
  ARGS+=(-d "www.$DOMAIN")
fi

"${ARGS[@]}"

if [[ -n "$RELOAD_CMD" ]]; then
  bash -lc "$RELOAD_CMD"
fi

echo "Provisioned certificate for $DOMAIN"
