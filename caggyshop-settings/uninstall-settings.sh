#!/usr/bin/env bash
# Copot halaman Admin > Theme CaggyShop. Pengaturan & file unggahan di public/themes/caggyshop
# ikut hilang jika Anda menjalankan ../uninstall.sh (yang menghapus folder tema).
# Jalankan sebagai root: bash uninstall-settings.sh [/var/www/pterodactyl]
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
ROUTES="$PANEL/routes/admin.php"
LAYOUT="$PANEL/resources/views/layouts/admin.blade.php"
OWNER="$(stat -c %U "$PANEL/artisan")"
runphp() { if [ "$(id -u)" = 0 ] && [ "$OWNER" != root ]; then runuser -u "$OWNER" -- php "$@"; else php "$@"; fi; }

[ -f "$ROUTES" ] && sed -i -z 's|\n*// caggytheme:start.*// caggytheme:end\n*|\n|' "$ROUTES"
[ -f "$LAYOUT" ] && sed -i '\|caggytheme-menu:start|,\|caggytheme-menu:end|d' "$LAYOUT"
rm -f "$PANEL/app/Http/Controllers/Admin/CaggyThemeController.php"
rm -rf "$PANEL/resources/views/admin/caggytheme"
cd "$PANEL"; runphp artisan view:clear || true; runphp artisan route:clear || true
echo "Halaman admin tema dicopot. Backup asli: *.caggytheme.bak di sebelah file yang dipatch."
