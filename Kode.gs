const PROP = {
  API_URL: 'KARTU_API_URL',
  API_TOKEN: 'KARTU_API_TOKEN',
  KECAMATAN: 'KARTU_KECAMATAN',
  SOURCE_SHEET: 'KARTU_SOURCE_SHEET',
  LAST_ROW: 'KARTU_LAST_PROCESSED_ROW',
};

function normalizeApiUrl_(value) {
  const text = String(value || '').trim();

  if (!text) {
    return text;
  }

  const withoutTrailingSlash = text.replace(/\/+$/, '');

  if (/\/api\/kartu-kendali$/i.test(withoutTrailingSlash)) {
    return withoutTrailingSlash;
  }

  return withoutTrailingSlash + '/api/kartu-kendali';
}

const KECAMATAN_VALID = [
  'SETU',
  'SERPONG',
  'PAMULANG',
  'CIPUTAT',
  'CIPUTAT TIMUR',
  'PONDOK AREN',
  'SERPONG UTARA',
];

const REQUEST_DELAY_MS = 2000;

function sleep_(milliseconds) {
  Utilities.sleep(milliseconds);
}

function getConfig_() {
  const props = PropertiesService.getScriptProperties();
  const config = {
    apiUrl: normalizeApiUrl_(props.getProperty(PROP.API_URL)),
    apiToken: props.getProperty(PROP.API_TOKEN),
    kecamatan: props.getProperty(PROP.KECAMATAN),
    sourceSheet: props.getProperty(PROP.SOURCE_SHEET),
  };

  if (!config.apiUrl || !config.apiToken || !config.kecamatan) {
    throw new Error(
      'KARTU_API_URL, KARTU_API_TOKEN, dan KARTU_KECAMATAN wajib diisi.'
    );
  }

  config.kecamatan = String(config.kecamatan).trim().toUpperCase();

  if (!KECAMATAN_VALID.includes(config.kecamatan)) {
    throw new Error('Nilai KARTU_KECAMATAN tidak valid: ' + config.kecamatan);
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

function normalizeDate_(value) {
  const text = String(value || '').trim();
  const match = text.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})/);

  if (!match) {
    return /^\d{4}-\d{2}-\d{2}/.test(text) ? text.substring(0, 10) : text;
  }

  const first = Number(match[1]);
  const second = Number(match[2]);
  const year = match[3];
  const month = second > 12 ? first : second;
  const day = second > 12 ? second : first;

  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function normalizeDateTime_(value) {
  const text = String(value || '').trim();

  if (!text) {
    return '';
  }

  if (/^\d{4}-\d{2}-\d{2}/.test(text)) {
    return text.replace('T', ' ').replace(/([+-]\d{2}:?\d{2}|Z)$/, '').substring(0, 19);
  }

    const match = text.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/);

  if (!match) {
    return text;
  }

  const first = Number(match[1]);
  const second = Number(match[2]);
  const year = match[3];
  const month = second > 12 ? first : second;
  const day = second > 12 ? second : first;
  const hour = match[4] || '00';
  const minute = match[5] || '00';
  const secondValue = match[6] || '00';

  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')} ${String(hour).padStart(2, '0')}:${minute}:${secondValue}`;
}

function getHeaderMapping_() {
  return {
    timestamp: 'timestamp_google',
    'timestamp google': 'timestamp_google',
    timestamp_google: 'timestamp_google',
    'tanggal pendataan': 'tanggal_pendataan',
    tanggalpendataan: 'tanggal_pendataan',
    tanggal_pendataan: 'tanggal_pendataan',
    'waktu pendataan': 'waktu_pendataan',
    waktupendataan: 'waktu_pendataan',
    waktu_pendataan: 'waktu_pendataan',
    'nama ppl': 'nama_ppl',
    namappl: 'nama_ppl',
    nama_ppl: 'nama_ppl',
    'nama pml': 'nama_pml',
    namapml: 'nama_pml',
    nama_pml: 'nama_pml',
    kelurahan: 'kelurahan',
    sls: 'sls',
    'nama responden': 'nama_responden',
    namaresponden: 'nama_responden',
    nama_responden: 'nama_responden',
    'jenis responden': 'jenis_responden',
    jenisresponden: 'jenis_responden',
    jenis_responden: 'jenis_responden',
    dokumentasi: 'dokumentasi',
    keterangan: 'keterangan',
    kecamatan: 'kecamatan',
    source_sheet: 'source_sheet',
    'source sheet': 'source_sheet',
    sourcesheet: 'source_sheet',
    external_id: 'external_id',
    'external id': 'external_id',
    externalid: 'external_id',
  };
}

