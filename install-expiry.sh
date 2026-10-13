#!/usr/bin/env bash
# Pasang auto-suspend expired. Jalankan sebagai root: bash install-expiry.sh [/var/www/pterodactyl]
set -euo pipefail
PANEL="${1:-/var/www/pterodactyl}"
DIR="$(cd "$(dirname "$0")" && pwd)"
DEST="$PANEL/caggyshop-expiry"

[ -f "$PANEL/artisan" ] || { echo "Panel tidak ditemukan di $PANEL"; exit 1; }
OWNER="$(stat -c %U "$PANEL/artisan")"

mkdir -p "$DEST"
cp "$DIR/expiry.php" "$DEST/expiry.php"
chown -R "$OWNER": "$DEST"

runuser -u "$OWNER" -- php "$DEST/expiry.php" install

# Cron tiap 5 menit, dijalankan sebagai pemilik panel (biasanya www-data)
cat > /etc/cron.d/caggyshop-expiry <<CRON
*/5 * * * * $OWNER php $DEST/expiry.php run >> $PANEL/storage/logs/caggyshop-expiry.log 2>&1
CRON
chmod 644 /etc/cron.d/caggyshop-expiry

echo "Selesai. Contoh pemakaian:"
echo "  cd $DEST"
echo "  sudo -u $OWNER php expiry.php set <server> +30d"
echo "  sudo -u $OWNER php expiry.php list"
