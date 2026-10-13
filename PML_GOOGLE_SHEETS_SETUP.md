# Setup Spreadsheet Kartu Kendali PML

Spreadsheet PML memakai tabel dan API terpisah dari data responden PPL. Data PML tidak menambah hitungan responden PPL.

## Header Spreadsheet

Buat spreadsheet Google Form/response dengan satu baris header. Header berikut dikenali:

| Header | Field API | Wajib |
| --- | --- | --- |
| Timestamp | `timestamp_google` | Disarankan dari timestamp Google Forms |
| Email Address | `email_address` | Tidak |
| Tanggal Pendataan | `tanggal_pendataan` | Ya |
| Nama PML | `nama_pml` | Ya |
| Kecamatan | `kecamatan` | Ya; salah satu dari 7 kecamatan Tangsel |
| Kelurahan | `kelurahan` | Ya |
| Jenis Responden | `jenis_responden` | Ya |
| Dokumentasi dengan ketentuan ... | `dokumentasi` | Ya; satu atau lebih URL, pisahkan dengan baris baru atau titik koma |
| SLS | `sls` | Opsional pada API, tetapi tambahkan untuk validasi dokumentasi per SLS |

Script mengenali header dokumentasi yang dimulai dengan `Dokumentasi`, termasuk header panjang berisi ketentuan foto.

**Catatan aturan foto:** kolom yang diberikan saat ini belum memiliki SLS. Karena itu aplikasi belum bisa memeriksa apakah setiap SLS memiliki minimal dua foto. Tambahkan kolom `SLS` jika laporan atau validasi otomatis per SLS diperlukan. Foto yang sama boleh dipakai ulang; endpoint tidak menolak URL dokumentasi yang berulang.

Setiap baris dapat berisi satu atau beberapa URL foto Google Drive. Pastikan pengaturan akses file Drive memungkinkan petugas yang berwenang melihatnya.

## Deploy Aplikasi

1. Pastikan `KARTU_KENDALI_API_TOKEN` pada environment Laravel tetap terisi dengan token yang dipakai endpoint PPL.
2. Deploy kode aplikasi.
3. Jalankan migrasi pada database tujuan:

   ```bash
   php artisan migrate --force
   ```

4. Endpoint penerimaan PML adalah `https://HOST-ANDA/api/pml-kartu-kendali`.
5. Endpoint GET daftar PML adalah `https://HOST-ANDA/api/pml-kartu-kendali`. Email tidak disertakan pada respons baca maupun statistik dashboard.

POST PML memakai middleware bearer token yang sama dengan endpoint PPL. Jangan menaruh token di sel spreadsheet atau membagikannya di chat.

## Pasang Apps Script

1. Buka spreadsheet PML, lalu pilih **Extensions > Apps Script**.
2. Ganti isi file script dengan kode dari `KodePML.gs` di project ini.
3. Di Apps Script, buka **Project Settings > Script Properties**, lalu tambahkan:

   | Property | Nilai |
   | --- | --- |
   | `KARTU_PML_API_URL` | URL publik aplikasi, misalnya `https://HOST-ANDA` atau URL lengkap yang diakhiri `/api/pml-kartu-kendali` |
   | `KARTU_PML_API_TOKEN` | Nilai yang sama dengan `KARTU_KENDALI_API_TOKEN` Laravel |
   | `KARTU_PML_SOURCE_SHEET` | Nama tab response yang berisi baris PML, persis seperti tab di spreadsheet |

4. Set timezone project Apps Script dan spreadsheet ke `Asia/Jakarta`.
5. Pilih fungsi `installPmlTriggers` pada dropdown fungsi Apps Script, lalu tekan **Run** satu kali dan setujui izin Google yang diminta.
6. Script memasang dua pemicu: saat form mengirim baris baru, dan sinkronisasi cadangan setiap lima menit.
7. Kirim satu respons uji melalui Form. Pastikan API mengembalikan HTTP 201, lalu cek panel **Aktivitas Kartu Kendali PML** di dashboard.

`installPmlTriggers` menyimpan ID spreadsheet secara otomatis. Jika `KARTU_PML_SOURCE_SHEET` belum diisi, script memakai tab yang sedang aktif; disarankan tetap mengisi nama tab secara eksplisit sebelum memasang pemicu.

## Sinkronisasi Ulang

`syncPmlRows` membaca baris sesudah cursor terakhir. Jika perlu mengirim ulang semua baris, pilih dan jalankan `resetPmlSyncCursor`, kemudian jalankan `syncPmlRows`. API melakukan deduplikasi berdasarkan `source_sheet` dan `external_id`, sehingga baris yang sama tidak dibuat ulang.

Jangan menghapus baris atau mengurutkan ulang tab setelah cursor sinkronisasi digunakan. Jika perlu mengurutkan data, gunakan sheet lain untuk analisis. Pemicu Form Submit tetap mengirim baris yang masuk langsung.
