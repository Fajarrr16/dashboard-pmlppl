<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Penyisiran SE2026 Tangerang Selatan</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <img class="brand-logo" src="/images/bps-logo.svg" alt="">
                <div>
                    <div class="brand-title">BPS Kota Tangerang Selatan</div>
                    <div class="brand-sub">Sensus Ekonomi 2026</div>
                </div>
            </div>
            <div class="sidebar-section-label">MONITORING LAPANGAN</div>
            <nav class="nav">
                <button class="nav-item is-active" data-section="dashboard">Ringkasan</button>
                <button class="nav-item" data-section="data">Data Kartu Kendali</button>
                <button class="nav-item" data-section="report">Laporan Perjalanan Dinas</button>
                <button class="nav-item" data-section="api">Koneksi API</button>
                <button class="nav-item" data-section="fasih">Bahan Realokasi Cek FASIH</button>
            </nav>
            <div class="sidebar-footer">
                <span id="live-indicator" class="live-indicator">&#9679; LIVE</span>
                <span id="last-updated" class="last-updated">--:--:--</span>
            </div>
        </aside>

        <main class="main">
            <header class="header">
                <button class="menu-toggle" id="menu-toggle" aria-label="Menu">&#9776;</button>
                <div class="header-title-group">
                    <span class="header-eyebrow">BADAN PUSAT STATISTIK · KOTA TANGERANG SELATAN</span>
                    <h1 id="page-title">Dashboard Penyisiran SE2026</h1>
                    <p class="header-subtitle">Pemantauan pendataan lapangan dan kinerja PPL</p>
                </div>
                <div class="header-status" id="header-status">
                    <span class="live-indicator" id="live-indicator-2">&#9679; LIVE</span>
                    <span class="last-updated">Terakhir diperbarui: <span id="last-updated-text">--:--:--</span></span>
                </div>
            </header>

            <section class="section" id="section-dashboard">
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Responden Tangerang Selatan</div>
                        <div class="stat-value" id="stat-responden">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total PPL Mengirim</div>
                        <div class="stat-value" id="stat-ppl">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total PML</div>
                        <div class="stat-value" id="stat-pml">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total SLS Dikerjakan</div>
                        <div class="stat-value" id="stat-sls">0</div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Ringkasan Target Harian</h2>
                        <span class="panel-hint">Rincian target setiap PPL ada di dalam kartu kecamatan.</span>
                    </div>
                    <div class="target-summary-grid" id="daily-target-summary-grid"></div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Statistik 7 Kecamatan</h2>
                        <span class="panel-hint">Pilih kecamatan untuk melihat daftar PPL</span>
                    </div>
                    <div class="kecamatan-grid" id="kecamatan-cards"></div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Progres Target Per Kecamatan</h2>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-summary">
                            <thead>
                                <tr>
                                    <th>Kecamatan</th>
                                    <th class="ta-right">Hari Ini</th>
                                    <th class="ta-right">Kemarin</th>
                                    <th class="ta-right">Target</th>
                                    <th class="ta-right">Sisa</th>
                                    <th class="ta-right">Progres</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody id="kecamatan-target-table"></tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Progres Target Per Kelurahan</h2>
                        <div class="district-stepper" aria-label="Pilih kecamatan untuk progres kelurahan">
                            <button type="button" class="district-step-button" id="kelurahan-district-prev" aria-label="Kecamatan sebelumnya" title="Kecamatan sebelumnya">&#8592;</button>
                            <span class="district-step-label" id="kelurahan-district-label">Memuat kecamatan...</span>
                            <button type="button" class="district-step-button" id="kelurahan-district-next" aria-label="Kecamatan berikutnya" title="Kecamatan berikutnya">&#8594;</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-summary">
                            <thead>
                                <tr>
                                    <th>Kecamatan</th>
                                    <th>Kelurahan</th>
                                    <th class="ta-right">Hari Ini</th>
                                    <th class="ta-right">Target</th>
                                    <th class="ta-right">Sisa</th>
                                    <th class="ta-right">Progres</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody id="kelurahan-target-table"></tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Rekap per Kecamatan</h2>
                        <select class="table-sort input" data-table="statistics-kecamatan-table" data-column="1" aria-label="Urutkan rekap kecamatan">
                            <option value="">Urutkan jumlah</option>
                            <option value="asc">Kecil ke besar</option>
                            <option value="desc">Besar ke kecil</option>
                        </select>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-summary">
                            <thead>
                                <tr>
                                    <th>Kecamatan</th>
                                    <th class="ta-right">Jumlah Total Responden</th>
                                    <th class="ta-right">PPL yang Mengirim</th>
                                    <th class="ta-right">PML</th>
                                    <th class="ta-right">SLS yang Dikerjakan</th>
                                </tr>
                            </thead>
                            <tbody id="statistics-kecamatan-table"></tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Responden Rata Tengah</h2>
                        <span class="panel-hint">Rekap jenis responden per kecamatan</span>
                        <select class="table-sort input" data-table="responden-kecamatan-table" data-column="4" aria-label="Urutkan responden kecamatan">
                            <option value="">Urutkan jumlah</option>
                            <option value="asc">Kecil ke besar</option>
                            <option value="desc">Besar ke kecil</option>
                        </select>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-summary">
                            <thead>
                                <tr>
                                    <th>Kecamatan</th>
                                    <th class="ta-right">Keluarga</th>
                                    <th class="ta-right">Usaha</th>
                                    <th class="ta-right">Campuran</th>
                                    <th class="ta-right">Total Responden</th>
                                </tr>
                            </thead>
                            <tbody id="responden-kecamatan-table"></tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Grafik Progres Harian</h2>
                        <span class="panel-hint">Jumlah data masuk per hari</span>
                    </div>
                    <div class="chart-box">
                        <div class="chart-bars" id="daily-progress-chart"></div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Aktivitas Kartu Kendali PML</h2>
                        <span class="panel-hint">Data dari spreadsheet PML, terpisah dari rekap responden PPL</span>
                    </div>
                    <div class="target-summary-grid pml-activity-summary">
                        <div class="target-summary-box">
                            <span class="target-summary-label">Pengiriman hari ini</span>
                            <strong id="pml-submissions-today">0</strong>
                        </div>
                        <div class="target-summary-box">
                            <span class="target-summary-label">Total pengiriman</span>
                            <strong id="pml-submissions-total">0</strong>
                        </div>
                        <div class="target-summary-box">
                            <span class="target-summary-label">PML tercatat</span>
                            <strong id="pml-activity-total-pml">0</strong>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-pml-activity">
                            <thead>
                                <tr>
                                    <th>Tanggal Pendataan</th>
                                    <th>Nama PML</th>
                                    <th>Kecamatan</th>
                                    <th>Kelurahan</th>
                                    <th>SLS</th>
                                    <th>Jenis Responden</th>
                                    <th>Dokumentasi</th>
                                </tr>
                            </thead>
                            <tbody id="pml-activity-latest"></tbody>
                        </table>
                    </div>
                </div>

                <div class="two-col">
                    <div class="panel">
                        <div class="panel-header">
                            <h2>TOP PPL Teratas</h2>
                            <span class="panel-hint">Jumlah responden terbanyak</span>
                            <select class="table-sort input" data-table="top-ppl-table" data-column="3" aria-label="Urutkan PPL teratas">
                                <option value="">Urutkan jumlah</option>
                                <option value="asc">Kecil ke besar</option>
                                <option value="desc">Besar ke kecil</option>
                            </select>
                        </div>
                        <div class="table-wrap">
                            <table class="table table-ranking">
                                <thead>
                                    <tr>
                                        <th>Nomor</th>
                                        <th>Nama</th>
                                        <th>Kecamatan</th>
                                        <th>Jumlah Responden (Keluarga, Usaha, Campuran)</th>
                                    </tr>
                                </thead>
                                <tbody id="top-ppl-table"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="panel-header">
                            <h2>TOP PPL Terbawah</h2>
                            <span class="panel-hint">Jumlah responden paling sedikit</span>
                            <select class="table-sort input" data-table="bottom-ppl-table" data-column="3" aria-label="Urutkan PPL terbawah">
                                <option value="">Urutkan jumlah</option>
                                <option value="asc">Kecil ke besar</option>
                                <option value="desc">Besar ke kecil</option>
                            </select>
                        </div>
                        <div class="table-wrap">
                            <table class="table table-ranking">
                                <thead>
                                    <tr>
                                        <th>Nomor</th>
                                        <th>Nama</th>
                                        <th>Kecamatan</th>
                                        <th>Jumlah Responden (Keluarga, Usaha, Campuran)</th>
                                    </tr>
                                </thead>
                                <tbody id="bottom-ppl-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Data Terbaru</h2>
                        <span class="panel-hint">Lima record terakhir yang masuk</span>
                        <select class="table-sort input" data-table="latest-table" data-column="2" aria-label="Urutkan data terbaru">
                            <option value="">Urutkan tanggal</option>
                            <option value="asc">Terlama ke terbaru</option>
                            <option value="desc">Terbaru ke terlama</option>
                        </select>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-latest">
                            <thead>
                                <tr>
                                    <th>Kecamatan</th>
                                    <th>Responden</th>
                                    <th>PPL</th>
                                    <th>PML</th>
                                    <th>Tanggal Pendataan</th>
                                    <th>Waktu Masuk Spreadsheet</th>
                                </tr>
                            </thead>
                            <tbody id="latest-table"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="section is-hidden" id="section-data">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Data Kartu Kendali</h2>
                        <div class="search-box">
                            <input type="search" id="filter-search" class="input" placeholder="Cari responden, PPL, PML, kecamatan...">
                        </div>
                    </div>
                    <form class="filter-grid" id="filter-form">
                        <label class="field">
                            <span>Kecamatan</span>
                            <select class="input" id="filter-kecamatan">
                                <option value="">Semua Kecamatan</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Rentang Tanggal</span>
                            <div class="range-inline">
                                <input type="date" class="input" id="filter-date-from" aria-label="Tanggal mulai">
                                <span class="range-separator">s.d.</span>
                                <input type="date" class="input" id="filter-date-to" aria-label="Tanggal akhir">
                            </div>
                        </label>
                        <div class="field">
                            <span>Shortcut</span>
                            <div class="quick-date-actions">
                                <button type="button" class="btn btn-outline btn-small" data-range="today">Hari ini</button>
                                <button type="button" class="btn btn-outline btn-small" data-range="7d">7 hari</button>
                                <button type="button" class="btn btn-outline btn-small" data-range="30d">30 hari</button>
                                <button type="button" class="btn btn-outline btn-small" data-range="month">Bulan ini</button>
                            </div>
                        </div>
                        <label class="field">
                            <span>Kelurahan</span>
                            <select class="input" id="filter-kelurahan">
                                <option value="">Semua Kelurahan</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>SLS</span>
                            <select class="input" id="filter-sls">
                                <option value="">Semua SLS</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>PPL</span>
                            <select class="input" id="filter-ppl">
                                <option value="">Semua PPL</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>PML</span>
                            <select class="input" id="filter-pml">
                                <option value="">Semua PML</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Jenis Responden</span>
                            <select class="input" id="filter-jenis">
                                <option value="">Semua Jenis</option>
                            </select>
                        </label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                            <button type="button" class="btn btn-outline" id="reset-filter">Reset</button>
                            <button type="button" class="btn btn-outline" id="export-csv">Export CSV</button>
                        </div>
                    </form>
                </div>
                <div class="panel summary-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Ringkasan Jumlah Responden</h2>
                            <span class="panel-hint">Jumlah mengikuti filter kecamatan yang diterapkan</span>
                        </div>
                    </div>
                    <div class="stat-grid data-summary">
                        <div class="stat-card summary-card summary-card-total">
                            <div class="stat-label">Total Responden Tangerang Selatan</div>
                            <div class="stat-value" id="data-summary-total">0</div>
                        </div>
                        <div class="stat-card summary-card summary-card-filtered">
                            <div class="stat-label">Total Responden Sesuai Kecamatan</div>
                            <div class="stat-value" id="data-summary-filtered">0</div>
                        </div>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-header">
                        <h2>Deteksi Data Tidak Lengkap</h2>
                        <span class="panel-hint">Record yang perlu segera diperbaiki</span>
                    </div>
                    <div class="alert-grid">
                        <div class="alert-card alert-warning">
                            <div class="alert-label">Perlu Diperbaiki</div>
                            <div class="alert-value" id="data-health-count">0</div>
                        </div>
                    </div>
                    <ul class="issue-list" id="data-health-list"></ul>
                </div>
                <div class="panel">
                    <div class="panel-header">
                        <h2>Daftar Data</h2>
                        <select class="table-sort input" data-table="data-table-body" data-column="2" data-type="date" aria-label="Urutkan daftar data">
                            <option value="">Urutkan tanggal</option>
                            <option value="asc">Terlama ke terbaru</option>
                            <option value="desc">Terbaru ke terlama</option>
                        </select>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-data">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kecamatan</th>
                                    <th>Tanggal Pendataan</th>
                                    <th>Waktu Masuk Spreadsheet</th>
                                    <th>PPL</th>
                                    <th>PML</th>
                                    <th>Kelurahan</th>
                                    <th>SLS</th>
                                    <th>Nama Responden</th>
                                    <th>Jenis Responden</th>
                                    <th>Dokumentasi</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody id="data-table-body"></tbody>
                        </table>
                        <div class="table-empty is-hidden" id="data-empty">Tidak ada data.</div>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>

            </section>

            <section class="section is-hidden" id="section-report">
                <div class="panel report-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Buat Laporan Perjalanan Dinas</h2>
                            <span class="panel-hint">Isi informasi laporan, lalu unduh dalam format Word.</span>
                        </div>
                        <button type="button" class="btn btn-primary" id="download-report">Unduh Laporan Word</button>
                    </div>
                    <div class="report-form-grid">
                        <label class="field">
                            <span>Judul Pelaksanaan</span>
                            <input class="input" id="report-title" value="Melaksanakan Revisit dan Penyisiran Hasil Pendataan Sensus Ekonomi 2026">
                        </label>
                        <label class="field">
                            <span>Nama Pelaksana Perjadin</span>
                            <input class="input" id="report-executor" placeholder="Nama pelaksana">
                        </label>
                        <label class="field">
                            <span>Tanggal Perjadin</span>
                            <input type="date" class="input" id="report-date">
                        </label>
                        <label class="field">
                            <span>Nomor dan Tanggal Surat Tugas</span>
                            <input class="input" id="report-letter" placeholder="Nomor surat dan tanggal">
                        </label>
                        <label class="field">
                            <span>RT</span>
                            <input class="input" id="report-rt" placeholder="Nomor RT">
                        </label>
                        <label class="field">
                            <span>RW</span>
                            <input class="input" id="report-rw" placeholder="Nomor RW">
                        </label>
                        <label class="field">
                            <span>Jumlah keluarga menurut pengurus SLS</span>
                            <input type="number" min="0" class="input" id="report-family-count" placeholder="0">
                        </label>
                        <label class="field">
                            <span>Jumlah usaha baru</span>
                            <input type="number" min="0" class="input" id="report-new-business" placeholder="0">
                        </label>
                        <label class="field">
                            <span>Jumlah keluarga baru</span>
                            <input type="number" min="0" class="input" id="report-new-family" placeholder="0">
                        </label>
                        <label class="field">
                            <span>Nama penandatangan</span>
                            <input class="input" id="report-signatory" value="Agung Erianto Juliandono">
                        </label>
                    </div>
                </div>
            </section>

            <section class="section is-hidden" id="section-api">
                <div class="panel">
                    <div class="panel-header"><h2>Pengaturan API</h2></div>
                    <p class="muted">API penerimaan data dari Google Apps Script dilindungi token Bearer.</p>
                    <div class="api-info">
                        <div class="api-row">
                            <span class="api-key">Endpoint (POST)</span>
                            <code class="api-url">/api/kartu-kendali</code>
                        </div>
                        <div class="api-row">
                            <span class="api-key">Endpoint (GET)</span>
                            <code class="api-url">/api/kartu-kendali</code>
                        </div>
                        <div class="api-row">
                            <span class="api-key">Statistik</span>
                            <code class="api-url">/api/kartu-kendali/statistics</code>
                        </div>
                    </div>
                    <h3>Contoh pengiriman data</h3>
                    <pre class="code-block" id="api-example"></pre>
                </div>
            </section>

            <section class="section is-hidden" id="section-fasih">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Bahan Realokasi Cek FASIH</h2>
                            <span class="panel-hint">Validasi dan pengecekan data kartu kendali untuk keperluan realokasi.</span>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline" id="fasih-export-btn">Export Hasil Pengecekan</button>
                        </div>
                    </div>
                    <p class="muted" style="margin-bottom:16px;">Menampilkan seluruh data dari sumber Google Sheet yang telah disinkronkan ke database, tanpa mengimpor CSV.</p>

                    <div class="stat-grid" id="fasih-summary" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:18px;">
                        <div class="stat-card" style="border-top:3px solid var(--primary);">
                            <div class="stat-label">Total Data</div>
                            <div class="stat-value" id="fasih-total">0</div>
                        </div>
                        <div class="stat-card" style="border-top:3px solid var(--green);">
                            <div class="stat-label">Sudah Dipindah</div>
                            <div class="stat-value" id="fasih-sudah-dicek">0</div>
                        </div>
                        <div class="stat-card" style="border-top:3px solid var(--orange);">
                            <div class="stat-label">Sudah Sesuai SLS</div>
                            <div class="stat-value" id="fasih-sesuai">0</div>
                        </div>
                        <div class="stat-card" style="border-top:3px solid var(--muted);">
                            <div class="stat-label">Belum Dipindah</div>
                            <div class="stat-value" id="fasih-belum-dicek">0</div>
                        </div>
                    </div>

                    <form class="filter-grid fasih-filter-grid" id="fasih-filter-form">
                        <label class="field">
                            <span>Kecamatan</span>
                            <select class="input" id="fasih-filter-kecamatan">
                                <option value="">Semua Kecamatan</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Kelurahan</span>
                            <select class="input" id="fasih-filter-kelurahan">
                                <option value="">Semua Kelurahan</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>SLS</span>
                            <select class="input" id="fasih-filter-sls">
                                <option value="">Semua SLS</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>PPL</span>
                            <select class="input" id="fasih-filter-ppl">
                                <option value="">Semua PPL</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>PML</span>
                            <select class="input" id="fasih-filter-pml">
                                <option value="">Semua PML</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Status Pemindahan</span>
                            <select class="input" id="fasih-filter-status-pemindahan">
                                <option value="">Semua Status</option>
                                <option value="sudah_dipindah">Sudah Dipindah</option>
                                <option value="belum_dipindah">Belum Dipindah</option>
                                <option value="sudah_sesuai_sls">Sudah Sesuai SLS</option>
                            </select>
                        </label>
                        <div class="field">
                            <span>Pencarian</span>
                            <input type="search" class="input" id="fasih-filter-search" placeholder="Pencarian nama responden / SLS...">
                        </div>
                        <div class="field">
                            <span>&nbsp;</span>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                                <button type="button" class="btn btn-outline" id="fasih-reset-filter">Reset</button>
                            </div>
                        </div>
                    </form>

                    <div class="fasih-table-heading">
                        <h2>Daftar Data Pemindahan</h2>
                        <span class="panel-hint">Status dapat diperbarui langsung dari tabel.</span>
                    </div>
                    <div class="table-wrap">
                        <table class="table table-data" style="min-width:1200px;">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kecamatan</th>
                                    <th>Kelurahan</th>
                                    <th>SLS</th>
                                    <th>Nama Responden</th>
                                    <th>PPL</th>
                                    <th>PML</th>
                                    <th>Status Pemindahan</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody id="fasih-table-body"></tbody>
                        </table>
                        <div class="table-empty is-hidden" id="fasih-empty">Tidak ada data master. Silakan import data terlebih dahulu.</div>
                    </div>
                    <div class="pagination" id="fasih-pagination"></div>
                </div>

                <details class="panel fasih-progress-panel">
                    <summary class="fasih-progress-summary">
                        <span>
                            <strong>Progress Pemindahan per Kelurahan</strong>
                            <small id="fasih-progress-overview">Memuat ringkasan...</small>
                        </span>
                        <span class="fasih-progress-toggle" aria-hidden="true">+</span>
                    </summary>
                    <div class="fasih-progress-content" id="fasih-kelurahan-progress"></div>
                </details>
            </section>

            <footer class="dashboard-footer">
                <img class="dashboard-footer-watermark" src="/images/bps-logo.svg" alt="" aria-hidden="true">
                <div class="dashboard-footer-content">
                    <strong>Badan Pusat Statistik</strong>
                    <span>Kota Tangerang Selatan</span>
                    <small>Dashboard Penyisiran Sensus Ekonomi 2026</small>
                </div>
            </footer>

            <div class="toast-container" id="fasih-toast-container"></div>
        </main>
    </div>

    <script src="/js/dashboard.js"></script>
</body>
</html>