function buildPayload_(headers, values, config) {
  const mapping = getHeaderMapping_();
  const payload = {};

  headers.forEach(function (header, index) {
    const fieldName = mapping[normalizeHeader_(header)];

    if (!fieldName) {
      return;
    }

    const value = values[index] || '';
    if (fieldName === 'tanggal_pendataan') {
      payload[fieldName] = normalizeDate_(value);
    } else if (fieldName === 'waktu_pendataan') {
      payload[fieldName] = normalizeDateTime_(value);
    } else {
      payload[fieldName] = value;
    }
  });

  payload.kecamatan = config.kecamatan;
  payload.source_sheet =
    config.sourceSheet || SpreadsheetApp.getActiveSpreadsheet().getName();

  if (!payload.external_id) {
    payload.external_id = createExternalId_(payload);
  }

  return payload;
}

function createExternalId_(payload) {
  const stableText = [
    payload.source_sheet,
    payload.timestamp_google,
    payload.tanggal_pendataan,
    payload.waktu_pendataan,
    payload.nama_ppl,
    payload.nama_pml,
    payload.kecamatan,
    payload.kelurahan,
    payload.sls,
    payload.nama_responden,
    payload.jenis_responden,
    payload.dokumentasi,
    payload.keterangan,
  ]
    .map(function (value) {
      return String(value || '').trim();
    })
    .join('|');

  const digest = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256,
    stableText,
    Utilities.Charset.UTF_8
  );

  return 'gas-' + Utilities.base64EncodeWebSafe(digest).replace(/=+$/, '');
}

