#!/usr/bin/env php
<?php
/**
 * CaggyShop - Auto suspend server berdasarkan masa expired (Pterodactyl 1.x)
 *
 * Perintah:
 *   php expiry.php install                       buat tabel server_expirations (aman diulang)
 *   php expiry.php set <server> <tanggal|+Nd>    atur expired, mis. "2026-12-01 23:59" atau +30d (hari), +4w, +1m
 *   php expiry.php extend <server> <hari>        perpanjang N hari (dihitung dari expired lama jika belum lewat, kalau sudah lewat dari sekarang)
 *   php expiry.php clear <server>                hapus masa expired (server tidak akan di-suspend otomatis)
 *   php expiry.php list                          daftar semua server beserta expired-nya
 *   php expiry.php run                           suspend server yang sudah lewat expired (dipanggil cron)
 *
 * <server> bisa berupa ID angka, UUID, UUID pendek (8 karakter), atau nama server (harus unik).
 * Server yang di-suspend manual oleh admin TIDAK ikut di-unsuspend saat diperpanjang;
 * hanya server yang di-suspend oleh skrip ini.
 */
if (PHP_SAPI !== 'cli') { exit(1); }

$panel = getenv('PANEL_PATH') ?: dirname(__DIR__);
if (!is_file($panel . '/vendor/autoload.php')) { fwrite(STDERR, "Panel tidak ditemukan di $panel (set PANEL_PATH=...)\n"); exit(1); }

require $panel . '/vendor/autoload.php';
$app = require $panel . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Namespace aplikasi: "Pterodactyl" (standar 1.x), cadangan "App"
$ns = null;
foreach (['Pterodactyl', 'App'] as $try) {
    if (class_exists($try . '\\Models\\Server')) { $ns = $try; break; }
}
if ($ns === null) { fwrite(STDERR, "Model Server tidak ditemukan (namespace panel tidak dikenali).\n"); exit(1); }
class_alias($ns . '\\Models\\Server', 'CaggyServer');
class_alias($ns . '\\Services\\Servers\\SuspensionService', 'CaggySuspension');

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const TBL = 'server_expirations';

