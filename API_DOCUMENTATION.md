# API Kartu Kendali

Base URL lokal:

```text
http://127.0.0.1:8000/api
```

Base URL production/tunnel mengikuti host yang digunakan. Endpoint POST menerima data dari Google Apps Script dengan token Bearer. Endpoint GET dipakai dashboard dan saat ini tidak memerlukan token; batasi akses melalui jaringan atau autentikasi tambahan jika data responden bersifat internal.

## Autentikasi POST

Laravel membaca token dari `.env`:

```env
KARTU_KENDALI_API_TOKEN=isi_dengan_token_acak
```

Request wajib memiliki header:

```http
Authorization: Bearer isi_dengan_token_acak
Content-Type: application/json
```

Token tidak disimpan di source code. Jangan mengirim token melalui query string.

## POST `/api/kartu-kendali`

Contoh request:

```bash
curl -X POST "http://127.0.0.1:8000/api/kartu-kendali" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "kecamatan": "SETU",
    "timestamp_google": "09/22/2026 08:30:00",
    "tanggal_pendataan": "2026-09-22",
    "waktu_pendataan": "2026-09-22 08:30:00",
    "nama_pml": "Nama PML",
    "kelurahan": "Setu",
    "sls": "001",
    "nama_responden": "Nama Responden",
    "jenis_responden": "Usaha",
    "dokumentasi": "https://drive.google.com/file/d/FILE_ID/view",
    "keterangan": "Catatan",
    "source_sheet": "SETU",
    "external_id": "baris-123"
  }'
```

Header JSON juga dapat menggunakan variasi kapitalisasi/spasi seperti `Nama PPL`, `Tanggal Pendataan`, dan `Source Sheet`. Laravel menormalisasinya ke nama field internal.

`external_id` boleh dikosongkan. Laravel membuat identifier SHA-256 dari data sumber. Duplicate ditentukan oleh kombinasi `source_sheet + external_id`.

Response sukses baru: HTTP `201`.

```json
{
  "message": "Data kartu kendali berhasil disimpan.",
  "data": {
    "id": 36,
    "kecamatan": "SETU",
    "external_id": "baris-123",
  }
}
```

Response duplicate: HTTP `200`.

```json
{
  "message": "Data sudah ada, tidak disimpan ulang.",
  "duplicate": true
}
```

Response token salah: HTTP `401`.

Response validasi gagal: HTTP `422` dengan object `errors` per field.

## GET `/api/kartu-kendali`

Query parameter yang tersedia:

| Parameter | Keterangan |
|---|---|
| `page` | Nomor halaman |
| `per_page` | 5 sampai 100; default 15 |
| `search` | Cari nama responden, PPL, PML, kelurahan, atau SLS |
| `kecamatan` | Filter kecamatan tepat |
| `kelurahan` | Filter kelurahan tepat sesuai kecamatan |
| `sls` | Filter SLS tepat sesuai kecamatan |
| `ppl` | Filter nama PPL tepat sesuai kecamatan |
| `pml` | Filter nama PML tepat sesuai kecamatan |
| `jenis_responden` | Filter jenis responden tepat |
| `tanggal` | Filter tanggal `YYYY-MM-DD` |

Contoh:

```text
GET /api/kartu-kendali?kecamatan=SETU&search=Responden&page=1&per_page=15
```

Response menggunakan format pagination Laravel:

```json
{
  "current_page": 1,
  "data": [],
  "per_page": 15,
  "total": 0,
  "last_page": 1
}
```

## GET `/api/kartu-kendali/statistics`

Mengembalikan:

- `total_responden`
- `total_ppl`
- `total_pml`
- `total_sls`
- `per_kecamatan` untuk 7 kecamatan
- `per_kecamatan_detail` berisi jumlah responden, PPL, PML, dan SLS per kecamatan
- `per_kecamatan_jenis_responden` berisi Keluarga, Usaha, dan Campuran per kecamatan
- `top_ppl` dan `bottom_ppl` berisi peringkat PPL dengan rincian jenis responden
- `filter_options` berisi pilihan unik yang mengikuti filter `kecamatan`
- `updated_at`

## GET `/api/kartu-kendali/{id}`

Mengambil satu record berdasarkan ID database.

- HTTP `200` jika ditemukan.
- HTTP `404` jika tidak ditemukan.

## Batas request

POST dibatasi middleware `throttle:60,1`, yaitu maksimal 60 request per menit per key throttle Laravel. Token tetap wajib; rate limit bukan pengganti autentikasi.

## Operasional

Setelah mengubah `.env`, bersihkan/cache konfigurasi sesuai kebutuhan:

```bash
php artisan config:clear
php artisan optimize
```
