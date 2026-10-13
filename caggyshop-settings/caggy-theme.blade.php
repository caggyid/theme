@extends('layouts.admin')

@section('title')
    Theme CaggyShop
@endsection

@section('content-header')
    <h1>Theme CaggyShop<small>Logo, latar, tampilan kaca, dan widget Discord.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Theme CaggyShop</li>
    </ol>
@endsection

@section('content')
@php
    $csUploadsPrefix = '/themes/caggyshop/uploads/';
    $csLogoIsUpload = $cs['logo'] && \Illuminate\Support\Str::startsWith($cs['logo'], $csUploadsPrefix);
    $csBgIsUpload = $cs['bg'] && \Illuminate\Support\Str::startsWith($cs['bg'], $csUploadsPrefix);
    $csLogoUrl = ($cs['logo'] && !$csLogoIsUpload) ? $cs['logo'] : '';
    $csBgUrl = ($cs['bg'] && !$csBgIsUpload) ? $cs['bg'] : '';
@endphp
@if(!$writable)
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-danger">
                Folder <code>public/themes/caggyshop</code> tidak bisa ditulis oleh web server, jadi pengaturan tidak akan tersimpan.
                Jalankan <code>bash install.sh</code> lagi sebagai root.
            </div>
        </div>
    </div>
@endif
<form action="{{ route('admin.caggytheme.update') }}" method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Logo</h3>
                </div>
                <div class="box-body">
                    <p>
                        <img src="{{ $cs['logo'] ?: $defaultLogo }}" alt="Logo saat ini" style="max-width:100%;max-height:90px;padding:10px;border-radius:6px;background:#1f2d3a;">
                    </p>
                    <div class="form-group">
                        <label for="cs_logo" class="control-label">Unggah logo baru</label>
                        <input type="file" id="cs_logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
                        <p class="text-muted small">PNG transparan paling bagus (rasio 16:9). Maksimal 4 MB. Dipakai di navbar dan halaman login.</p>
                    </div>
                    <div class="form-group">
                        <label for="cs_logo_url" class="control-label">atau link gambar</label>
                        <input type="text" id="cs_logo_url" name="logo_url" class="form-control" placeholder="https://contoh.com/logo.png" value="{{ old('logo_url', $csLogoUrl) }}">
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="reset_logo" value="1"> Kembalikan ke logo bawaan</label>
                    </div>
                </div>
            </div>

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Latar belakang</h3>
                </div>
                <div class="box-body">
                    <p>
                        <img src="{{ $cs['bg'] ?: $defaultBg }}" alt="Latar saat ini" style="max-width:100%;max-height:130px;border-radius:6px;">
                    </p>
                    <div class="form-group">
                        <label for="cs_bg" class="control-label">Unggah gambar / GIF baru</label>
                        <input type="file" id="cs_bg" name="bg" accept="image/png,image/jpeg,image/webp,image/gif">
                        <p class="text-muted small">
                            PNG, JPG, WEBP, atau GIF, maksimal 20 MB (disarankan 1280x720 ke atas, di bawah ~5 MB supaya ringan).
                            Batas upload server juga mengikuti <code>upload_max_filesize</code> di php.ini.
                        </p>
                    </div>
                    <div class="form-group">
                        <label for="cs_bg_url" class="control-label">atau link gambar</label>
                        <input type="text" id="cs_bg_url" name="bg_url" class="form-control" placeholder="https://contoh.com/latar.gif" value="{{ old('bg_url', $csBgUrl) }}">
                        <p class="text-muted small">Harus link langsung ke file gambar (bukan halaman Tenor/Giphy). Mengunggah ke server sendiri lebih aman karena link luar bisa mati.</p>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="reset_bg" value="1"> Kembalikan ke latar bawaan</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Tampilan kaca</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="cs_dim" class="control-label">Kegelapan latar (0 - 1)</label>
                            <input type="number" id="cs_dim" name="dim" class="form-control" min="0" max="1" step="0.01" placeholder="0.44" value="{{ old('dim', $cs['dim']) }}">
                            <p class="text-muted small">Naikkan jika teks sulit dibaca di atas latar yang terang. Kosong = bawaan (0.44).</p>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="cs_blur" class="control-label">Blur kaca (px)</label>
                            <input type="number" id="cs_blur" name="blur" class="form-control" min="0" max="40" step="1" placeholder="18" value="{{ old('blur', $cs['blur']) }}">
                            <p class="text-muted small">Makin besar makin buram, tapi makin berat di perangkat lemah. Kosong = bawaan (18).</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Widget Discord</h3>
                </div>
                <div class="box-body">
                    <div class="checkbox" style="margin-top:0;">
                        <label><input type="checkbox" name="discord_enabled" value="1" @if(old('discord_enabled', $cs['discord_enabled'])) checked @endif> <strong>Tampilkan widget Discord</strong> di panel pengguna</label>
                    </div>
                    <div class="form-group">
                        <label for="cs_discord" class="control-label">Server ID atau link widget</label>
                        <input type="text" id="cs_discord" name="discord" class="form-control" placeholder="123456789012345678 atau https://discord.com/widget?id=123456789012345678" value="{{ old('discord', $cs['discord_id']) }}">
                        <p class="text-muted small">
                            Cukup tempel Server ID atau link widget; ID diambil otomatis.
                            Di Discord: Pengaturan Server &rsaquo; <strong>Widget</strong> &rsaquo; aktifkan <em>Aktifkan widget server</em>
                            (Server ID ada di sana, atau klik kanan server dengan Developer Mode aktif).
                        </p>
                    </div>
                    <div class="form-group">
                        <label for="cs_discord_invite" class="control-label">Link undangan (opsional)</label>
                        <input type="text" id="cs_discord_invite" name="discord_invite" class="form-control" placeholder="https://discord.gg/xxxxxxx" value="{{ old('discord_invite', $cs['discord_invite']) }}">
                        <p class="text-muted small">Dipakai untuk tombol &ldquo;Gabung server&rdquo;. Tanpa Server ID, tombol Discord langsung membuka link ini.</p>
                    </div>
                    <p class="text-muted small" style="margin-bottom:0;">
                        Saat widget aktif, browser pengguna memuat widget dan jumlah anggota online dari discord.com (iframe baru dimuat saat panel widget dibuka).
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Simpan Pengaturan</button>
                    <span class="text-muted small">Setelah menyimpan, muat ulang panel dengan Ctrl+Shift+R.</span>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
