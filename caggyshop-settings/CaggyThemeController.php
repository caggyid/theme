<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Prologue\Alerts\AlertsMessageBag;

/**
 * CaggyShop: pengaturan tema dari Admin > Theme CaggyShop.
 *
 * Sumber data: public/themes/caggyshop/settings.json
 * Hasil yang dibaca browser (dibuat ulang setiap simpan):
 *   settings.css  -> variabel logo / latar / blur / dim
 *   settings.js   -> window.CaggyTheme (konfigurasi widget Discord)
 * File gambar yang diunggah disimpan di public/themes/caggyshop/uploads/.
 */
class CaggyThemeController extends Controller
{
    private const PUBLIC_DIR = '/themes/caggyshop';
    // Link gambar: https://..., http://... atau /path. Tanpa spasi, tanda kutip, kurung, backslash, < >
    private const URL_RE = '/^(https?:\/\/[^\s"\'()\\\\<>]+|\/[^\s"\'()\\\\<>]+)$/';
    private const INVITE_RE = '#^https://(discord\.gg|discord(app)?\.com/invite)/[A-Za-z0-9_-]+/?$#';

    protected $alert;

    public function __construct(AlertsMessageBag $alert)
    {
        $this->alert = $alert;
    }

    public function index(): View
    {
        $dir = $this->path();

        return view('admin.caggytheme.index', [
            'cs' => $this->load(),
            'writable' => is_dir($dir) && is_writable($dir),
            'defaultLogo' => self::PUBLIC_DIR . '/logo-glass.png',
            'defaultBg' => self::PUBLIC_DIR . '/bg.gif',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $back = redirect()->route('admin.caggytheme');

        $urlRule = function ($attribute, $value, $fail) {
            if ($value !== null && $value !== '' && !preg_match(self::URL_RE, (string) $value)) {
                $fail('Link gambar tidak valid: harus diawali https://, http:// atau /, tanpa spasi dan tanda kutip.');
            }
        };
        $inviteRule = function ($attribute, $value, $fail) {
            if ($value !== null && $value !== '' && !preg_match(self::INVITE_RE, (string) $value)) {
                $fail('Link undangan harus berbentuk https://discord.gg/xxxx atau https://discord.com/invite/xxxx.');
            }
        };

        $request->validate([
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp,gif|max:4096',
            'bg' => 'nullable|file|mimes:png,jpg,jpeg,webp,gif|max:20480',
            'logo_url' => ['nullable', 'string', 'max:500', $urlRule],
            'bg_url' => ['nullable', 'string', 'max:500', $urlRule],
            'dim' => 'nullable|numeric|between:0,1',
            'blur' => 'nullable|integer|between:0,40',
            'discord' => 'nullable|string|max:300',
            'discord_invite' => ['nullable', 'string', 'max:200', $inviteRule],
        ], [
            'logo.uploaded' => 'Logo gagal diunggah. Biasanya karena melebihi batas upload_max_filesize di php.ini.',
            'bg.uploaded' => 'Gambar latar gagal diunggah. Biasanya karena melebihi batas upload_max_filesize di php.ini.',
            'logo.mimes' => 'Logo harus berformat PNG, JPG, WEBP, atau GIF.',
            'bg.mimes' => 'Latar harus berformat PNG, JPG, WEBP, atau GIF.',
            'logo.max' => 'Ukuran logo maksimal 4 MB.',
            'bg.max' => 'Ukuran gambar latar maksimal 20 MB.',
            'dim.between' => 'Kegelapan latar harus antara 0 dan 1.',
            'blur.between' => 'Blur kaca harus antara 0 dan 40 px.',
        ]);

        $dir = $this->path();
        $uploads = $dir . '/uploads';
        if (!is_dir($dir) || !is_writable($dir)) {
            $this->alert->danger('Folder public/themes/caggyshop tidak bisa ditulis oleh web server. Jalankan install.sh lagi sebagai root.')->flash();
            return $back;
        }
        if (!is_dir($uploads)) {
            @mkdir($uploads, 0755, true);
        }
        if (!is_dir($uploads) || !is_writable($uploads)) {
            $this->alert->danger('Folder uploads tidak bisa dibuat/ditulis. Jalankan install.sh lagi sebagai root.')->flash();
            return $back;
        }

        $cs = $this->load();
        $trash = [];

        // Logo dan latar: reset > file unggahan > link
        foreach (['logo', 'bg'] as $key) {
            $current = $cs[$key];

            if ($request->boolean('reset_' . $key)) {
                $trash[] = $current;
                $cs[$key] = null;
                continue;
            }

            $file = $request->file($key);
            if ($file instanceof UploadedFile && $file->isValid()) {
                $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                if (!in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
                    return $back->withInput()->withErrors([$key => 'Format gambar tidak dikenali.']);
                }
                $name = $key . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                try {
                    $file->move($uploads, $name);
                } catch (\Throwable $e) {
                    $this->alert->danger('Gagal menyimpan file: ' . $e->getMessage())->flash();
                    return $back;
                }
                $trash[] = $current;
                $cs[$key] = self::PUBLIC_DIR . '/uploads/' . $name;
                continue;
            }

            $url = trim((string) $request->input($key . '_url', ''));
            if ($url !== '' && $url !== $current) {
                $trash[] = $current;
                $cs[$key] = $url;
            }
        }

        // Tampilan kaca
        $cs['dim'] = $request->filled('dim') ? round((float) $request->input('dim'), 2) : null;
        $cs['blur'] = $request->filled('blur') ? (int) $request->input('blur') : null;

        // Discord: kolom widget menerima Server ID atau link yang memuat ID
        $raw = trim((string) $request->input('discord', ''));
        $invite = trim((string) $request->input('discord_invite', ''));
        $id = '';
        $warn = null;
        if ($raw !== '') {
            if (preg_match('/(?<!\d)(\d{15,20})(?!\d)/', $raw, $m)) {
                $id = $m[1];
            } elseif (preg_match(self::INVITE_RE, $raw)) {
                // Link undangan ditempel di kolom widget: simpan sebagai undangan
                if ($invite === '') {
                    $invite = $raw;
                }
                $warn = 'Link undangan tidak memuat Server ID, jadi widget penuh tidak bisa ditampilkan: tombol Discord hanya membuka link undangan. Isi Server ID untuk widget lengkap.';
            } else {
                return $back->withInput()->withErrors(['discord' => 'Server ID tidak ditemukan. Isi dengan angka Server ID (15-20 digit) atau link widget yang memuat ID tersebut.']);
            }
        }
        $cs['discord_enabled'] = $request->boolean('discord_enabled');
        $cs['discord_id'] = $id;
        $cs['discord_invite'] = $invite;

        try {
            $this->publish($cs);
        } catch (\Throwable $e) {
            $this->alert->danger('Gagal menyimpan pengaturan: ' . $e->getMessage())->flash();
            return $back;
        }

        // Hapus file unggahan lama yang sudah tidak dipakai
        foreach ($trash as $old) {
            $this->forget($old);
        }

        $this->alert->success('Pengaturan tema disimpan. Muat ulang panel dengan Ctrl+Shift+R untuk melihat perubahan.')->flash();
        if ($warn) {
            $this->alert->warning($warn)->flash();
        }

        return $back;
    }

    private function path(string $file = ''): string
    {
        return public_path(ltrim(self::PUBLIC_DIR, '/')) . ($file !== '' ? '/' . $file : '');
    }

    private function load(): array
    {
        $defaults = [
            'logo' => null,
            'bg' => null,
            'dim' => null,
            'blur' => null,
            'discord_enabled' => false,
            'discord_id' => '',
            'discord_invite' => '',
        ];

        $file = $this->path('settings.json');
        if (is_file($file)) {
            $json = json_decode((string) file_get_contents($file), true);
            if (is_array($json)) {
                $defaults = array_merge($defaults, array_intersect_key($json, $defaults));
            }
        }

        return $defaults;
    }

    private function write(string $file, string $content): void
    {
        if (file_put_contents($this->path($file), $content, LOCK_EX) === false) {
            throw new \RuntimeException('tidak bisa menulis ' . $file);
        }
    }

    private function publish(array $cs): void
    {
        $note = '/* Dibuat otomatis oleh Admin > Theme CaggyShop. Jangan diedit manual. */';

        $vars = '';
        if ($cs['logo']) {
            $vars .= '--cs-logo-image:url("' . $cs['logo'] . '");';
        }
        if ($cs['bg']) {
            $vars .= '--cs-bg-image:url("' . $cs['bg'] . '");';
        }
        if ($cs['dim'] !== null) {
            $vars .= '--cs-dim:' . rtrim(rtrim(number_format((float) $cs['dim'], 2, '.', ''), '0'), '.') . ';';
        }
        if ($cs['blur'] !== null) {
            $vars .= '--cs-blur:' . (int) $cs['blur'] . 'px;';
        }

        $css = $note . "\n:root{" . $vars . "}\n";
        if ($cs['blur'] !== null) {
            // Di layar kecil blur dibatasi 10px supaya hemat baterai (sama seperti bawaan tema)
            $css .= '@media (max-width:640px){:root{--cs-blur:' . min((int) $cs['blur'], 10) . "px}}\n";
        }

        $config = [
            'discord' => [
                'enabled' => (bool) $cs['discord_enabled'],
                'id' => (string) $cs['discord_id'],
                'invite' => (string) $cs['discord_invite'],
            ],
        ];
        $js = $note . "\nwindow.CaggyTheme=" . json_encode(
            $config,
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ";\n";

        $this->write('settings.json', json_encode($cs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $this->write('settings.css', $css);
        $this->write('settings.js', $js);
    }

    private function forget(?string $url): void
    {
        // Hanya file unggahan buatan controller ini yang boleh dihapus
        if ($url && preg_match('#^' . preg_quote(self::PUBLIC_DIR, '#') . '/uploads/((logo|bg)-[A-Za-z0-9_-]+\.(png|jpg|webp|gif))$#', $url, $m)) {
            @unlink($this->path('uploads/' . $m[1]));
        }
    }
}
