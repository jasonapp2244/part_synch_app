#!/usr/bin/env bash
#
# One-command deploy for the Part-Synch server.
#
#   ./deploy.sh            # deploys main
#   ./deploy.sh staging    # deploys staging
#
# Safe to re-run. Never deletes untracked files: uploaded images live under
# storage/app/public/ and are NOT gitignored, so `git clean` would destroy
# them. This script only ever uses fetch/reset, never clean.

set -euo pipefail

cd "$(dirname "$0")"

BRANCH="${1:-main}"
REMOTE="${REMOTE:-origin}"

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die() { printf '\n\033[1;31mFAILED: %s\033[0m\n' "$*" >&2; exit 1; }

[ -d .git ] || die "no .git here - run the one-time bootstrap first (see README)"
[ -f artisan ] || die "not a Laravel root: $(pwd)"

php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
  || die "composer.json needs PHP >= 8.2, this box has $(php -r 'echo PHP_VERSION;')"

# Whatever happens below, don't leave the site stuck in maintenance mode.
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

say "Maintenance mode on"
php artisan down --retry=15 || true

say "Fetching $REMOTE/$BRANCH"
git fetch "$REMOTE" "$BRANCH"
git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH" "$REMOTE/$BRANCH"
git reset --hard "$REMOTE/$BRANCH"

say "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

say "Running migrations"
php artisan migrate --force

# Fills only Legal & Support documents that are still empty; never
# overwrites text edited in the admin panel, so it is safe on every deploy.
say "Seeding empty legal pages"
php artisan db:seed --class=LegalContentSeeder --force

say "Linking storage"
# public/storage must be a link to storage/app/public. On this server it was a
# plain folder, `storage:link` quietly did nothing, and every uploaded photo
# answered 404 (2026-10-21). Never delete it automatically: it may hold files.
if [ -e public/storage ] && [ ! -L public/storage ]; then
    printf '\n\033[1;31mWARNING: public/storage is a folder, not a link. Uploaded photos will 404.\033[0m\n'
    printf 'Fix once, in this folder:\n'
    printf '  cp -an public/storage/. storage/app/public/\n'
    printf '  mv public/storage public/storage.old-$(date +%%Y%%m%%d)\n'
    printf '  php artisan storage:link\n'
else
    php artisan storage:link 2>/dev/null || true
fi

say "Rebuilding caches"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
# NB: no `route:cache`. Three routes are closures (routes/web.php, routes/api.php)
# and Laravel cannot serialise those, so caching routes fails outright.
php artisan route:clear
php artisan config:cache
php artisan view:cache

say "Checking admin credentials"
# The admin row was created with its password in plain text, which
# Auth::attempt() can never match - the panel would reject the right password.
# Rehash it in place, preserving whatever the password already is. Self-skips
# once hashed, so this is a no-op on every later deploy.
php artisan tinker --execute='
$fixed = 0;
foreach (\App\Models\User::where("role_id", 1)->get() as $admin) {
    if (! \Illuminate\Support\Facades\Hash::isHashed($admin->password)) {
        $admin->password = $admin->password;
        $admin->save();
        $fixed++;
        echo "  rehashed admin password: {$admin->email}\n";
    }
}
echo $fixed ? "  {$fixed} fixed\n" : "  already hashed, nothing to do\n";
'

say "Fixing permissions"
# storage/ must belong to the user PHP runs as. That is the owner of this
# folder (the site user on CloudPanel), not necessarily www-data: chowning to
# www-data on CloudPanel made every Blade page fail with "tempnam(): file
# created in the system's temporary directory" (2026-10-21).
SITE_OWNER="$(stat -c %U .)"
SITE_GROUP="$(stat -c %G .)"
fix_permissions() {
    if [ "$(id -u)" = "0" ]; then
        chown -R "$SITE_OWNER:$SITE_GROUP" storage bootstrap/cache
    fi
    chmod -R 775 storage bootstrap/cache
}
fix_permissions

say "Restarting queue workers"
php artisan queue:restart || true

say "Maintenance mode off"
php artisan up
trap - EXIT

# queue:restart and up wrote into storage/framework as root; hand it back.
fix_permissions

printf '\n\033[1;32mDeployed %s @ %s\033[0m\n' "$BRANCH" "$(git rev-parse --short HEAD)"
git log -1 --format='%s' | sed 's/^/  /'
