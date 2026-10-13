@php
    $csTz = config('app.timezone', 'UTC');
    $csRow = \Illuminate\Support\Facades\Schema::hasTable('server_expirations')
        ? \Illuminate\Support\Facades\DB::table('server_expirations')->where('server_id', $server->id)->first()
        : null;
    $csAt = ($csRow && $csRow->expires_at) ? \Illuminate\Support\Carbon::parse($csRow->expires_at, 'UTC')->setTimezone($csTz) : null;
    $csLeft = $csAt ? \Illuminate\Support\Carbon::now('UTC')->diffInDays($csAt, false) : null;
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Masa Aktif / Expired</h3>
            </div>
            @if(!\Illuminate\Support\Facades\Schema::hasTable('server_expirations'))
                <div class="box-body">
                    <p class="text-danger" style="margin:0;">Tabel expired belum dibuat. Jalankan <code>bash install-expiry.sh</code> di server panel.</p>
                </div>
            @else
            <form action="{{ route('admin.servers.view.expiry', $server->id) }}" method="POST">
                <div class="box-body">
                    <div class="form-group">
                        <label for="cs_expires_at" class="control-label">Expired pada</label>
                        <input type="datetime-local" id="cs_expires_at" name="expires_at" class="form-control" style="max-width:280px;" value="{{ $csAt ? $csAt->format('Y-m-d\TH:i') : '' }}">
                        <p class="text-muted small" style="margin-top:6px;">
                            Zona waktu: {{ $csTz }}. Setelah lewat, server otomatis di-suspend (dicek tiap 5 menit) dan dibuka lagi saat expired diperpanjang.
                        </p>
                    </div>
                    <p style="margin:0;">
                        <strong>Status:</strong>
                        @if(!$csAt)
                            <span class="text-muted">Tidak ada masa expired (tidak akan di-suspend otomatis).</span>
                        @elseif($csRow->suspended_by_expiry)
                            <span class="label label-danger">Disuspend karena expired</span>
                            sejak jadwal {{ $csAt->format('Y-m-d H:i') }}
                        @elseif($csLeft < 0 || $csAt->lte(\Illuminate\Support\Carbon::now($csTz)))
                            <span class="label label-warning">Sudah lewat, menunggu cron</span>
                        @else
                            <span class="label label-success">Aktif</span>
                            sisa {{ $csLeft }} hari (sampai {{ $csAt->format('Y-m-d H:i') }})
                        @endif
                    </p>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Expired</button>
                    <span style="margin-left:10px;">Perpanjang:</span>
                    <button type="submit" name="extend_days" value="7" class="btn btn-default btn-sm">+7 hari</button>
                    <button type="submit" name="extend_days" value="30" class="btn btn-default btn-sm">+30 hari</button>
                    <button type="submit" name="extend_days" value="90" class="btn btn-default btn-sm">+90 hari</button>
                    @if($csAt)
                        <button type="submit" name="clear" value="1" class="btn btn-danger btn-sm pull-right" onclick="return confirm('Hapus masa expired server ini?');">Hapus Expired</button>
                    @endif
                </div>
            </form>
            @endif
        </div>
    </div>
</div>
