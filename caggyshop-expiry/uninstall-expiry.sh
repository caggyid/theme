#!/usr/bin/env bash
# Copot fitur expired. Data di tabel server_expirations TIDAK dihapus.
# Jalankan sebagai root: bash uninstall-expiry.sh [/var/www/pterodactyl]
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
CRON_DIR="${CRON_DIR:-/etc/cron.d}"
DETAILS="$PANEL/resources/views/admin/servers/view/details.blade.php"
ROUTES="$PANEL/routes/admin.php"
API_ROUTES="$PANEL/routes/api-client.php"
OWNER="$(stat -c %U "$PANEL/artisan")"
runphp() { if [ "$(id -u)" = 0 ] && [ "$OWNER" != root ]; then runuser -u "$OWNER" -- php "$@"; else php "$@"; fi; }

[ -f "$DETAILS" ] && sed -i '\|caggyshop-expiry:start|,\|caggyshop-expiry:end|d' "$DETAILS"
[ -f "$ROUTES" ]  && sed -i -z 's|\n*// caggyshop-expiry:start.*// caggyshop-expiry:end\n*|\n|' "$ROUTES"
[ -f "$API_ROUTES" ] && sed -i -z 's|\n*// caggyshop-expiry:start.*// caggyshop-expiry:end\n*|\n|' "$API_ROUTES"
rm -f "$PANEL/app/Http/Controllers/Api/Client/CaggyExpiryClientController.php"
rm -f "$PANEL/resources/views/admin/servers/partials/caggy-expiry.blade.php" \
      "$PANEL/app/Http/Controllers/Admin/CaggyExpiryController.php" \
      "$CRON_DIR/caggyshop-expiry"
rm -rf "$PANEL/caggyshop-expiry"
cd "$PANEL"; runphp artisan view:clear || true; runphp artisan route:clear || true
echo "Dicopot. Backup asli: *.caggyshop-expiry.bak di sebelah file yang dipatch."
