#!/usr/bin/env bash
# Hapus tema CaggyShop. Jalankan sebagai root: bash uninstall.sh [/var/www/pterodactyl]
# Catatan: pengaturan dari halaman admin dan file logo/latar yang diunggah ikut terhapus.
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
VIEW="$PANEL/resources/views/templates/wrapper.blade.php"
DIR="$(cd "$(dirname "$0")" && pwd)"

# Halaman admin (jika terpasang)
if [ -f "$PANEL/app/Http/Controllers/Admin/CaggyThemeController.php" ] && [ -f "$DIR/caggyshop-settings/uninstall-settings.sh" ]; then
  bash "$DIR/caggyshop-settings/uninstall-settings.sh" "$PANEL"
fi

sed -i 's|<!-- caggyshop-theme:start -->.*<!-- caggyshop-theme:end -->||' "$VIEW"
rm -rf "$PANEL/public/themes/caggyshop"
cd "$PANEL" && (php artisan view:clear || true)
echo "Tema dihapus. Backup asli: $VIEW.caggyshop.bak"
