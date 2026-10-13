# CaggyShop - Tema Pterodactyl

![CaggyShop](logo-16x9-small.png)

Tema cyan dark mode untuk panel Pterodactyl. Ringan, hanya CSS, dan bisa dicopot kapan saja.

## Isi

| File | Fungsi |
| --- | --- |
| `caggyshop.css` | Gaya tema: warna, input, scrollbar, console, fokus, logo login |
| `install.sh` | Memasang tema dan membuat backup otomatis |
| `uninstall.sh` | Mencopot tema |
| `logo-16x9.png` | Logo 1920x1080 |
| `logo-16x9-small.png` | Logo 960x540, dipakai di halaman login |

## Pasang

Jalankan sebagai root di server panel:

```bash
git clone https://github.com/USERNAME/caggyshop-theme.git
cd caggyshop-theme
bash install.sh
```

Kalau panel tidak berada di `/var/www/pterodactyl`:

```bash
bash install.sh /path/ke/panel
```

Setelah selesai, hard refresh browser (`Ctrl+Shift+R`).

## Copot

```bash
bash uninstall.sh
```

File asli tersimpan di `resources/views/templates/wrapper.blade.php.caggyshop.bak`.

## Cara kerja

- Menyalin CSS dan logo ke `public/themes/caggyshop/`.
- Menambahkan satu blok `<link>` sebelum `</head>` di `wrapper.blade.php`.
- Menjalankan `php artisan view:clear`.

Tidak ada JavaScript, tidak ada rebuild frontend, dan database tidak disentuh. Layout panel tidak diubah, hanya warna, border, radius, fokus, dan scrollbar.

## Kustomisasi

Warna ada di blok `:root` paling atas `caggyshop.css`:

```css
--cs-bg: #070d12;
--cs-surface: #0d171e;
--cs-raised: #13222c;
--cs-line: #1e3441;
--cs-text: #dbe8ee;
--cs-muted: #7c97a5;
--cs-cyan: #22d3ee;
--cs-cyan-deep: #0891b2;
```

Setelah mengubah file, jalankan `bash install.sh` lagi supaya salinan di `public/themes/` diperbarui.

## Catatan

- Setelah update panel (`php artisan p:upgrade`), jalankan `install.sh` lagi karena `wrapper.blade.php` ditimpa.
- Kartu dan sidebar bawaan panel memakai class yang di-generate otomatis, jadi permukaannya tetap abu gelap bawaan dan tidak dipaksa ganti. Ini disengaja supaya tema tidak rusak saat panel diperbarui.
- Logo di halaman login memakai `:has()`, didukung browser modern (Chrome 105+, Safari 15.4+, Firefox 121+).
- Ditujukan untuk Pterodactyl 1.x. Installer sudah dites pada struktur folder tiruan, tampilan di panel asli belum diuji di semua versi.

## Kontribusi dan masalah

Kalau ada bagian yang tampil aneh, buka Issue dan sertakan screenshot beserta versi panel.
