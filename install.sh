#!/usr/bin/env bash
# Install tema CaggyShop (glass). Jalankan sebagai root: bash install.sh [/var/www/pterodactyl]
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
VIEW="$PANEL/resources/views/templates/wrapper.blade.php"
DEST="$PANEL/public/themes/caggyshop"
DIR="$(cd "$(dirname "$0")" && pwd)"

[ -f "$VIEW" ] || { echo "wrapper.blade.php tidak ditemukan di $PANEL"; exit 1; }

mkdir -p "$DEST"
cp "$DIR/caggyshop.css" "$DIR/glass.js" "$DIR/logo-glass.png" "$DIR/logo-16x9.png" "$DIR/logo-16x9-small.png" "$DEST/"

# bg.gif: jangan timpa GIF pilihan sendiri kecuali dipaksa (FORCE_BG=1)
if [ ! -f "$DEST/bg.gif" ] || [ "${FORCE_BG:-0}" = "1" ]; then
  cp "$DIR/bg.gif" "$DEST/bg.gif"
else
  echo "bg.gif yang sudah ada dipertahankan (pakai FORCE_BG=1 untuk menimpa)."
fi

# Backup sekali saja, lalu pasang ulang blok (menggantikan blok versi lama jika ada)
[ -f "$VIEW.caggyshop.bak" ] || cp "$VIEW" "$VIEW.caggyshop.bak"
sed -i 's|<!-- caggyshop-theme:start -->.*<!-- caggyshop-theme:end -->||' "$VIEW"
V=$(date +%s)
BLOCK="<!-- caggyshop-theme:start --><meta name=\"theme-color\" content=\"#070d12\"><link rel=\"stylesheet\" href=\"/themes/caggyshop/caggyshop.css?v=$V\"><script defer src=\"/themes/caggyshop/glass.js?v=$V\"></script><!-- caggyshop-theme:end -->"
sed -i "s~</head>~$BLOCK</head>~" "$VIEW"

chown -R --reference="$PANEL/public" "$DEST" 2>/dev/null || true
cd "$PANEL" && (php artisan view:clear || true)
echo "Selesai. Buka panel lalu hard refresh (Ctrl+Shift+R)."
