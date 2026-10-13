# CaggyShop - Tema Pterodactyl (Glass)

![CaggyShop](logo-16x9-small.png)

Tema cyan dengan efek **glassmorphism** dan **latar GIF anime** untuk panel Pterodactyl. Bisa dicopot kapan saja.

## Isi

| File | Fungsi |
| --- | --- |
| `caggyshop.css` | Gaya tema: kaca (blur), latar GIF, input, scrollbar, console, logo login |
| `glass.js` | Opsional. Menandai kartu/navbar panel supaya ikut berkaca (lihat "Cara kerja") |
| `bg.gif` | Latar bawaan (sakura malam, karya orisinal). **Ganti dengan GIF anime pilihan Anda** |
| `logo-glass.png` | Logo dengan latar transparan, dipakai di halaman login |
| `install.sh` / `uninstall.sh` | Pasang / copot tema |
| `logo-16x9*.png` | Logo asli (cadangan) |

## Pasang

Jalankan sebagai root di server panel:

```bash
cd caggyshop-theme
bash install.sh                  # panel di /var/www/pterodactyl
bash install.sh /path/ke/panel   # lokasi lain
```

Lalu hard refresh browser (`Ctrl+Shift+R`). Aman dijalankan ulang untuk update dari versi lama.

## Ganti latar GIF

1. Siapkan GIF anime (disarankan 1280x720 atau lebih, di bawah ~5 MB).
2. Simpan sebagai `/var/www/pterodactyl/public/themes/caggyshop/bg.gif` (timpa file yang ada).
3. Hard refresh.

`install.sh` **tidak menimpa** `bg.gif` yang sudah Anda ganti. Pakai `FORCE_BG=1 bash install.sh` kalau ingin kembali ke GIF bawaan.

Alternatif: ubah `--cs-bg-image` di `caggyshop.css`, misalnya `url("/themes/caggyshop/nama-lain.gif")`. Untuk GIF dari internet, pakai link langsung ke file `.gif` (halaman Tenor/Giphy tidak bisa dipakai), tapi lebih aman mengunduh dan menyimpannya di server sendiri karena link luar bisa mati atau diblokir.

Pastikan Anda berhak memakai GIF tersebut, terutama jika panel dipakai untuk usaha.

## Kustomisasi

Semua di blok `:root` paling atas `caggyshop.css`:

| Variabel | Fungsi |
| --- | --- |
| `--cs-dim` | Kegelapan lapisan di atas GIF (`0`-`1`). Naikkan kalau teks sulit dibaca di GIF terang |
| `--cs-blur` | Kekuatan blur kaca (default `18px`) |
| `--cs-glass` | Warna/transparansi kartu. Turunkan alpha untuk lebih bening |
| `--cs-radius` | Kelengkungan sudut kartu |
| `--cs-cyan` | Warna aksen |

Setelah mengubah file di repo, jalankan `bash install.sh` lagi. Kalau mengubah langsung di `public/themes/caggyshop/`, cukup hard refresh.

## Cara kerja

- Menyalin file ke `public/themes/caggyshop/` dan menambahkan satu blok `<link>` + `<script defer>` sebelum `</head>` di `wrapper.blade.php` (backup: `wrapper.blade.php.caggyshop.bak`).
- Kelas CSS panel di-generate otomatis (hash), jadi tidak bisa dipilih langsung. `glass.js` hanya **membaca** warna latar elemen; kalau elemen berupa kartu gelap yang cukup besar, ia diberi atribut `data-cs-glass`, dan CSS yang membuatnya berkaca. Tidak ada data yang dikirim keluar dan tidak ada rebuild frontend.
- Tanpa `glass.js` (hapus tag `<script>` di `wrapper.blade.php`), latar GIF, kartu login, dan input tetap berkaca; kartu dashboard kembali ke tampilan bawaan.
- Panel tidak diubah: tidak ada perubahan layout, database, atau file inti selain `wrapper.blade.php`.

## Performa

GIF animasi + blur dihitung ulang tiap frame, jadi di perangkat lemah bisa terasa berat. Kalau begitu:

- pakai GIF lebih kecil/pendek, atau turunkan `--cs-blur` (mobile sudah otomatis `10px`),
- atau ganti `bg.gif` dengan gambar diam (`.jpg`/`.webp`) dan ubah `--cs-bg-image`.

Pengguna dengan pengaturan "kurangi animasi" di OS otomatis mendapat latar polos.

## Copot

```bash
bash uninstall.sh
```

## Catatan

- Setelah `php artisan p:upgrade`, jalankan `install.sh` lagi karena `wrapper.blade.php` ditimpa.
- Memakai `:has()` dan `backdrop-filter`: Chrome 105+, Safari 15.4+, Firefox 121+. Browser lama jatuh ke tampilan lebih pekat.
- Ditujukan untuk Pterodactyl 1.x. Dites pada struktur folder tiruan dan halaman tiruan di Chromium; tampilan di panel asli belum diuji di semua versi, jadi kartu tertentu bisa saja tidak terdeteksi atau terlalu pekat.
