const PROP = {
  API_URL: 'KARTU_API_URL',
  API_TOKEN: 'KARTU_API_TOKEN',
  WILAYAH: 'KARTU_WILAYAH',
  SOURCE_SHEET: 'KARTU_SOURCE_SHEET',
  LAST_ROW: 'KARTU_LAST_PROCESSED_ROW',
};

const WILAYAH_VALID = [
  'SETU',
  'SERPONG',
  'PAMULANG',
  'CIPUTAT',
  'CIPUTAT TIMUR',
  'PONDOK AREN',
  'SERPONG UTARA',
];

function getConfig_() {
  const props = PropertiesService.getScriptProperties();
  const config = {
    apiUrl: props.getProperty(PROP.API_URL),
    apiToken: props.getProperty(PROP.API_TOKEN),
    wilayah: props.getProperty(PROP.WILAYAH),
    sourceSheet: props.getProperty(PROP.SOURCE_SHEET),
  };

  if (!config.apiUrl || !config.apiToken || !config.wilayah) {
    throw new Error(
      'KARTU_API_URL, KARTU_API_TOKEN, dan KARTU_WILAYAH wajib diisi.'
    );
  }

  if (!WILAYAH_VALID.includes(config.wilayah)) {
    throw new Error('Nilai KARTU_WILAYAH tidak valid.');
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

  if (!text) {
    return '';
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
    return text;
  }

  const match = text.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/);

  if (!match) {
    return text;
  }

  const first = Number(match[1]);
  const second = Number(match[2]);
  const year = match[3];
  let day = first;
  let month = second;

  // Jika angka kedua lebih dari 12, formatnya pasti MM/DD/YYYY.
  if (second > 12) {
    month = first;
    day = second;
  }

  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function getHeaderMapping_() {
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
    'external id': 'external_id',
    externalid: 'external_id',
  };
}

function buildPayload_(headers, values, config) {
  const mapping = getHeaderMapping_();
  const payload = {};

  headers.forEach(function (header, index) {
    const fieldName = mapping[normalizeHeader_(header)];

    if (fieldName) {
      payload[fieldName] =
        fieldName === 'tanggal_pendataan'
          ? normalizeDate_(values[index])
          : values[index] || '';
    }
  });

  payload.wilayah = config.wilayah;
  payload.source_sheet =
    config.sourceSheet ||
    SpreadsheetApp.getActiveSpreadsheet().getName();

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
    payload.nama_ppl,
    payload.nama_pml,
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
      'Laravel HTTP ' +
        statusCode +
        ': ' +
        responseText.substring(0, 500)
    );
  }

  let responseJson;

  try {
    responseJson = responseText ? JSON.parse(responseText) : {};
  } catch (error) {
    throw new Error(
      'Respons Laravel bukan JSON. HTTP ' +
        statusCode +
        ': ' +
        responseText.substring(0, 500)
    );
  }

  console.log(
    JSON.stringify({
      status: statusCode,
      duplicate: responseJson.duplicate || false,
      external_id: payload.external_id,
    })
  );

  return responseJson;
}

function sendRow_(sheet, rowNumber) {
  const config = getConfig_();
  const lastColumn = sheet.getLastColumn();
  const headers = sheet
    .getRange(1, 1, 1, lastColumn)
    .getDisplayValues()[0];
  const values = sheet
    .getRange(rowNumber, 1, 1, lastColumn)
    .getDisplayValues()[0];

  if (values.join('').trim() === '') {
    return;
  }

  const payload = buildPayload_(headers, values, config);
  sendToLaravel_(payload, config);
}

function onFormSubmit(e) {
  if (!e || !e.range) {
    throw new Error(
      'onFormSubmit harus dijalankan melalui trigger spreadsheet.'
    );
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
  const previousRow = Number(
    properties.getProperty(PROP.LAST_ROW) || 1
  );
  const firstRow = Math.max(previousRow + 1, 2);

  if (lastRow < firstRow) {
    console.log('Tidak ada baris baru untuk dikirim.');
    return;
  }

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);

  try {
    for (let row = firstRow; row <= lastRow; row++) {
      sendRow_(sheet, row);
      properties.setProperty(PROP.LAST_ROW, String(row));
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
  PropertiesService.getScriptProperties().deleteProperty(PROP.LAST_ROW);
  console.log('Cursor sinkronisasi di-reset.');
}
