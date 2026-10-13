#!/usr/bin/env bash
# Install tema CaggyShop (glass). Jalankan sebagai root: bash install.sh [/var/www/pterodactyl]
# Otomatis memasang juga halaman Admin > Theme CaggyShop (logo, latar, widget Discord).
# Lewati halaman admin dengan: NO_ADMIN=1 bash install.sh
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
VIEW="$PANEL/resources/views/templates/wrapper.blade.php"
DEST="$PANEL/public/themes/caggyshop"
DIR="$(cd "$(dirname "$0")" && pwd)"

[ -f "$VIEW" ] || { echo "wrapper.blade.php tidak ditemukan di $PANEL"; exit 1; }

mkdir -p "$DEST/uploads"
cp "$DIR/caggyshop.css" "$DIR/glass.js" "$DIR/logo-glass.png" "$DIR/logo-16x9.png" "$DIR/logo-16x9-small.png" "$DEST/"

# bg.gif: jangan timpa GIF pilihan sendiri kecuali dipaksa (FORCE_BG=1)
if [ ! -f "$DEST/bg.gif" ] || [ "${FORCE_BG:-0}" = "1" ]; then
  cp "$DIR/bg.gif" "$DEST/bg.gif"
else
  echo "bg.gif yang sudah ada dipertahankan (pakai FORCE_BG=1 untuk menimpa)."
fi

# Berkas pengaturan dari halaman admin: dibuat kosong bila belum ada, tidak pernah ditimpa
[ -f "$DEST/settings.css" ] || printf '/* Dibuat otomatis oleh Admin > Theme CaggyShop. */\n:root{}\n' > "$DEST/settings.css"
[ -f "$DEST/settings.js" ]  || printf '/* Dibuat otomatis oleh Admin > Theme CaggyShop. */\nwindow.CaggyTheme={};\n' > "$DEST/settings.js"

# Backup sekali saja, lalu pasang ulang blok (menggantikan blok versi lama jika ada)
[ -f "$VIEW.caggyshop.bak" ] || cp "$VIEW" "$VIEW.caggyshop.bak"
sed -i 's|<!-- caggyshop-theme:start -->.*<!-- caggyshop-theme:end -->||' "$VIEW"
V=$(date +%s)
# settings.css/js diberi nomor versi dari waktu ubah file (Blade), supaya perubahan dari admin langsung terbaca browser
MT_CSS="{{ is_file(public_path('themes/caggyshop/settings.css')) ? filemtime(public_path('themes/caggyshop/settings.css')) : 0 }}"
MT_JS="{{ is_file(public_path('themes/caggyshop/settings.js')) ? filemtime(public_path('themes/caggyshop/settings.js')) : 0 }}"
BLOCK="<!-- caggyshop-theme:start --><meta name=\"theme-color\" content=\"#070d12\"><link rel=\"stylesheet\" href=\"/themes/caggyshop/caggyshop.css?v=$V\"><link rel=\"stylesheet\" href=\"/themes/caggyshop/settings.css?v=$MT_CSS\"><script defer src=\"/themes/caggyshop/settings.js?v=$MT_JS\"></script><script defer src=\"/themes/caggyshop/glass.js?v=$V\"></script><!-- caggyshop-theme:end -->"
sed -i "s~</head>~$BLOCK</head>~" "$VIEW"

chown -R --reference="$PANEL/public" "$DEST" 2>/dev/null || true
cd "$PANEL" && (php artisan view:clear || true)

# Halaman admin (logo / latar / widget Discord)
if [ "${NO_ADMIN:-0}" != "1" ] && [ -f "$DIR/caggyshop-settings/install-settings.sh" ]; then
  bash "$DIR/caggyshop-settings/install-settings.sh" "$PANEL"
fi

echo "Selesai. Buka panel lalu hard refresh (Ctrl+Shift+R)."
