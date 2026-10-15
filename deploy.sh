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
php artisan storage:link 2>/dev/null || true

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
if id -u www-data >/dev/null 2>&1; then
    chown -R www-data:www-data storage bootstrap/cache
fi
chmod -R 775 storage bootstrap/cache

say "Restarting queue workers"
php artisan queue:restart || true

say "Maintenance mode off"
php artisan up
trap - EXIT

printf '\n\033[1;32mDeployed %s @ %s\033[0m\n' "$BRANCH" "$(git rev-parse --short HEAD)"
git log -1 --format='%s' | sed 's/^/  /'
