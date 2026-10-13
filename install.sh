#!/usr/bin/env bash
# Install tema CaggyShop. Jalankan sebagai root: bash install.sh [/var/www/pterodactyl]
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
VIEW="$PANEL/resources/views/templates/wrapper.blade.php"
DEST="$PANEL/public/themes/caggyshop"
DIR="$(cd "$(dirname "$0")" && pwd)"

[ -f "$VIEW" ] || { echo "wrapper.blade.php tidak ditemukan di $PANEL"; exit 1; }

mkdir -p "$DEST"
cp "$DIR/caggyshop.css" "$DIR/logo-16x9.png" "$DIR/logo-16x9-small.png" "$DEST/"

if grep -q "caggyshop-theme:start" "$VIEW"; then
  echo "Tema sudah terpasang, file CSS & logo diperbarui."
else
  [ -f "$VIEW.caggyshop.bak" ] || cp "$VIEW" "$VIEW.caggyshop.bak"
  V=$(date +%s)
  BLOCK="<!-- caggyshop-theme:start --><meta name=\"theme-color\" content=\"#070d12\"><link rel=\"stylesheet\" href=\"/themes/caggyshop/caggyshop.css?v=$V\"><!-- caggyshop-theme:end -->"
  sed -i "s~</head>~$BLOCK</head>~" "$VIEW"
fi

chown -R --reference="$PANEL/public" "$DEST" 2>/dev/null || true
cd "$PANEL" && (php artisan view:clear || true)
echo "Selesai. Buka panel lalu hard refresh (Ctrl+Shift+R)."
