(() => {
    'use strict';

    const POLL_INTERVAL_MS = 5000;
    const WILAYAH = ['SETU', 'SERPONG', 'PAMULANG', 'CIPUTAT', 'CIPUTAT TIMUR', 'PONDOK AREN', 'SERPONG UTARA'];

    const state = {
        stats: null,
        data: null,
        filters: {
            search: '',
            wilayah: '',
            tanggal: '',
            kelurahan: '',
            sls: '',
            ppl: '',
            pml: '',
            jenis: '',
        },
        page: 1,
        perPage: 15,
        lastSignature: '',
        connected: true,
        lastUpdated: null,
    };

    const elements = {
        sections: {},
        wilayahCards: document.getElementById('wilayah-cards'),
        wilayahTable: document.getElementById('statistics-wilayah-table'),
        jenisTable: document.getElementById('jenis-responden-table'),
        latestTable: document.getElementById('latest-table'),
        dataTable: document.getElementById('data-table-body'),
        dataEmpty: document.getElementById('data-empty'),
        pagination: document.getElementById('pagination'),
        filterWilayah: document.getElementById('filter-wilayah'),
        filterJenis: document.getElementById('filter-jenis'),
        filterKelurahan: document.getElementById('filter-kelurahan'),
        filterSls: document.getElementById('filter-sls'),
        filterPpl: document.getElementById('filter-ppl'),
        filterPml: document.getElementById('filter-pml'),
        statResponden: document.getElementById('stat-responden'),
        statPpl: document.getElementById('stat-ppl'),
        statPml: document.getElementById('stat-pml'),
        statSls: document.getElementById('stat-sls'),
        dataSummaryTotal: document.getElementById('data-summary-total'),
        dataSummaryFiltered: document.getElementById('data-summary-filtered'),
        dataSummaryShown: document.getElementById('data-summary-shown'),
        dataSummaryPage: document.getElementById('data-summary-page'),
        apiExample: document.getElementById('api-example'),
        liveIndicator: document.getElementById('live-indicator'),
        liveIndicator2: document.getElementById('live-indicator-2'),
        lastUpdated: document.getElementById('last-updated'),
        lastUpdatedText: document.getElementById('last-updated-text'),
    };

    /**
     * Data source abstraction.
     * Saat ini menggunakan polling Fetch API. Untuk bermigrasi ke Laravel
     * Reverb/WebSocket nanti, cukup ganti implementasi kelas ini.
     */
    const DataSource = {
        async fetchJson(url, options) {
            const response = await fetch(url, options);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        },

        async getStatistics() {
            return this.fetchJson('/api/kartu-kendali/statistics');
        },

        async getData(params) {
            const qs = new URLSearchParams(params).toString();
            return this.fetchJson(`/api/kartu-kendali?${qs}`);
        },
    };

    function isoTime(iso) {
        if (!iso) return '--:--:--';
        const d = new Date(iso);
        return d.toLocaleTimeString('id-ID');
    }

    function formatDate(value) {
        if (!value) return '—';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function docLink(value) {
        if (!value) return '—';
        const text = String(value).trim();

        try {
            const url = new URL(text, window.location.origin);

            if (['http:', 'https:'].includes(url.protocol)) {
                return `<a class="doc-link" href="${esc(url.href)}" target="_blank" rel="noopener">Buka</a>`;
            }
        } catch {
            // Fall through to escaped text for malformed URLs.
        }

        return esc(text);
    }

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function badge(value) {
        return `<span class="badge">${esc(value)}</span>`;
    }

    function setLiveStatus(online) {
        state.connected = online;
        const indicator = online ? '&#9679; LIVE' : '&#9679; Connection lost';
        const cls = online ? '' : 'is-offline';
        [elements.liveIndicator, elements.liveIndicator2].forEach((el) => {
            if (!el) return;
            el.innerHTML = indicator;
            el.classList.toggle('is-offline', !online);
        });
    }

    function updateTimestamp() {
        const text = state.lastUpdated ? isoTime(state.lastUpdated) : '--:--:--';
        elements.lastUpdated.textContent = text;
        elements.lastUpdatedText.textContent = text;
    }

    function renderStatistics(stats) {
        elements.statResponden.textContent = stats.total_responden ?? 0;
        elements.statPpl.textContent = stats.total_ppl ?? 0;
        elements.statPml.textContent = stats.total_pml ?? 0;
        elements.statSls.textContent = stats.total_sls ?? 0;

        const perWilayah = stats.per_wilayah ?? {};
        const renderedWilayah = WILAYAH.map((name) => {
            const count = perWilayah[name] ?? 0;
            return `<div class="wilayah-card">
                <div class="w-name">${esc(name)}</div>
                <div class="w-count">${count}</div>
            </div>`;
        }).join('');
        if (elements.wilayahCards) elements.wilayahCards.innerHTML = renderedWilayah;
        const perJenis = stats.per_jenis_responden ?? {};
        const jenisEntries = Object.keys(perJenis).length
            ? Object.entries(perJenis)
            : [['—', 0]];
        const jenisRows = jenisEntries
            .map(([name, count]) => `<tr><td>${esc(name)}</td><td class="ta-right">${count}</td></tr>`)
            .join('');
        if (elements.jenisTable) elements.jenisTable.innerHTML = jenisRows;

        if (elements.wilayahTable) {
            const perWilayahDetail = stats.per_wilayah_detail ?? {};
            elements.wilayahTable.innerHTML = WILAYAH
                .map((name) => {
                    const detail = perWilayahDetail[name] ?? {};

                    return `<tr>
                        <td>${esc(name)}</td>
                        <td class="ta-right">${detail.total_responden ?? 0}</td>
                        <td class="ta-right">${detail.total_ppl ?? 0}</td>
                        <td class="ta-right">${detail.total_pml ?? 0}</td>
                        <td class="ta-right">${detail.total_sls ?? 0}</td>
                    </tr>`;
                })
                .join('');
        }

        const latest = stats.latest;
        const latestRows = latest
            ? `<tr><td>${badge(latest.wilayah)}</td><td>${esc(latest.nama_responden)}</td><td>${esc(latest.tanggal_pendataan ?? '—')}</td></tr>`
            : `<tr><td colspan="3" class="muted">Belum ada data.</td></tr>`;
        if (elements.latestTable) elements.latestTable.innerHTML = latestRows;

        const filterOptions = stats.filter_options ?? {};
        fillFilterSelect(elements.filterWilayah, WILAYAH, state.filters.wilayah);
        fillFilterSelect(elements.filterJenis, Object.keys(perJenis), state.filters.jenis);
        fillFilterSelect(elements.filterKelurahan, filterOptions.kelurahan ?? [], state.filters.kelurahan);
        fillFilterSelect(elements.filterSls, filterOptions.sls ?? [], state.filters.sls);
        fillFilterSelect(elements.filterPpl, filterOptions.ppl ?? [], state.filters.ppl);
        fillFilterSelect(elements.filterPml, filterOptions.pml ?? [], state.filters.pml);
    }

    function fillFilterSelect(select, values, current) {
        if (!select) return;
        const normalizedValues = values.map((value) => String(value));
        const existingValues = Array.from(select.options)
            .filter((option) => option.value !== '')
            .map((option) => option.value);
        const optionsMatch = existingValues.length === normalizedValues.length
            && existingValues.every((value, index) => value === normalizedValues[index]);
        const currentValue = current || select.value;

        if (!optionsMatch) {
            const firstLabel = select.options[0] ? select.options[0].text : 'Semua';
            select.innerHTML = `<option value="">${esc(firstLabel)}</option>`
                + normalizedValues.map((value) => `<option value="${esc(value)}">${esc(value)}</option>`).join('');
        }

        select.value = normalizedValues.includes(currentValue) ? currentValue : '';
    }


    function currentParams() {
        return {
            search: state.filters.search,
            wilayah: state.filters.wilayah,
            kelurahan: state.filters.kelurahan,
            sls: state.filters.sls,
            ppl: state.filters.ppl,
            pml: state.filters.pml,
            jenis_responden: state.filters.jenis,
            tanggal: state.filters.tanggal,
            page: state.page,
            per_page: state.perPage,
        };
    }

    function renderData(data) {
        const rows = data.data ?? [];
        const currentPage = data.current_page ?? 1;
        const lastPage = data.last_page ?? 1;
        const totalData = state.stats?.total_responden ?? data.total ?? 0;

        elements.dataSummaryTotal.textContent = Number(totalData).toLocaleString('id-ID');
        elements.dataSummaryFiltered.textContent = Number(data.total ?? 0).toLocaleString('id-ID');
        elements.dataSummaryShown.textContent = Number(rows.length).toLocaleString('id-ID');
        elements.dataSummaryPage.textContent = `${currentPage}/${lastPage}`;
        if (rows.length === 0) {
            elements.dataTable.innerHTML = '';
            elements.dataEmpty.classList.remove('is-hidden');
        } else {
            elements.dataEmpty.classList.add('is-hidden');
            const from = ((data.current_page ?? 1) - 1) * (data.per_page ?? rows.length);
            elements.dataTable.innerHTML = rows.map((row, i) => `
                <tr>
                    <td>${from + i + 1}</td>
                    <td>${badge(row.wilayah)}</td>
                    <td>${esc(row.tanggal_pendataan ?? '—')}</td>
                    <td>${esc(row.nama_ppl)}</td>
                    <td>${esc(row.nama_pml)}</td>
                    <td>${esc(row.kelurahan ?? '—')}</td>
                    <td>${esc(row.sls ?? '—')}</td>
                    <td>${esc(row.nama_responden)}</td>
                    <td>${esc(row.jenis_responden ?? '—')}</td>
                    <td>${docLink(row.dokumentasi)}</td>
                    <td>${esc(row.keterangan ?? '—')}</td>
                </tr>`).join('');
        }
        renderPagination(data);
    }

    function renderPagination(data) {
        const lastPage = data.last_page ?? 1;
        const current = data.current_page ?? 1;
        if (lastPage <= 1) {
            elements.pagination.innerHTML = '';
            return;
        }
        const buttons = [];
        buttons.push(`<button class="page-btn" data-page="${current - 1}" ${current === 1 ? 'disabled' : ''}>&#9664;</button>`);
        for (let p = 1; p <= lastPage; p++) {
            if (p === 1 || p === lastPage || Math.abs(p - current) <= 1) {
                buttons.push(`<button class="page-btn ${p === current ? 'is-active' : ''}" data-page="${p}">${p}</button>`);
            } else if (Math.abs(p - current) === 2) {
                buttons.push('<span class="muted">…</span>');
            }
        }
        buttons.push(`<button class="page-btn" data-page="${current + 1}" ${current === lastPage ? 'disabled' : ''}>&#9654;</button>`);
        elements.pagination.innerHTML = buttons.join('');
    }

    function dataSignature(data) {
        if (!data || !data.data || data.data.length === 0) return 'empty';
        const first = data.data[0];
        const last = data.data[data.data.length - 1];
        return `${data.current_page}:${data.last_page}:${first.id}-${last.id}`;
    }

    async function refreshAll() {
        try {
            const [stats, data] = await Promise.all([
                DataSource.getStatistics(),
                DataSource.getData(currentParams()),
            ]);

            state.stats = stats;
            state.data = data;
            state.lastUpdated = stats.updated_at || new Date().toISOString();

            renderStatistics(stats);

            const signature = dataSignature(data);
            if (signature !== state.lastSignature) {
                renderData(data);
                state.lastSignature = signature;
            }
            setLiveStatus(true);
            updateTimestamp();
        } catch (error) {
            console.error('Dashboard refresh gagal:', error);
            setLiveStatus(false);
            updateTimestamp();
        }
    }

    function applyFilters() {
        state.filters.search = document.getElementById('filter-search').value.trim();
        state.filters.wilayah = document.getElementById('filter-wilayah').value;
        state.filters.tanggal = document.getElementById('filter-tanggal').value;
        state.filters.kelurahan = document.getElementById('filter-kelurahan').value.trim();
        state.filters.sls = document.getElementById('filter-sls').value.trim();
        state.filters.ppl = document.getElementById('filter-ppl').value.trim();
        state.filters.pml = document.getElementById('filter-pml').value.trim();
        state.filters.jenis = document.getElementById('filter-jenis').value;
        state.page = 1;
        state.lastSignature = '';
        refreshAll();
    }

    function resetFilters() {
        document.getElementById('filter-form').reset();
        document.getElementById('filter-search').value = '';
        applyFilters();
    }

    function navigate(section) {
        const titles = { dashboard: 'Dashboard', data: 'Data Kartu Kendali', api: 'Pengaturan API' };
        ['dashboard', 'data', 'api'].forEach((key) => {
            const sectionEl = document.getElementById(`section-${key}`);
            if (sectionEl) sectionEl.classList.toggle('is-hidden', key !== section);
        });
        document.querySelectorAll('.nav-item').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.section === section);
        });
        document.getElementById('page-title').textContent = titles[section];
        document.getElementById('sidebar').classList.remove('is-open');
    }

    function init() {
        setLiveStatus(true);
        updateTimestamp();

        document.getElementById('filter-form').addEventListener('submit', (e) => {
            e.preventDefault();
            applyFilters();
        });
        document.getElementById('reset-filter').addEventListener('click', resetFilters);

        document.getElementById('filter-search').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });

        elements.pagination.addEventListener('click', (e) => {
            const btn = e.target.closest('.page-btn');
            if (!btn || btn.disabled) return;
            state.page = Number(btn.dataset.page);
            state.lastSignature = '';
            refreshAll();
        });

        document.querySelectorAll('.nav-item').forEach((btn) => {
            btn.addEventListener('click', () => navigate(btn.dataset.section));
        });

        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('is-open');
        });

        const baseUrl = `${window.location.origin}/api/kartu-kendali`;
        elements.apiExample.textContent = [
            `curl -X POST ${baseUrl} \\`,
            `  -H "Authorization: Bearer KARTU_KENDALI_API_TOKEN" \\`,
            `  -H "Content-Type: application/json" \\`,
            `  -d '{`,
            `    "wilayah": "SETU",`,
            `    "tanggal_pendataan": "2026-09-22",`,
            `    "nama_ppl": "Nama PPL",`,
            `    "nama_pml": "Nama PML",`,
            `    "kelurahan": "Setu",`,
            `    "sls": "001",`,
            `    "nama_responden": "Nama Responden",`,
            `    "jenis_responden": "Rumah Tangga Biasa",`,
            `    "dokumentasi": "https://drive.google.com/...",`,
            `    "keterangan": "Catatan",`,
            `    "source_sheet": "SETU",`,
            `    "external_id": "abc-123"`,
            `  }'`,
        ].join('\n');

        refreshAll();
        setInterval(refreshAll, POLL_INTERVAL_MS);
    }

    document.addEventListener('DOMContentLoaded', init);
})();