function out(string $m): void { echo '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $m . PHP_EOL; }
function fail(string $m): void { fwrite(STDERR, $m . PHP_EOL); exit(1); }
function nowUtc(): Carbon { return Carbon::now('UTC'); }

function isSusp($s): bool {
    return method_exists($s, 'isSuspended') ? $s->isSuspended() : (bool) ($s->suspended ?? false);
}

function findServer(string $q): \CaggyServer {
    $list = \CaggyServer::query()
        ->when(ctype_digit($q), fn ($w) => $w->where('id', (int) $q))
        ->when(!ctype_digit($q), fn ($w) => $w->where(fn ($x) => $x->where('uuid', $q)->orWhere('uuidShort', $q)->orWhere('name', $q)))
        ->get();
    if ($list->count() === 0) { fail("Server '$q' tidak ditemukan."); }
    if ($list->count() > 1) { fail("Nama '$q' cocok dengan lebih dari satu server, pakai ID/UUID: " . $list->pluck('id')->implode(', ')); }
    return $list->first();
}

function parseWhen(string $v): Carbon {
    if (preg_match('/^\+(\d+)([dwm])$/i', $v, $m)) {
        $n = (int) $m[1];
        $t = nowUtc();
        $u = strtolower($m[2]);
        if ($u === 'd') { return $t->addDays($n); }
        if ($u === 'w') { return $t->addWeeks($n); }
        return $t->addMonthsNoOverflow($n);
    }
    try {
        // Tanggal tanpa zona waktu dibaca memakai zona waktu panel (app.timezone), disimpan sebagai UTC.
        return Carbon::parse($v, config('app.timezone', 'UTC'))->utc();
    } catch (\Throwable $e) {
        fail("Format tanggal tidak dikenali: $v");
    }
}

function suspend($s): void   { app(\CaggySuspension::class)->toggle($s, 'suspend'); }
function unsuspend($s): void { app(\CaggySuspension::class)->toggle($s, 'unsuspend'); }

function save($s, Carbon $when): void {
    $row = DB::table(TBL)->where('server_id', $s->id)->first();
    $t = $when->copy()->utc()->format('Y-m-d H:i:s');
    if ($row) { DB::table(TBL)->where('server_id', $s->id)->update(['expires_at' => $t, 'updated_at' => gmdate('Y-m-d H:i:s')]); }
    else { DB::table(TBL)->insert(['server_id' => $s->id, 'expires_at' => $t, 'suspended_by_expiry' => 0, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]); }

    // Diperpanjang melewati sekarang -> buka suspend jika sebelumnya disuspend oleh skrip ini
    if ($row && $row->suspended_by_expiry && $when->gt(nowUtc())) {
        if (isSusp($s)) { unsuspend($s); out("Server #{$s->id} ({$s->name}) di-unsuspend karena diperpanjang."); }
        DB::table(TBL)->where('server_id', $s->id)->update(['suspended_by_expiry' => 0]);
    }
    out("Server #{$s->id} ({$s->name}) expired: " . $when->copy()->utc()->format('Y-m-d H:i') . ' UTC');
}

$cmd = $argv[1] ?? '';
if ($cmd !== 'install' && !Schema::hasTable(TBL)) { fail("Tabel belum ada. Jalankan: php expiry.php install"); }

switch ($cmd) {
    case 'install':
        if (Schema::hasTable(TBL)) { out('Tabel sudah ada.'); break; }
        $make = function (bool $fk) {
            Schema::create(TBL, function ($t) use ($fk) {
                $t->increments('id');
                $t->unsignedInteger('server_id')->unique();
                $t->timestamp('expires_at')->nullable()->index();
                $t->boolean('suspended_by_expiry')->default(false);
                $t->timestamps();
                if ($fk) { $t->foreign('server_id')->references('id')->on('servers')->onDelete('cascade'); }
            });
        };
        try { $make(true); }
        catch (\Throwable $e) { Schema::dropIfExists(TBL); $make(false); out('Dibuat tanpa foreign key (' . $e->getMessage() . ')'); }
        out('Tabel ' . TBL . ' dibuat.');
        break;

    case 'set':
        if (count($argv) < 4) { fail('Pemakaian: php expiry.php set <server> <tanggal|+Nd>'); }
        save(findServer($argv[2]), parseWhen(implode(' ', array_slice($argv, 3))));
        break;

    case 'extend':
        if (count($argv) < 4 || !ctype_digit($argv[3])) { fail('Pemakaian: php expiry.php extend <server> <hari>'); }
        $s = findServer($argv[2]);
        $row = DB::table(TBL)->where('server_id', $s->id)->first();
        $base = nowUtc();
        if ($row && $row->expires_at) { $cur = Carbon::parse($row->expires_at, 'UTC'); if ($cur->gt($base)) { $base = $cur; } }
        save($s, $base->addDays((int) $argv[3]));
        break;

    case 'clear':
        if (count($argv) < 3) { fail('Pemakaian: php expiry.php clear <server>'); }
        $s = findServer($argv[2]);
        $row = DB::table(TBL)->where('server_id', $s->id)->first();
        if ($row && $row->suspended_by_expiry && isSusp($s)) { unsuspend($s); out("Server #{$s->id} di-unsuspend."); }
        DB::table(TBL)->where('server_id', $s->id)->delete();
        out("Masa expired server #{$s->id} ({$s->name}) dihapus.");
        break;

    case 'list':
        $rows = DB::table(TBL)->join('servers', 'servers.id', '=', TBL . '.server_id')
            ->orderBy(TBL . '.expires_at')->get([TBL . '.*', 'servers.name', 'servers.uuidShort']);
        if ($rows->isEmpty()) { echo "Belum ada server dengan masa expired.\n"; break; }
        printf("%-5s %-9s %-24s %-17s %s\n", 'ID', 'UUID', 'NAMA', 'EXPIRED (UTC)', 'STATUS');
        foreach ($rows as $r) {
            $left = Carbon::parse($r->expires_at, 'UTC');
            $st = $r->suspended_by_expiry ? 'disuspend (expired)' : ($left->lte(nowUtc()) ? 'lewat, menunggu cron' : 'sisa ' . nowUtc()->diffInDays($left) . ' hari');
            printf("%-5s %-9s %-24s %-17s %s\n", $r->server_id, $r->uuidShort, mb_substr($r->name, 0, 24), $left->format('Y-m-d H:i'), $st);
        }
        break;

    case 'run':
        $due = DB::table(TBL)->where('expires_at', '<=', nowUtc()->format('Y-m-d H:i:s'))->where('suspended_by_expiry', 0)->get();
        foreach ($due as $r) {
            $s = \CaggyServer::find($r->server_id);
            if (!$s) { continue; }
            try {
                if (!isSusp($s)) { suspend($s); out("Server #{$s->id} ({$s->name}) di-suspend (expired)."); }
                else { out("Server #{$s->id} ({$s->name}) sudah suspend, ditandai."); }
                DB::table(TBL)->where('server_id', $s->id)->update(['suspended_by_expiry' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {
                out("GAGAL suspend server #{$s->id}: " . $e->getMessage() . ' (dicoba lagi di jalan cron berikutnya)');
            }
        }
        break;

    default:
        echo "Perintah: install | set | extend | clear | list | run\nLihat komentar di atas file untuk detail.\n";
}
