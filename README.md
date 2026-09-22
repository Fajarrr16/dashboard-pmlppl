# DashboardPMLPPL

Dashboard monitoring Kartu Kendali untuk data pendataan PPL/PML dari 7 Google Spreadsheet.

```text
Google Form
  -> Google Spreadsheet
  -> Google Apps Script
  -> Laravel REST API
  -> SQLite
  -> Dashboard Blade + Fetch API
```

## Stack

- Laravel 13
- PHP 8.3 atau lebih baru
- SQLite
- Blade
- JavaScript biasa dan Fetch API
- Vite untuk asset build project
- Google Apps Script untuk integrasi spreadsheet

Tidak ada React, Vue, Inertia, atau package Excel tambahan.

## Persyaratan

- PHP 8.3+
- Composer
- Node.js dan npm
- SQLite extension PHP aktif
- ngrok hanya diperlukan jika Google Apps Script mengakses Laravel dari komputer lokal

## Instalasi

Dari folder project:

```bash
composer install
npm install
```

Buat file environment:

```bash
copy .env.example .env
php artisan key:generate
```

Pada Linux/macOS, gunakan `cp .env.example .env`.

Atur minimal `.env`:

```env
APP_NAME="Dashboard PML/PPL"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

KARTU_KENDALI_API_TOKEN=ganti-dengan-token-acak-yang-panjang
```

Buat file database jika belum ada:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Jangan commit `.env` atau token ke repository.

## Database

Jalankan migration:

```bash
php artisan migrate
```

Untuk data dummy lokal/staging:

```bash
php artisan db:seed
```

Seeder membuat data fiktif dari 7 wilayah. Jangan menjalankan seeder dummy pada production yang sudah berisi data nyata.

Cek status migration:

```bash
php artisan migrate:status
```

## Menjalankan aplikasi

Terminal pertama:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Buka:

```text
http://127.0.0.1:8000
```

Alias dashboard juga tersedia di `/dashboard`.

Untuk mode development Laravel dan Vite sesuai script project:

```bash
composer run dev
```

Build asset production:

```bash
npm run build
php artisan optimize
```

## Dashboard

Dashboard menyediakan:

- Total responden
- Total PPL unik
- Total PML unik
- Total SLS unik
- Statistik 7 wilayah
- Statistik jenis responden
- Data terbaru
- Pencarian
- Filter wilayah, tanggal, kelurahan, SLS, PPL, PML, dan jenis responden
- Pagination
- Link dokumentasi Google Drive
- Indikator `LIVE`
- Waktu update terakhir
- Polling Fetch API setiap 5 detik
- Status `Connection lost` saat API gagal

Dashboard memakai endpoint GET API. Endpoint GET saat ini tidak memakai token agar dapat dipanggil browser; jika datanya internal, batasi dashboard/API melalui autentikasi atau jaringan privat sebelum dipublikasikan.

## API

Ringkasan endpoint:

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/kartu-kendali` | Menerima data dari Apps Script; wajib Bearer token |
| GET | `/api/kartu-kendali` | Daftar data, search, filter, pagination |
| GET | `/api/kartu-kendali/statistics` | Statistik dashboard |
| GET | `/api/kartu-kendali/{id}` | Detail satu record |

Dokumentasi request, response, validasi, duplicate, dan query parameter ada di [API_DOCUMENTATION.md](API_DOCUMENTATION.md).

## Token API

Middleware POST membaca:

```env
KARTU_KENDALI_API_TOKEN=...
```

Apps Script wajib mengirim:

```http
Authorization: Bearer TOKEN
Content-Type: application/json
```

Token tidak di-hard-code di Laravel. Token yang berbeda atau kosong menghasilkan HTTP `401`. POST dibatasi 60 request per menit.

## Import CSV

Command import CSV:

```bash
php artisan kartu-kendali:import storage/app/kartu-kendali.csv --sheet=SETU
```

Header CSV mengikuti nama Google Sheet dan dinormalisasi terhadap kapitalisasi/spasi, misalnya `Nama PPL`, `Nama PML`, dan `Tanggal Pendataan`.

Tanpa `--sheet`, command memakai kolom `source_sheet`, atau memakai nilai `wilayah` sebagai fallback. Jika `external_id` kosong, command membuat identifier SHA-256. Baris duplicate dihitung sebagai gagal/duplicate dan tidak disimpan.

Excel belum didukung agar project tidak menambah package yang belum diperlukan. Export Excel sebagai CSV terlebih dahulu.

## Google Apps Script

Dokumentasi script reusable, mapping header dinamis, trigger, Script Properties, duplicate prevention, dan fallback time trigger ada di [GOOGLE_APPS_SCRIPT.md](GOOGLE_APPS_SCRIPT.md).

Pasang script yang sama pada 7 spreadsheet berikut:

1. SETU
2. SERPONG
3. PAMULANG
4. CIPUTAT
5. CIPUTAT TIMUR
6. PONDOK AREN
7. SERPONG UTARA

Setiap spreadsheet memiliki `KARTU_WILAYAH` sesuai wilayahnya. Script membaca header secara dinamis dan tidak mengubah spreadsheet.

Trigger utama:

```text
From spreadsheet -> On form submit
```

Fallback:

```text
installTimeTrigger() -> pemeriksaan setiap 5 menit
```

## Koneksi Google ke localhost

Google Apps Script tidak dapat mengakses `localhost` atau `127.0.0.1`. Jalankan tunnel:

```bash
ngrok http 8000
```

Masukkan URL HTTPS ngrok ke Script Property `KARTU_API_URL`, lengkap dengan path:

```text
https://subdomain.ngrok-free.app/api/kartu-kendali
```

Detail tunnel dan troubleshooting ada di [GOOGLE_CONNECTION.md](GOOGLE_CONNECTION.md).

## Testing

Jalankan seluruh test:

```bash
php artisan test
```

Cakupan feature test API meliputi:

- payload valid dan normalisasi header
- token salah
- payload invalid
- duplicate
- daftar data
- filter wilayah
- statistik agregat

Verifikasi dependensi PHP:

```bash
composer validate --no-check-publish
composer audit
```

## Deployment checklist

Sebelum production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-resmi
KARTU_KENDALI_API_TOKEN=token-acak-baru
```

Lalu:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

Gunakan document root web server ke folder `public/`. Pastikan web server tidak mengekspos `.env`, `storage`, `vendor`, atau database SQLite secara langsung.

Jika fitur file storage digunakan:

```bash
php artisan storage:link
```

## Troubleshooting

### Dashboard menampilkan `Connection lost`

1. Pastikan Laravel server masih berjalan.
2. Buka `/api/kartu-kendali/statistics` di browser.
3. Periksa `storage/logs/laravel.log`.
4. Pastikan migration sudah dijalankan.

### POST mengembalikan `401`

Pastikan token Script Properties sama persis dengan `KARTU_KENDALI_API_TOKEN` dan header menggunakan format `Bearer TOKEN`.

### POST mengembalikan `422`

Periksa field wajib:

- `wilayah`
- `nama_ppl`
- `nama_pml`
- `nama_responden`
- `source_sheet`

`wilayah` harus salah satu dari 7 wilayah yang didukung.

### Data masuk dua kali

API memakai kombinasi `source_sheet + external_id` sebagai unique key. Pastikan Apps Script mengirim `external_id` stabil. Script pada dokumentasi sudah membuat identifier deterministik dari isi row.

### Google tidak dapat mengakses API

Jangan memakai `localhost` atau `127.0.0.1`. Gunakan URL HTTPS ngrok atau deploy aplikasi ke server publik.