function sendToLaravel_(payload, config) {
  const response = UrlFetchApp.fetch(config.apiUrl, {
    method: 'post',
    contentType: 'application/json',
    headers: {
      Authorization: 'Bearer ' + config.apiToken,
      'ngrok-skip-browser-warning': 'true',
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true,
  });

  const statusCode = response.getResponseCode();
  const responseText = response.getContentText();

  if (statusCode < 200 || statusCode >= 300) {
    throw new Error(
      'Laravel HTTP ' + statusCode + ': ' + responseText.substring(0, 500)
    );
  }

  const responseJson = responseText ? JSON.parse(responseText) : {};
  console.log(
    JSON.stringify({
      status: statusCode,
      kecamatan: payload.kecamatan,
      duplicate: responseJson.duplicate || false,
      external_id: payload.external_id,
    })
  );

  return responseJson;
}

function sendRow_(sheet, rowNumber) {
  const config = getConfig_();
  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  const values = sheet.getRange(rowNumber, 1, 1, lastColumn).getDisplayValues()[0];

  if (values.join('').trim() === '') {
    return { empty: true };
  }

  const payload = buildPayload_(headers, values, config);
  return sendToLaravel_(payload, config);
}

function onFormSubmit(e) {
  if (!e || !e.range) {
    throw new Error('onFormSubmit harus dijalankan melalui trigger spreadsheet.');
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    sendRow_(e.range.getSheet(), e.range.getRow());
  } catch (error) {
    console.error('Gagal mengirim data: ' + (error.stack || error));
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
  const previousRow = Number(properties.getProperty(PROP.LAST_ROW) || 1);
  const firstRow = Math.max(previousRow + 1, 2);

  if (lastRow < firstRow) {
    console.log(
      'Tidak ada baris baru untuk dikirim. (Baris terakhir diproses: ' +
        previousRow +
        ', Baris terakhir sheet: ' +
        lastRow +
        ').'
    );
    console.log(
      'Gunakan fungsi syncAllRows() jika ingin memeriksa atau mengirim ulang seluruh data dari awal.'
    );
    return;
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  let successCount = 0;
  let duplicateCount = 0;
  let failCount = 0;

  try {
    for (let row = firstRow; row <= lastRow; row++) {
      try {
        const result = sendRow_(sheet, row);
        if (result && result.duplicate) {
          duplicateCount++;
        } else if (result && !result.empty) {
          successCount++;
        }
      } catch (err) {
        failCount++;
        console.error(
          '[Baris ' + row + '] Gagal kirim: ' + (err.message || String(err))
        );
      }

      properties.setProperty(PROP.LAST_ROW, String(row));

      if (row < lastRow) {
        sleep_(REQUEST_DELAY_MS);
      }
    }

    console.log(
      'Sinkronisasi baris baru selesai. Baru: ' +
        successCount +
        ', Duplikat: ' +
        duplicateCount +
        ', Gagal: ' +
        failCount
    );
  } finally {
    lock.releaseLock();
  }
}

function syncAllRows() {
  const config = getConfig_();
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  const lastRow = sheet.getLastRow();

  if (lastRow < 2) {
    console.log('Tidak ada data pada spreadsheet (hanya header atau kosong).');
    return;
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  const stats = {
    totalChecked: 0,
    created: 0,
    duplicate: 0,
    empty: 0,
    failed: 0,
    failedRows: [],
  };

  console.log(
    'Memulai sinkronisasi seluruh baris (Baris 2 s.d. ' +
      lastRow +
      ') untuk Kecamatan ' +
      config.kecamatan +
      '...'
  );

  try {
    for (let row = 2; row <= lastRow; row++) {
      stats.totalChecked++;
      try {
        const result = sendRow_(sheet, row);
        if (result && result.empty) {
          stats.empty++;
          console.log('[Baris ' + row + '] Dilewati (baris kosong).');
        } else if (result && result.duplicate) {
          stats.duplicate++;
          console.log('[Baris ' + row + '] Sudah ada di dashboard (duplikat).');
        } else {
          stats.created++;
          console.log('[Baris ' + row + '] BERHASIL disimpan baru ke dashboard.');
        }
      } catch (err) {
        stats.failed++;
        stats.failedRows.push({
          row: row,
          error: err.message || String(err),
        });
        console.error(
          '[Baris ' + row + '] GAGAL: ' + (err.message || String(err))
        );
      }

      if (row < lastRow) {
        sleep_(REQUEST_DELAY_MS);
      }
    }

    PropertiesService.getScriptProperties().setProperty(
      PROP.LAST_ROW,
      String(lastRow)
    );

    console.log('====================================');
    console.log('=== RINGKASAN SINKRONISASI SEMUA BARIS ===');
    console.log('Total baris data dicek : ' + stats.totalChecked);
    console.log('Berhasil disimpan baru : ' + stats.created);
    console.log('Sudah ada (duplikat)   : ' + stats.duplicate);
    console.log('Baris kosong dilewati  : ' + stats.empty);
    console.log('Gagal / Error          : ' + stats.failed);
    if (stats.failed > 0) {
      console.log('Detail baris gagal: ' + JSON.stringify(stats.failedRows));
    }
    console.log('====================================');
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

  ScriptApp.newTrigger('syncNewRows').timeBased().everyMinutes(5).create();
}

function resetSyncCursor() {
  PropertiesService.getScriptProperties().deleteProperty(PROP.LAST_ROW);
  console.log('Cursor sinkronisasi di-reset. Jalankan syncNewRows atau syncAllRows untuk memulai ulang.');
}
