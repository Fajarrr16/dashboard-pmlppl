# Integrasi Google Apps Script

Dokumen ini menyediakan satu script reusable untuk 7 spreadsheet wilayah. Script hanya membaca spreadsheet dan mengirim data ke Laravel API; script tidak mengubah data di Google Spreadsheet.

## Konfigurasi Script Properties

Buka spreadsheet, pilih **Extensions > Apps Script**, lalu buka **Project Settings > Script properties**. Isi properti berikut:

| Property | Nilai |
|---|---|
| `KARTU_API_URL` | URL lengkap endpoint POST Laravel, misalnya `https://subdomain.ngrok-free.app/api/kartu-kendali` |
| `KARTU_API_TOKEN` | Nilai `KARTU_KENDALI_API_TOKEN` dari environment Laravel |
| `KARTU_WILAYAH` | Salah satu dari 7 wilayah yang sesuai dengan spreadsheet |
| `KARTU_SOURCE_SHEET` | Nama sumber; boleh dikosongkan agar memakai nama spreadsheet aktif |

Jangan menaruh token di source code. Script membaca token dari Script Properties.

## Script lengkap

Salin seluruh kode berikut ke setiap spreadsheet. Kode yang sama dipakai untuk semua wilayah; hanya `KARTU_WILAYAH` yang berbeda.

```javascript
const CONFIG_KEYS = {
  apiUrl: 'KARTU_API_URL',
  apiToken: 'KARTU_API_TOKEN',
  wilayah: 'KARTU_WILAYAH',
  sourceSheet: 'KARTU_SOURCE_SHEET',
  lastProcessedRow: 'KARTU_LAST_PROCESSED_ROW',
};

const REQUIRED_WILAYAH = [
  'SETU',
  'SERPONG',
  'PAMULANG',
  'CIPUTAT',
  'CIPUTAT TIMUR',
  'PONDOK AREN',
  'SERPONG UTARA',
];

function getConfig_() {
  const properties = PropertiesService.getScriptProperties();
  const config = {
    apiUrl: properties.getProperty(CONFIG_KEYS.apiUrl),
    apiToken: properties.getProperty(CONFIG_KEYS.apiToken),
    wilayah: properties.getProperty(CONFIG_KEYS.wilayah),
    sourceSheet: properties.getProperty(CONFIG_KEYS.sourceSheet),
  };

  if (!config.apiUrl || !config.apiToken || !config.wilayah) {
    throw new Error('KARTU_API_URL, KARTU_API_TOKEN, dan KARTU_WILAYAH wajib diisi di Script Properties.');
  }

  if (!REQUIRED_WILAYAH.includes(config.wilayah)) {
    throw new Error('KARTU_WILAYAH tidak termasuk daftar 7 wilayah yang didukung.');
  }

  return config;
}

function normalizeHeader_(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ');
}

function headerMap_() {
  return {
    timestamp: 'timestamp_google',
    'timestamp google': 'timestamp_google',
    'tanggal pendataan': 'tanggal_pendataan',
    tanggalpendataan: 'tanggal_pendataan',
    'nama ppl': 'nama_ppl',
    namappl: 'nama_ppl',
    'nama pml': 'nama_pml',
    namapml: 'nama_pml',
    kelurahan: 'kelurahan',
    sls: 'sls',
    'nama responden': 'nama_responden',
    namaresponden: 'nama_responden',
    'jenis responden': 'jenis_responden',
    jenisresponden: 'jenis_responden',
    dokumentasi: 'dokumentasi',
    keterangan: 'keterangan',
    wilayah: 'wilayah',
    'source sheet': 'source_sheet',
    sourcesheet: 'source_sheet',
    externalid: 'external_id',
    'external id': 'external_id',
  };
}

function buildPayload_(headers, values, config) {
  const mapping = headerMap_();
  const payload = {};

  headers.forEach(function (header, index) {
    const key = mapping[normalizeHeader_(header)];
    if (key) {
      payload[key] = values[index] === undefined ? '' : values[index];
    }
  });

  payload.wilayah = config.wilayah;
  payload.source_sheet = config.sourceSheet || SpreadsheetApp.getActiveSpreadsheet().getName();
  payload.external_id = payload.external_id || createExternalId_(payload);

  return payload;
}

function createExternalId_(payload) {
  const stableData = [
    payload.source_sheet,
    payload.timestamp_google,
    payload.tanggal_pendataan,
    payload.nama_ppl,
    payload.nama_pml,
    payload.kelurahan,
    payload.sls,
    payload.nama_responden,
    payload.jenis_responden,
    payload.dokumentasi,
    payload.keterangan,
  ].map(function (value) {
    return String(value || '').trim();
  }).join('|');

  const digest = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256,
    stableData,
    Utilities.Charset.UTF_8
  );

  return 'gas-' + Utilities.base64EncodeWebSafe(digest).replace(/=+$/, '');
}

function sendPayload_(payload, config) {
  const response = UrlFetchApp.fetch(config.apiUrl, {
    method: 'post',
    contentType: 'application/json',
    headers: {
      Authorization: 'Bearer ' + config.apiToken,
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true,
  });

  const status = response.getResponseCode();
  const body = response.getContentText();
  let parsedBody = {};

  try {
    parsedBody = body ? JSON.parse(body) : {};
  } catch (error) {
    console.error('Response Laravel bukan JSON: ' + body);
  }

  if (status < 200 || status >= 300) {
    throw new Error('Laravel mengembalikan HTTP ' + status + ': ' + body);
  }

  console.log('Data terkirim: ' + JSON.stringify({
    status: status,
    duplicate: parsedBody.duplicate || false,
    external_id: payload.external_id,
  }));

  return parsedBody;
}

function sendRow_(sheet, rowNumber) {
  const config = getConfig_();
  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  const values = sheet.getRange(rowNumber, 1, 1, lastColumn).getDisplayValues()[0];

  if (values.join('').trim() === '') {
    return;
  }

  const payload = buildPayload_(headers, values, config);
  sendPayload_(payload, config);
}

function onFormSubmit(e) {
  if (!e || !e.range) {
    throw new Error('Trigger onFormSubmit harus dipanggil oleh event spreadsheet.');
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    sendRow_(e.range.getSheet(), e.range.getRow());
  } catch (error) {
    console.error('Gagal mengirim row form submit: ' + (error.stack || error));
    throw error;
  } finally {
    lock.releaseLock();
  }
}

function syncNewRows() {
  const config = getConfig_();
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  const lastRow = sheet.getLastRow();
  const properties = PropertiesService.getScriptProperties();
  const previousRow = Number(properties.getProperty(CONFIG_KEYS.lastProcessedRow) || 1);
  const firstRow = Math.max(previousRow + 1, 2);

  if (lastRow < firstRow) {
    return;
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    for (let row = firstRow; row <= lastRow; row += 1) {
      sendRow_(sheet, row);
      properties.setProperty(CONFIG_KEYS.lastProcessedRow, String(row));
    }
  } finally {
    lock.releaseLock();
  }
}

function installTimeTrigger() {
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (trigger.getHandlerFunction() === 'syncNewRows') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('syncNewRows')
    .timeBased()
    .everyMinutes(5)
    .create();
}

function resetSyncCursor() {
  PropertiesService.getScriptProperties().deleteProperty(CONFIG_KEYS.lastProcessedRow);
}
```

