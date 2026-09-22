<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard PML/PPL</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <span class="brand-dot"></span>
                <div>
                    <div class="brand-title">Dashboard</div>
                    <div class="brand-sub">Kartu Kendali PML/PPL</div>
                </div>
            </div>
            <nav class="nav">
                <button class="nav-item is-active" data-section="dashboard">Dashboard</button>
                <button class="nav-item" data-section="data">Data Kartu Kendali</button>
                <button class="nav-item" data-section="api">Pengaturan API</button>
            </nav>
            <div class="sidebar-footer">
                <span id="live-indicator" class="live-indicator">&#9679; LIVE</span>
                <span id="last-updated" class="last-updated">--:--:--</span>
            </div>
        </aside>

        <main class="main">
            <header class="header">
                <button class="menu-toggle" id="menu-toggle" aria-label="Menu">&#9776;</button>
                <h1 id="page-title">Dashboard</h1>
                <div class="header-status" id="header-status">
                    <span class="live-indicator" id="live-indicator-2">&#9679; LIVE</span>
                    <span class="last-updated">Terakhir diperbarui: <span id="last-updated-text">--:--:--</span></span>
                </div>
            </header>

            <!-- Section: Dashboard -->
            <section class="section" id="section-dashboard">
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Responden</div>
                        <div class="stat-value" id="stat-responden">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total PPL</div>
                        <div class="stat-value" id="stat-ppl">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total PML</div>
                        <div class="stat-value" id="stat-pml">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total SLS</div>
                        <div class="stat-value" id="stat-sls">0</div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Statistik 7 Wilayah</h2>
                        <span class="panel-hint">Pemantauan progres pendataan</span>
                    </div>
                    <div class="wilayah-grid" id="wilayah-cards"></div>
                </div>

                <div class="two-col">
                    <div class="panel">
                        <div class="panel-header">
                            <h2>Rekap per Wilayah</h2>
                            <span class="panel-hint">Total responden setiap wilayah</span>
                        </div>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Wilayah</th>
                                        <th class="ta-right">Data</th>
                                        <th class="ta-right">PPL</th>
                                        <th class="ta-right">PML</th>
                                        <th class="ta-right">SLS</th>
                                    </tr>
                                </thead>
                                <tbody id="statistics-wilayah-table"></tbody>
                            </table>
                        </div>
                        <div class="panel-header">
                            <h2>Rekap Jenis Responden</h2>
                            <span class="panel-hint">Distribusi jenis responden</span>
                        </div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jenis Responden</th>
                                    <th class="ta-right">Responden</th>
                                </tr>
                            </thead>
                            <tbody id="jenis-responden-table"></tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Data Terbaru</h2>
                        <span class="panel-hint">Record terakhir yang masuk</span>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Wilayah</th>
                                <th>Responden</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody id="latest-table"></tbody>
                    </table>
                </div>
            </section>

            <!-- Section: Data -->
            <section class="section is-hidden" id="section-data">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Data Kartu Kendali</h2>
                        <div class="search-box">
                            <input type="search" id="filter-search" class="input" placeholder="Cari responden, PPL, PML, kelurahan...">
                        </div>
                    </div>
                    <form class="filter-grid" id="filter-form">
                        <label class="field">
                            <span>Wilayah</span>
                            <select class="input" id="filter-wilayah">
                                <option value="">Semua Wilayah</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Tanggal</span>
                            <input type="date" class="input" id="filter-tanggal">
                        </label>
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
                        </div>
                    </form>
                </div>
                <div class="panel summary-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Ringkasan Jumlah Data</h2>
                            <span class="panel-hint">Jumlah diperbarui mengikuti filter yang diterapkan</span>
                        </div>
                    </div>
                    <div class="stat-grid data-summary">
                        <div class="stat-card summary-card summary-card-total">
                            <div class="stat-label">Total seluruh data</div>
                            <div class="stat-value" id="data-summary-total">0</div>
                        </div>
                        <div class="stat-card summary-card summary-card-filtered">
                            <div class="stat-label">Sesuai filter</div>
                            <div class="stat-value" id="data-summary-filtered">0</div>
                        </div>
                        <div class="stat-card summary-card summary-card-shown">
                            <div class="stat-label">Ditampilkan</div>
                            <div class="stat-value" id="data-summary-shown">0</div>
                        </div>
                        <div class="stat-card summary-card summary-card-page">
                            <div class="stat-label">Halaman aktif</div>
                            <div class="stat-value" id="data-summary-page">1/1</div>
                        </div>
                    </div>
                </div>
                <div class="panel">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Wilayah</th>
                                    <th>Tanggal</th>
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


            <!-- Section: Api -->
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
        </main>
    </div>

    <script src="/js/dashboard.js"></script>
</body>
</html>