<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\SuspensionService;
use Prologue\Alerts\AlertsMessageBag;

/**
 * CaggyShop: atur masa expired server dari halaman admin (Servers > Details).
 * Suspend otomatis dilakukan oleh cron (caggyshop-expiry/expiry.php run).
 */
class CaggyExpiryController extends Controller
{
    private const TBL = 'server_expirations';

    protected $alert;
    protected $suspension;

    public function __construct(AlertsMessageBag $alert, SuspensionService $suspension)
    {
        $this->alert = $alert;
        $this->suspension = $suspension;
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $back = redirect()->route('admin.servers.view.details', $server->id);

        if (!Schema::hasTable(self::TBL)) {
            $this->alert->danger('Tabel ' . self::TBL . ' belum ada. Jalankan: php caggyshop-expiry/expiry.php install')->flash();
            return $back;
        }

        $row = DB::table(self::TBL)->where('server_id', $server->id)->first();
        $stamp = gmdate('Y-m-d H:i:s');

        // Hapus masa expired
        if ($request->has('clear')) {
            if ($row && $row->suspended_by_expiry && $this->isSuspended($server)) {
                try {
                    $this->suspension->toggle($server, 'unsuspend');
                } catch (\Throwable $e) {
                    $this->alert->danger('Gagal membuka suspend: ' . $e->getMessage())->flash();
                    return $back;
                }
            }
            DB::table(self::TBL)->where('server_id', $server->id)->delete();
            $this->alert->success('Masa expired server dihapus.')->flash();
            return $back;
        }

        // Tentukan waktu expired baru
        if ($request->filled('extend_days')) {
            $days = (int) $request->input('extend_days');
            if ($days < 1 || $days > 3650) {
                $this->alert->danger('Jumlah hari tidak valid.')->flash();
                return $back;
            }
            $base = Carbon::now('UTC');
            if ($row && $row->expires_at) {
                $cur = Carbon::parse($row->expires_at, 'UTC');
                if ($cur->gt($base)) {
                    $base = $cur;
                }
            }
            $when = $base->addDays($days);
        } else {
            $request->validate(['expires_at' => 'required|date']);
            // Input dibaca dalam zona waktu panel, disimpan sebagai UTC
            $when = Carbon::parse($request->input('expires_at'), config('app.timezone', 'UTC'))->utc();
        }

        $value = $when->copy()->utc()->format('Y-m-d H:i:s');
        if ($row) {
            DB::table(self::TBL)->where('server_id', $server->id)->update(['expires_at' => $value, 'updated_at' => $stamp]);
        } else {
            DB::table(self::TBL)->insert([
                'server_id' => $server->id, 'expires_at' => $value, 'suspended_by_expiry' => 0,
                'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
        }

        // Diperpanjang melewati sekarang: buka suspend hanya jika sebelumnya disuspend oleh sistem expired
        if ($row && $row->suspended_by_expiry && $when->gt(Carbon::now('UTC'))) {
            try {
                if ($this->isSuspended($server)) {
                    $this->suspension->toggle($server, 'unsuspend');
                }
                DB::table(self::TBL)->where('server_id', $server->id)->update(['suspended_by_expiry' => 0]);
            } catch (\Throwable $e) {
                $this->alert->warning('Expired disimpan, tetapi gagal membuka suspend: ' . $e->getMessage())->flash();
                return $back;
            }
        }

        $this->alert->success('Expired disimpan: ' . $when->copy()->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i') . ' (' . config('app.timezone', 'UTC') . ')')->flash();
        return $back;
    }

    private function isSuspended(Server $server): bool
    {
        return method_exists($server, 'isSuspended') ? $server->isSuspended() : (bool) ($server->suspended ?? false);
    }
}