## Catatan script

- Header dibaca dari baris pertama, bukan dari nomor kolom tetap.
- Nama header dinormalisasi terhadap spasi, underscore, tanda hubung, dan kapitalisasi.
- `external_id` dibuat deterministik dari isi row dan `source_sheet`. Pengiriman yang sama akan diterima Laravel sebagai duplicate dan tidak membuat row kedua.
- `onFormSubmit(e)` adalah jalur utama.
- `syncNewRows()` adalah fallback berbasis waktu. Cursor row disimpan di Script Properties.
- Jika row diedit setelah berhasil dikirim, hasilnya memiliki `external_id` berbeda dan dianggap record baru. Untuk koreksi data, gunakan proses koreksi terpisah; script ini tidak mengubah row yang sudah tersimpan.

## Pemasangan untuk 7 spreadsheet

Pasang script yang sama pada masing-masing spreadsheet dan isi `KARTU_WILAYAH` sesuai tabel berikut:

| Spreadsheet | `KARTU_WILAYAH` |
|---|---|
| Spreadsheet Setu | `SETU` |
| Spreadsheet Serpong | `SERPONG` |
| Spreadsheet Pamulang | `PAMULANG` |
| Spreadsheet Ciputat | `CIPUTAT` |
| Spreadsheet Ciputat Timur | `CIPUTAT TIMUR` |
| Spreadsheet Pondok Aren | `PONDOK AREN` |
| Spreadsheet Serpong Utara | `SERPONG UTARA` |

## Trigger utama

1. Buka **Extensions > Apps Script**.
2. Pilih menu **Triggers**.
3. Klik **Add Trigger**.
4. Function: `onFormSubmit`.
5. Event source: **From spreadsheet**.
6. Event type: **On form submit**.
7. Simpan dan berikan authorization yang diminta Google.

Ulangi untuk semua 7 spreadsheet.

## Trigger fallback

Jika event `On form submit` tidak tersedia atau tidak berjalan:

1. Pastikan `KARTU_API_URL`, `KARTU_API_TOKEN`, dan `KARTU_WILAYAH` sudah diisi.
2. Jalankan fungsi `installTimeTrigger()` satu kali dari editor Apps Script.
3. Berikan authorization.
4. Script akan memeriksa row baru setiap 5 menit.

Jalankan `resetSyncCursor()` hanya jika cursor perlu diulang dari row 2. API tetap mencegah duplicate melalui `source_sheet + external_id`.
