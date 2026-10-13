# Koneksi Google Apps Script ke Laravel

Google Apps Script tidak dapat mengakses `localhost` atau `127.0.0.1` di komputer developer. Alamat tersebut menunjuk ke mesin Google, bukan ke komputer Windows tempat Laravel berjalan.

## Jalankan Laravel

Dari folder project:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Pastikan dashboard dapat dibuka di:

```text
http://127.0.0.1:8000
```

## Gunakan ngrok

Install ngrok dari situs resmi ngrok, login sesuai instruksinya, lalu jalankan pada terminal lain:

```bash
ngrok http 8000
```

ngrok akan menampilkan URL publik, misalnya:

```text
Forwarding https://contoh-subdomain.ngrok-free.app -> http://localhost:8000
```

Endpoint yang dimasukkan ke Script Properties Google Apps Script adalah:

```text
https://contoh-subdomain.ngrok-free.app/api/kartu-kendali
```

Jangan menambahkan slash ganda dan jangan menggunakan URL `localhost` di Apps Script.

## Hal yang perlu diperhatikan

- URL gratis ngrok dapat berubah ketika tunnel dimulai ulang.
- Jika URL berubah, ubah `KARTU_API_URL` di Script Properties untuk semua 7 spreadsheet.
- Laravel dan ngrok harus tetap berjalan selama Google Apps Script mengirim data.
- Jangan membagikan URL tunnel dan API token secara publik.
- Untuk penggunaan stabil, gunakan domain/tunnel tetap atau deploy Laravel ke server HTTPS.
- Pastikan port yang dipakai ngrok sama dengan port Laravel.

## Uji endpoint melalui tunnel

Dari terminal lokal:

```bash
curl -i https://contoh-subdomain.ngrok-free.app/api/kartu-kendali/statistics
```

Untuk menguji POST, gunakan token yang sama dengan `.env` Laravel:

```bash
curl -i -X POST "https://contoh-subdomain.ngrok-free.app/api/kartu-kendali" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "kecamatan": "SETU",
    "nama_ppl": "PPL Uji",
    "nama_pml": "PML Uji",
    "nama_responden": "Responden Uji",
    "source_sheet": "SETU",
    "external_id": "uji-ngrok-1"
  }'
```

## Alur saat tunnel aktif

```text
Google Form
  -> Google Spreadsheet
  -> Google Apps Script
  -> URL ngrok HTTPS
  -> Laravel /api/kartu-kendali
  -> SQLite
  -> Dashboard polling setiap 5 detik
```

Jika Apps Script gagal, periksa Logger Apps Script, terminal ngrok, dan `storage/logs/laravel.log`.
