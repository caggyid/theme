#!/usr/bin/env bash
# Hapus tema CaggyShop. Jalankan sebagai root: bash uninstall.sh [/var/www/pterodactyl]
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
VIEW="$PANEL/resources/views/templates/wrapper.blade.php"
sed -i 's|<!-- caggyshop-theme:start -->.*<!-- caggyshop-theme:end -->||' "$VIEW"
rm -rf "$PANEL/public/themes/caggyshop"
cd "$PANEL" && (php artisan view:clear || true)
echo "Tema dihapus. Backup asli: $VIEW.caggyshop.bak"
