#!/usr/bin/env bash
# Pasang auto-suspend expired + field expired di admin (Servers > Details).
# Jalankan sebagai root: bash install-expiry.sh [/var/www/pterodactyl]
# Aman dijalankan ulang (mis. setelah php artisan p:upgrade).
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
DIR="$(cd "$(dirname "$0")" && pwd)"
CRON_DIR="${CRON_DIR:-/etc/cron.d}"

DETAILS="$PANEL/resources/views/admin/servers/view/details.blade.php"
ROUTES="$PANEL/routes/admin.php"
PARTIAL="$PANEL/resources/views/admin/servers/partials/caggy-expiry.blade.php"
CTRL="$PANEL/app/Http/Controllers/Admin/CaggyExpiryController.php"
DEST="$PANEL/caggyshop-expiry"

for f in "$PANEL/artisan" "$DETAILS" "$ROUTES" "$PANEL/app/Models/Server.php"; do
  [ -f "$f" ] || { echo "File tidak ditemukan: $f"; exit 1; }
done
OWNER="$(stat -c %U "$PANEL/artisan")"
NS="$(sed -n 's/^namespace \([A-Za-z]*\)\\.*/\1/p' "$PANEL/app/Models/Server.php" | head -1)"
NS="${NS:-Pterodactyl}"

runphp() { if [ "$(id -u)" = 0 ] && [ "$OWNER" != root ]; then runuser -u "$OWNER" -- php "$@"; else php "$@"; fi; }

# 1) Backup sekali saja
[ -f "$DETAILS.caggyshop-expiry.bak" ] || cp "$DETAILS" "$DETAILS.caggyshop-expiry.bak"
[ -f "$ROUTES.caggyshop-expiry.bak" ]  || cp "$ROUTES" "$ROUTES.caggyshop-expiry.bak"

# 2) Controller + partial view
cp "$DIR/CaggyExpiryController.php" "$CTRL"
[ "$NS" = "Pterodactyl" ] || sed -i "s/Pterodactyl\\\\/${NS}\\\\/g" "$CTRL"
cp "$DIR/caggy-expiry.blade.php" "$PARTIAL"

# 3) details.blade.php: sisipkan @include tepat sebelum @endsection penutup section 'content'
if ! grep -q 'caggyshop-expiry:start' "$DETAILS"; then
  awk -v inc="@include('admin.servers.partials.caggy-expiry')" '
    /@section\(.content.\)/ { insec = 1 }
    insec && !done && /@endsection/ {
      print "{{-- caggyshop-expiry:start --}}"; print inc; print "{{-- caggyshop-expiry:end --}}"; done = 1
    }
    { print }
    END { if (!done) exit 3 }
  ' "$DETAILS" > "$DETAILS.tmp" || { rm -f "$DETAILS.tmp"; echo "Gagal menemukan section 'content' di details.blade.php (versi panel berbeda?)"; exit 1; }
  cat "$DETAILS.tmp" > "$DETAILS"; rm -f "$DETAILS.tmp"
else
  echo "details.blade.php sudah dipatch."
fi

# 4) routes/admin.php: ganti blok lama (jika ada), tambahkan di akhir file
sed -i -z 's|\n*// caggyshop-expiry:start.*// caggyshop-expiry:end\n*|\n|' "$ROUTES"
CLS="\\${NS}\\Http\\Controllers\\Admin\\CaggyExpiryController"
{
  echo ""
  echo "// caggyshop-expiry:start"
  echo "Route::post('/servers/view/{server}/expiry', [${CLS}::class, 'update'])->name('admin.servers.view.expiry');"
  echo "// caggyshop-expiry:end"
} >> "$ROUTES"

# 5) Skrip CLI + tabel + cron
mkdir -p "$DEST"
cp "$DIR/expiry.php" "$DEST/expiry.php"
if [ "$(id -u)" = 0 ]; then
  chown "$OWNER": "$CTRL" "$PARTIAL" "$DETAILS" "$ROUTES" 2>/dev/null || true
  chown -R "$OWNER": "$DEST"
fi
runphp "$DEST/expiry.php" install

cat > "$CRON_DIR/caggyshop-expiry" <<CRON
*/5 * * * * $OWNER php $DEST/expiry.php run >> $PANEL/storage/logs/caggyshop-expiry.log 2>&1
CRON
chmod 644 "$CRON_DIR/caggyshop-expiry"

# 6) Bersihkan cache
cd "$PANEL"
runphp artisan view:clear || true
runphp artisan route:clear || true

echo "Selesai. Buka Admin > Servers > (pilih server) > Details, lalu hard refresh (Ctrl+Shift+R)."
