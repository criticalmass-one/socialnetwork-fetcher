#!/usr/bin/env bash
#
# Deploys the current working tree to production.
#
# Run from the project root *after* git pull has brought the latest commit in.
# Defaults assume a Plesk host (PHP at /opt/plesk/php/8.5/bin/php,
# composer.phar at /usr/lib/plesk-9.0/composer.phar) — override any of the
# variables below via the environment if your layout differs:
#
#   PHP=/usr/bin/php COMPOSER=/usr/local/bin/composer ./deploy.sh
#
# What it does (in order):
#   1. composer install --no-dev (with classmap / authoritative autoloader)
#   2. doctrine:migrations:migrate
#   3. cache:clear + cache:warmup (prod)
#   4. asset-map:compile (writes hashed assets to public/assets/)
#   5. importmap:install (refresh JS dependencies into public/assets/)
#   6. reload PHP-FPM if the helper exists (Plesk: plesk repair web)
#
# Any failure aborts the script — `set -e` is on. The script does NOT run
# tests, run git pull, or touch your .env files; do that explicitly.

set -euo pipefail

# ---------- configurable paths --------------------------------------------- #

PHP="${PHP:-/opt/plesk/php/8.5/bin/php}"
COMPOSER="${COMPOSER:-/usr/lib/plesk-9.0/composer.phar}"
APP_ENV="${APP_ENV:-prod}"

# ---------- helpers --------------------------------------------------------- #

step() {
    printf '\n\033[1;34m==> %s\033[0m\n' "$*"
}

ok() {
    printf '\033[1;32m✓ %s\033[0m\n' "$*"
}

# ---------- sanity check ---------------------------------------------------- #

step "Sanity check"
if [[ ! -f composer.json ]] || [[ ! -d src ]]; then
    echo "✗ Not in project root (no composer.json / src/ found). Aborting." >&2
    exit 1
fi
if [[ ! -x "$PHP" ]]; then
    echo "✗ PHP binary not executable: $PHP" >&2
    exit 1
fi
if [[ ! -f "$COMPOSER" ]]; then
    echo "✗ Composer phar not found: $COMPOSER" >&2
    exit 1
fi

export APP_ENV
ok "PHP: $($PHP -v | head -n1)"
ok "Composer: $($PHP "$COMPOSER" --version | head -n1)"
ok "APP_ENV=$APP_ENV"

# ---------- 1. composer install -------------------------------------------- #

step "composer install (no-dev, optimized autoloader)"
$PHP "$COMPOSER" install \
    --no-dev \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction \
    --no-progress

# ---------- 2. database migrations ----------------------------------------- #

step "Database migrations"
$PHP bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration \
    --env="$APP_ENV"

# ---------- 3. cache rebuild ----------------------------------------------- #

step "Cache clear + warmup ($APP_ENV)"
$PHP bin/console cache:clear --env="$APP_ENV"
$PHP bin/console cache:warmup --env="$APP_ENV"

# ---------- 4. assets ------------------------------------------------------ #

step "Importmap install (downloads missing vendor JS into public/assets/)"
$PHP bin/console importmap:install --env="$APP_ENV"

step "Asset-map compile (writes hashed assets into public/assets/)"
# Drop the previous build first so removed files don't linger.
rm -rf public/assets
$PHP bin/console asset-map:compile --env="$APP_ENV"

# ---------- 5. PHP-FPM reload ---------------------------------------------- #

step "Reload PHP-FPM (optional)"
if command -v plesk >/dev/null 2>&1; then
    # Plesk: domain-bound; the operator usually does this via the panel.
    # Skip auto-execution here to avoid hitting other vhosts on the box.
    echo "  Plesk detected — restart PHP for this domain via the panel"
    echo "  (Plesk → Domain → PHP Settings → 'Apply').  Skipping auto-reload."
elif command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet php-fpm; then
    systemctl reload php-fpm
    ok "PHP-FPM reloaded via systemctl"
else
    echo "  No php-fpm service found via systemctl, and not a Plesk host."
    echo "  If your setup needs a reload, do it manually now."
fi

# ---------- done ----------------------------------------------------------- #

printf '\n\033[1;32m✓ Deploy complete.\033[0m\n'
