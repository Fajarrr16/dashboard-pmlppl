const PML_PROP = {
  API_URL: 'KARTU_PML_API_URL',
  API_TOKEN: 'KARTU_PML_API_TOKEN',
  SOURCE_SHEET: 'KARTU_PML_SOURCE_SHEET',
  SPREADSHEET_ID: 'KARTU_PML_SPREADSHEET_ID',
  LAST_ROW: 'KARTU_PML_LAST_PROCESSED_ROW',
};

const PML_KECAMATAN_VALID = [
  'SETU',
  'SERPONG',
  'PAMULANG',
  'CIPUTAT',
  'CIPUTAT TIMUR',
  'PONDOK AREN',
  'SERPONG UTARA',
];

const PML_REQUEST_DELAY_MS = 2000;

function getPmlConfig_() {
  const properties = PropertiesService.getScriptProperties();
  const config = {
    apiUrl: String(properties.getProperty(PML_PROP.API_URL) || '').trim().replace(/\/+$/, ''),
    apiToken: properties.getProperty(PML_PROP.API_TOKEN),
    sourceSheet: String(properties.getProperty(PML_PROP.SOURCE_SHEET) || '').trim(),
    spreadsheetId: properties.getProperty(PML_PROP.SPREADSHEET_ID),
  };

  if (!config.apiUrl || !config.apiToken || !config.sourceSheet) {
    throw new Error('Isi KARTU_PML_API_URL, KARTU_PML_API_TOKEN, dan KARTU_PML_SOURCE_SHEET di Script Properties.');
  }

  if (/\/api$/i.test(config.apiUrl)) {
    config.apiUrl += '/pml-kartu-kendali';
  } else if (!/\/api\/pml-kartu-kendali$/i.test(config.apiUrl)) {
    config.apiUrl += '/api/pml-kartu-kendali';
  }

  return config;
}

