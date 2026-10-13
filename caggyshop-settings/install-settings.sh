#!/usr/bin/env bash
# Pasang halaman Admin > Theme CaggyShop (logo, latar, widget Discord).
# Biasanya dipanggil otomatis oleh ../install.sh. Bisa juga dijalankan sendiri:
#   bash install-settings.sh [/var/www/pterodactyl]
# Aman dijalankan ulang (mis. setelah php artisan p:upgrade).
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
DIR="$(cd "$(dirname "$0")" && pwd)"

ROUTES="$PANEL/routes/admin.php"
LAYOUT="$PANEL/resources/views/layouts/admin.blade.php"
CTRL="$PANEL/app/Http/Controllers/Admin/CaggyThemeController.php"
VIEWDIR="$PANEL/resources/views/admin/caggytheme"
THEME="$PANEL/public/themes/caggyshop"

for f in "$PANEL/artisan" "$ROUTES" "$LAYOUT" "$PANEL/app/Models/Server.php"; do
  [ -f "$f" ] || { echo "File tidak ditemukan: $f"; exit 1; }
done
OWNER="$(stat -c %U "$PANEL/artisan")"
NS="$(sed -n 's/^namespace \([A-Za-z]*\)\\.*/\1/p' "$PANEL/app/Models/Server.php" | head -1)"
NS="${NS:-Pterodactyl}"

runphp() { if [ "$(id -u)" = 0 ] && [ "$OWNER" != root ]; then runuser -u "$OWNER" -- php "$@"; else php "$@"; fi; }

# 1) Backup sekali saja
[ -f "$ROUTES.caggytheme.bak" ] || cp "$ROUTES" "$ROUTES.caggytheme.bak"
[ -f "$LAYOUT.caggytheme.bak" ] || cp "$LAYOUT" "$LAYOUT.caggytheme.bak"

# 2) Controller + view
cp "$DIR/CaggyThemeController.php" "$CTRL"
[ "$NS" = "Pterodactyl" ] || sed -i "s/Pterodactyl\\\\/${NS}\\\\/g" "$CTRL"
mkdir -p "$VIEWDIR"
cp "$DIR/caggy-theme.blade.php" "$VIEWDIR/index.blade.php"

# 3) routes/admin.php: ganti blok lama (jika ada), tambahkan di akhir file
sed -i -z 's|\n*// caggytheme:start.*// caggytheme:end\n*|\n|' "$ROUTES"
CLS="\\${NS}\\Http\\Controllers\\Admin\\CaggyThemeController"
{
  echo ""
  echo "// caggytheme:start"
  echo "Route::get('/caggytheme', [${CLS}::class, 'index'])->name('admin.caggytheme');"
  echo "Route::post('/caggytheme', [${CLS}::class, 'update'])->name('admin.caggytheme.update');"
  echo "// caggytheme:end"
} >> "$ROUTES"

# 4) Menu di sidebar admin: buang blok lama (jika ada), lalu sisipkan sebelum </ul> penutup sidebar-menu
sed -i '\|caggytheme-menu:start|,\|caggytheme-menu:end|d' "$LAYOUT"
export ITEM="$(cat <<'BLADE'
<li class="{{ request()->routeIs('admin.caggytheme*') ? 'active' : '' }}">
    <a href="{{ route('admin.caggytheme') }}">
        <i class="fa fa-paint-brush"></i> <span>Theme CaggyShop</span>
    </a>
</li>
BLADE
)"
# ITEM dibaca lewat ENVIRON (bukan awk -v) supaya tanda backslash tidak dimakan awk
if awk '
    /sidebar-menu/ { insb = 1 }
    insb && !done && /<\/ul>/ { print "{{-- caggytheme-menu:start --}}"; print ENVIRON["ITEM"]; print "{{-- caggytheme-menu:end --}}"; done = 1 }
    { print }
    END { if (!done) exit 3 }
  ' "$LAYOUT" > "$LAYOUT.tmp"; then
  cat "$LAYOUT.tmp" > "$LAYOUT"
else
  echo "PERINGATAN: sidebar admin tidak ditemukan di layouts/admin.blade.php (versi panel berbeda?)."
  echo "            Menu tidak dipasang; halaman tetap bisa dibuka di /admin/caggytheme."
fi
rm -f "$LAYOUT.tmp"

# 5) Berkas pengaturan awal + folder unggahan (tidak menimpa yang sudah ada)
mkdir -p "$THEME/uploads"
[ -f "$THEME/settings.css" ] || printf '/* Dibuat otomatis oleh Admin > Theme CaggyShop. */\n:root{}\n' > "$THEME/settings.css"
[ -f "$THEME/settings.js" ]  || printf '/* Dibuat otomatis oleh Admin > Theme CaggyShop. */\nwindow.CaggyTheme={};\n' > "$THEME/settings.js"
if [ "$(id -u)" = 0 ]; then
  chown "$OWNER": "$CTRL" "$ROUTES" "$LAYOUT" 2>/dev/null || true
  chown -R "$OWNER": "$VIEWDIR" "$THEME" 2>/dev/null || true
fi

# 6) Bersihkan cache
cd "$PANEL"
runphp artisan view:clear || true
runphp artisan route:clear || true

echo "Selesai. Buka Admin > Theme CaggyShop (atau /admin/caggytheme)."