function normalizePmlHeader_(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/["“”]/g, '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizePmlDate_(value) {
  const text = String(value || '').trim();
  const match = text.match(/^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})/);

  if (!match) {
    return /^\d{4}-\d{2}-\d{2}/.test(text) ? text.substring(0, 10) : text;
  }

  const first = Number(match[1]);
  const second = Number(match[2]);
  const month = second > 12 ? first : second;
  const day = second > 12 ? second : first;

  return `${match[3]}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function buildPmlPayload_(headers, values, sheet, rowNumber, config) {
  const mapping = {
    timestamp: 'timestamp_google',
    'timestamp google': 'timestamp_google',
    'email address': 'email_address',
    email: 'email_address',
    'tanggal pendataan': 'tanggal_pendataan',
    tanggal_pendataan: 'tanggal_pendataan',
    'nama pml': 'nama_pml',
    nama_pml: 'nama_pml',
    kecamatan: 'kecamatan',
    kelurahan: 'kelurahan',
    sls: 'sls',
    'jenis responden': 'jenis_responden',
    jenis_responden: 'jenis_responden',
    dokumentasi: 'dokumentasi',
  };
  const payload = {};

  headers.forEach(function (header, index) {
    const normalizedHeader = normalizePmlHeader_(header);
    let field = mapping[normalizedHeader];

    if (!field && normalizedHeader.indexOf('dokumentasi') === 0) {
      field = 'dokumentasi';
    }

    if (!field) {
      return;
    }

    const value = String(values[index] || '').trim();
    payload[field] = field === 'tanggal_pendataan' ? normalizePmlDate_(value) : value;
  });

  payload.kecamatan = String(payload.kecamatan || '').trim().toUpperCase();
  if (!PML_KECAMATAN_VALID.includes(payload.kecamatan)) {
    throw new Error(`Kecamatan pada baris ${rowNumber} tidak valid: ${payload.kecamatan || '(kosong)'}`);
  }

  payload.source_sheet = config.sourceSheet || sheet.getName();
  payload.external_id = createPmlExternalId_(sheet, rowNumber, payload);

  return payload;
}

function createPmlExternalId_(sheet, rowNumber, payload) {
  const spreadsheetId = sheet.getParent().getId();
  const stableValue = [
    spreadsheetId,
    sheet.getSheetId(),
    rowNumber,
    payload.timestamp_google,
    payload.email_address,
  ].join('|');
  const digest = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256,
    stableValue,
    Utilities.Charset.UTF_8
  );

  return 'pml-gas-' + Utilities.base64EncodeWebSafe(digest).replace(/=+$/, '');
}

function sendPmlToLaravel_(payload, config) {
  const response = UrlFetchApp.fetch(config.apiUrl, {
    method: 'post',
    contentType: 'application/json',
    headers: {
      Authorization: 'Bearer ' + config.apiToken,
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true,
  });
  const statusCode = response.getResponseCode();
  const responseText = response.getContentText();

  if (statusCode < 200 || statusCode >= 300) {
    throw new Error(`Laravel HTTP ${statusCode}: ${responseText.substring(0, 500)}`);
  }

  const responseJson = responseText ? JSON.parse(responseText) : {};
  console.log(JSON.stringify({
    status: statusCode,
    kecamatan: payload.kecamatan,
    nama_pml: payload.nama_pml,
    duplicate: responseJson.duplicate || false,
    external_id: payload.external_id,
  }));

  return responseJson;
}

function sendPmlRow_(sheet, rowNumber, config) {
  const lastColumn = sheet.getLastColumn();
  if (lastColumn < 1 || rowNumber < 2) {
    return { empty: true };
  }

  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  const values = sheet.getRange(rowNumber, 1, 1, lastColumn).getDisplayValues()[0];
  if (values.join('').trim() === '') {
    return { empty: true };
  }

  const payload = buildPmlPayload_(headers, values, sheet, rowNumber, config);
  return sendPmlToLaravel_(payload, config);
}

function onPmlFormSubmit(event) {
  if (!event || !event.range) {
    throw new Error('Jalankan onPmlFormSubmit melalui pemicu Form Submit spreadsheet.');
  }

  const config = getPmlConfig_();
  const sheet = event.range.getSheet();
  if (sheet.getName() !== config.sourceSheet) {
    console.log(`Baris diabaikan karena tab ${sheet.getName()} bukan ${config.sourceSheet}.`);
    return;
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    sendPmlRow_(sheet, event.range.getRow(), config);
  } finally {
    lock.releaseLock();
  }
}

function syncPmlRows() {
  const config = getPmlConfig_();
  const spreadsheetId = config.spreadsheetId || SpreadsheetApp.getActiveSpreadsheet().getId();
  const spreadsheet = SpreadsheetApp.openById(spreadsheetId);
  const sheet = spreadsheet.getSheetByName(config.sourceSheet);

  if (!sheet) {
    throw new Error(`Tab spreadsheet tidak ditemukan: ${config.sourceSheet}`);
  }

  const properties = PropertiesService.getScriptProperties();
  const lastRow = sheet.getLastRow();
  const previousRow = Number(properties.getProperty(PML_PROP.LAST_ROW) || 1);
  const firstRow = Math.max(previousRow + 1, 2);
  if (lastRow < firstRow) {
    console.log(`Tidak ada baris PML baru. (Terakhir diproses: ${previousRow}, Terakhir di sheet: ${lastRow}).`);
    console.log('Gunakan syncAllPmlRows() jika ingin memeriksa atau mengirim ulang seluruh data dari awal.');
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
        const result = sendPmlRow_(sheet, row, config);
        if (result && result.duplicate) {
          duplicateCount++;
        } else if (result && !result.empty) {
          successCount++;
        }
      } catch (err) {
        failCount++;
        console.error(`[Baris ${row}] Gagal kirim: ${err.message || String(err)}`);
      }

      properties.setProperty(PML_PROP.LAST_ROW, String(row));

      if (row < lastRow) {
        Utilities.sleep(PML_REQUEST_DELAY_MS);
      }
    }

    console.log(`Sinkronisasi PML baris baru selesai. Baru: ${successCount}, Duplikat: ${duplicateCount}, Gagal: ${failCount}.`);
  } finally {
    lock.releaseLock();
  }
}

function syncAllPmlRows() {
  const config = getPmlConfig_();
  const spreadsheetId = config.spreadsheetId || SpreadsheetApp.getActiveSpreadsheet().getId();
  const spreadsheet = SpreadsheetApp.openById(spreadsheetId);
  const sheet = spreadsheet.getSheetByName(config.sourceSheet);

  if (!sheet) {
    throw new Error(`Tab spreadsheet tidak ditemukan: ${config.sourceSheet}`);
  }

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    console.log('Tidak ada data PML pada spreadsheet.');
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

  console.log(`Memulai sinkronisasi seluruh baris PML (Baris 2 s.d. ${lastRow})...`);

  try {
    for (let row = 2; row <= lastRow; row++) {
      stats.totalChecked++;
      try {
        const result = sendPmlRow_(sheet, row, config);
        if (result && result.empty) {
          stats.empty++;
          console.log(`[Baris ${row}] Dilewati (kosong).`);
        } else if (result && result.duplicate) {
          stats.duplicate++;
          console.log(`[Baris ${row}] Sudah ada di dashboard (duplikat).`);
        } else {
          stats.created++;
          console.log(`[Baris ${row}] BERHASIL disimpan baru ke dashboard.`);
        }
      } catch (err) {
        stats.failed++;
        stats.failedRows.push({ row: row, error: err.message || String(err) });
        console.error(`[Baris ${row}] GAGAL: ${err.message || String(err)}`);
      }

      if (row < lastRow) {
        Utilities.sleep(PML_REQUEST_DELAY_MS);
      }
    }

    PropertiesService.getScriptProperties().setProperty(PML_PROP.LAST_ROW, String(lastRow));

    console.log('====================================');
    console.log('=== RINGKASAN SINKRONISASI SEMUA BARIS PML ===');
    console.log(`Total baris diperiksa : ${stats.totalChecked}`);
    console.log(`Berhasil baru         : ${stats.created}`);
    console.log(`Sudah ada (duplikat)  : ${stats.duplicate}`);
    console.log(`Baris kosong dilewati : ${stats.empty}`);
    console.log(`Gagal / Error         : ${stats.failed}`);
    if (stats.failed > 0) {
      console.log(`Detail baris gagal: ${JSON.stringify(stats.failedRows)}`);
    }
    console.log('====================================');
  } finally {
    lock.releaseLock();
  }
}

function installPmlTriggers() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) {
    throw new Error('Buka project Apps Script yang terikat pada spreadsheet PML sebelum memasang pemicu.');
  }

  const properties = PropertiesService.getScriptProperties();
  if (!properties.getProperty(PML_PROP.SOURCE_SHEET)) {
    const activeSheet = spreadsheet.getActiveSheet();
    if (!activeSheet) {
      throw new Error('Pilih tab respons PML atau isi KARTU_PML_SOURCE_SHEET.');
    }
    properties.setProperty(PML_PROP.SOURCE_SHEET, activeSheet.getName());
  }
  properties.setProperty(PML_PROP.SPREADSHEET_ID, spreadsheet.getId());

  const config = getPmlConfig_();
  if (!spreadsheet.getSheetByName(config.sourceSheet)) {
    throw new Error(`Tab respons PML tidak ditemukan: ${config.sourceSheet}`);
  }

  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (['onPmlFormSubmit', 'syncPmlRows'].includes(trigger.getHandlerFunction())) {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  ScriptApp.newTrigger('onPmlFormSubmit')
    .forSpreadsheet(spreadsheet)
    .onFormSubmit()
    .create();
  ScriptApp.newTrigger('syncPmlRows')
    .timeBased()
    .everyMinutes(5)
    .create();

  console.log(`Pemicu PML aktif untuk tab ${config.sourceSheet}.`);
}

function resetPmlSyncCursor() {
  PropertiesService.getScriptProperties().deleteProperty(PML_PROP.LAST_ROW);
  console.log('Cursor sinkronisasi PML di-reset. Jalankan syncPmlRows atau syncAllPmlRows untuk memulai ulang.');
}
