(() => {
    'use strict';

    const POLL_INTERVAL_MS = 60000;
    const KECAMATAN = ['SETU', 'SERPONG', 'PAMULANG', 'CIPUTAT', 'CIPUTAT TIMUR', 'PONDOK AREN', 'SERPONG UTARA'];

    const state = {
        stats: null,
        data: null,
        filters: {
            search: '',
            kecamatan: '',
            tanggal: '',
            dateFrom: '',
            dateTo: '',
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
        expandedKecamatan: new Set(),
        expandedPplActivity: new Set(),
        loadingPplActivity: new Set(),
        pplActivityCache: new Map(),
        kelurahanProgress: [],
        kelurahanDistrictIndex: 0,
    };

    const elements = {
        kecamatanCards: document.getElementById('kecamatan-cards'),
        dailyTargetSummaryGrid: document.getElementById('daily-target-summary-grid'),
        kecamatanTargetTable: document.getElementById('kecamatan-target-table'),
        kelurahanTargetTable: document.getElementById('kelurahan-target-table'),
        kelurahanDistrictLabel: document.getElementById('kelurahan-district-label'),
        kelurahanDistrictPrev: document.getElementById('kelurahan-district-prev'),
        kelurahanDistrictNext: document.getElementById('kelurahan-district-next'),
        kecamatanTable: document.getElementById('statistics-kecamatan-table'),
        respondenKecamatanTable: document.getElementById('responden-kecamatan-table'),
        topPplTable: document.getElementById('top-ppl-table'),
        bottomPplTable: document.getElementById('bottom-ppl-table'),
        latestTable: document.getElementById('latest-table'),
        pmlSubmissionsToday: document.getElementById('pml-submissions-today'),
        pmlSubmissionsTotal: document.getElementById('pml-submissions-total'),
        pmlActivityTotalPml: document.getElementById('pml-activity-total-pml'),
        pmlActivityLatest: document.getElementById('pml-activity-latest'),
        dailyProgressChart: document.getElementById('daily-progress-chart'),
        dataTable: document.getElementById('data-table-body'),
        dataEmpty: document.getElementById('data-empty'),
        pagination: document.getElementById('pagination'),
        filterKecamatan: document.getElementById('filter-kecamatan'),
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
        dataHealthCount: document.getElementById('data-health-count'),
        dataHealthList: document.getElementById('data-health-list'),
        apiExample: document.getElementById('api-example'),
        downloadReport: document.getElementById('download-report'),
        exportCsv: document.getElementById('export-csv'),
        liveIndicator: document.getElementById('live-indicator'),
        liveIndicator2: document.getElementById('live-indicator-2'),
        lastUpdated: document.getElementById('last-updated'),
        lastUpdatedText: document.getElementById('last-updated-text'),
    };

    const DataSource = {
        async fetchJson(url, options) {
            const response = await fetch(url, options);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        },

        async getStatistics(kecamatan) {
            const params = kecamatan ? `?kecamatan=${encodeURIComponent(kecamatan)}` : '';
            return this.fetchJson(`/api/kartu-kendali/statistics${params}`);
        },

        async getPplActivity(kecamatan, namaPpl) {
            const params = new URLSearchParams({ kecamatan, nama_ppl: namaPpl });
            return this.fetchJson(`/api/kartu-kendali/ppl-activity?${params.toString()}`);
        },

        async getData(params) {
            const qs = new URLSearchParams(params).toString();
            return this.fetchJson(`/api/kartu-kendali?${qs}`);
        },
    };

    function parseDate(value) {
        if (!value) return null;
        const text = String(value).trim();
        const slashDate = text.match(/^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/);
        if (slashDate) {
            const [, first, second, year, hour = '0', minute = '0', secondPart = '0'] = slashDate;
            const firstNumber = Number(first);
            const secondNumber = Number(second);
            const month = secondNumber > 12 ? firstNumber : secondNumber;
            const day = secondNumber > 12 ? secondNumber : firstNumber;
            return new Date(Number(year), month - 1, day, Number(hour), Number(minute), Number(secondPart));
        }
        const date = new Date(text);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function formatDate(value) {
        const date = parseDate(value);
        if (!date) return value ? esc(value) : '—';
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatDateTime(value) {
        const date = parseDate(value);
        if (!date) return value ? esc(value) : '—';
        return date.toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    }

    function isoTime(iso) {
        const date = parseDate(iso);
        return date ? date.toLocaleTimeString('id-ID') : '--:--:--';
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

    function renderPmlDocumentation(value) {
        const links = String(value ?? '')
            .split(/\r?\n|;\s*|,\s*(?=https?:\/\/)/i)
            .map((item) => item.trim())
            .filter(Boolean);

        if (!links.length) return '—';

        return links.map((link, index) => {
            try {
                const url = new URL(link, window.location.origin);
                if (['http:', 'https:'].includes(url.protocol)) {
                    return `<a class="doc-link" href="${esc(url.href)}" target="_blank" rel="noopener">Foto ${index + 1}</a>`;
                }
            } catch {
                return esc(link);
            }

            return esc(link);
        }).join(' ');
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
        [elements.liveIndicator, elements.liveIndicator2].forEach((element) => {
            if (!element) return;
            element.innerHTML = indicator;
            element.classList.toggle('is-offline', !online);
        });
    }

    function updateTimestamp() {
        const text = state.lastUpdated ? isoTime(state.lastUpdated) : '--:--:--';
        elements.lastUpdated.textContent = text;
        elements.lastUpdatedText.textContent = text;
    }

    function renderPplRows(rows) {
        if (!rows || rows.length === 0) {
            return '<tr><td colspan="4" class="muted">Belum ada data.</td></tr>';
        }

        return rows.map((row, index) => {
            const total = Number(row.total_responden ?? 0);
            const keluarga = Number(row.keluarga ?? 0);
            const usaha = Number(row.usaha ?? 0);
            const campuran = Number(row.campuran ?? 0);

            return `<tr>
                <td><span class="rank-pill">${index + 1}</span></td>
                <td><div class="person-name">${esc(row.nama)}</div></td>
                <td>${badge(row.kecamatan)}</td>
                <td>
                    <div class="metric-cell">${total.toLocaleString('id-ID')}</div>
                    <div class="metric-sub">${keluarga} K, ${usaha} U, ${campuran} C</div>
                </td>
            </tr>`;
        }).join('');
    }

    function renderDailyTargetProgress(stats) {
        const targetPerPpl = Number(stats.daily_target_per_ppl ?? 10);
        const pplProgress = Array.isArray(stats.per_ppl_daily_progress) ? stats.per_ppl_daily_progress : [];
        const kecamatanProgress = stats.per_kecamatan_target_progress ?? stats.per_kecamatan_daily_progress ?? {};
        const snapshot = stats.daily_snapshot ?? {};
        const todayTotal = Number(snapshot.today_total ?? 0);
        const yesterdayTotal = Number(snapshot.yesterday_total ?? 0);
        const sevenDayTotal = Number(snapshot.last_7_days_total ?? 0);
        const averageDaily = Number(snapshot.average_per_day_7d ?? 0);
        const activePpl = Number(snapshot.active_ppl_today ?? pplProgress.length ?? 0);
        const changedPercent = Number(snapshot.change_vs_yesterday_percent ?? 0);

        if (elements.dailyTargetSummaryGrid) {
            elements.dailyTargetSummaryGrid.innerHTML = `
                <div class="target-summary-box">
                    <span class="target-summary-label">Hari ini</span>
                    <strong>${todayTotal.toLocaleString('id-ID')}</strong>
                    <small>${changedPercent >= 0 ? '+' : ''}${changedPercent.toFixed(1)}% vs kemarin</small>
                </div>
                <div class="target-summary-box">
                    <span class="target-summary-label">Kemarin</span>
                    <strong>${yesterdayTotal.toLocaleString('id-ID')}</strong>
                    <small>Target: ${targetPerPpl} / PPL</small>
                </div>
                <div class="target-summary-box">
                    <span class="target-summary-label">7 hari</span>
                    <strong>${sevenDayTotal.toLocaleString('id-ID')}</strong>
                    <small>${averageDaily.toFixed(1)}/hari</small>
                </div>
                <div class="target-summary-box">
                    <span class="target-summary-label">PPL aktif</span>
                    <strong>${activePpl}</strong>
                    <small>Hari ini</small>
                </div>
                <div class="target-summary-box">
                    <span class="target-summary-label">Target per PPL</span>
                    <strong>${targetPerPpl}</strong>
                    <small>responden / hari</small>
                </div>
            `;
        }

        if (elements.kecamatanTargetTable) {
            const rows = KECAMATAN.map((name) => {
                const row = kecamatanProgress[name] ?? {};
                const progress = Math.min(Number(row.progress_percent ?? 0), 100);
                const target = Number(row.target_total ?? 0);
                const todayTotal = Number(row.total_today ?? 0);
                const remaining = Number(row.remaining ?? Math.max(target - todayTotal, 0));
                const yesterdayTotal = Number(row.yesterday_total ?? 0);

                return `
                    <tr>
                        <td>${esc(name)}</td>
                        <td class="ta-right">${todayTotal.toLocaleString('id-ID')}</td>
                        <td class="ta-right">${yesterdayTotal.toLocaleString('id-ID')}</td>
                        <td class="ta-right">${target.toLocaleString('id-ID')}</td>
                        <td class="ta-right">${remaining.toLocaleString('id-ID')}</td>
                        <td class="ta-right">${progress.toFixed(0)}%</td>
                        <td>
                            <div class="progress-track">
                                <span class="progress-fill" style="width:${progress}%"></span>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            elements.kecamatanTargetTable.innerHTML = rows || '<tr><td colspan="7" class="muted">Belum ada data kecamatan.</td></tr>';
        }

        if (elements.kelurahanTargetTable) {
            state.kelurahanProgress = Array.isArray(stats.per_kelurahan_daily_progress)
                ? stats.per_kelurahan_daily_progress
                : [];
            renderKelurahanProgress();
        }
    }

    function renderKelurahanProgress() {
        if (!elements.kelurahanTargetTable) return;

        state.kelurahanDistrictIndex = Math.min(state.kelurahanDistrictIndex, KECAMATAN.length - 1);
        const selectedKecamatan = KECAMATAN[state.kelurahanDistrictIndex];
        const rows = state.kelurahanProgress.filter((row) => row.kecamatan === selectedKecamatan);

        elements.kelurahanDistrictLabel.textContent = `${selectedKecamatan} · ${state.kelurahanDistrictIndex + 1} dari ${KECAMATAN.length}`;
        elements.kelurahanDistrictPrev.disabled = state.kelurahanDistrictIndex === 0;
        elements.kelurahanDistrictNext.disabled = state.kelurahanDistrictIndex >= KECAMATAN.length - 1;
        if (rows.length === 0) {
            elements.kelurahanTargetTable.innerHTML = '<tr><td colspan="7" class="muted">Belum ada progres kelurahan untuk kecamatan ini.</td></tr>';
            return;
        }

        elements.kelurahanTargetTable.innerHTML = rows.map((row) => {
            const progress = Math.min(Number(row.progress_percent ?? 0), 100);
            const remaining = Number(row.remaining ?? 0);
            return `
                <tr>
                    <td>${esc(row.kecamatan ?? '—')}</td>
                    <td>${esc(row.kelurahan ?? '—')}</td>
                    <td class="ta-right">${Number(row.total_today ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${Number(row.target_total ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${remaining.toLocaleString('id-ID')}</td>
                    <td class="ta-right">${progress.toFixed(0)}%</td>
                    <td>
                        <div class="progress-track">
                            <span class="progress-fill" style="width:${progress}%"></span>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderStatistics(stats) {
        elements.statResponden.textContent = Number(stats.total_responden ?? 0).toLocaleString('id-ID');
        elements.statPpl.textContent = Number(stats.total_ppl ?? 0).toLocaleString('id-ID');
        elements.statPml.textContent = Number(stats.total_pml ?? 0).toLocaleString('id-ID');
        elements.statSls.textContent = Number(stats.total_sls ?? 0).toLocaleString('id-ID');

        renderDailyTargetProgress(stats);

        const perKecamatan = stats.per_kecamatan ?? {};
        const perKecamatanPpl = stats.per_kecamatan_ppl ?? {};
        if (elements.kecamatanCards) {
            elements.kecamatanCards.innerHTML = KECAMATAN.map((name) => {
                const rows = perKecamatanPpl[name] ?? [];
                const detailHtml = rows.length
                    ? rows.map((row) => {
                        const pplKey = `${name}|${row.nama_ppl}`;
                        const encodedKey = encodeURIComponent(pplKey);
                        const expanded = state.expandedPplActivity.has(pplKey);
                        const isLoading = state.loadingPplActivity.has(pplKey);
                        const activity = state.pplActivityCache.get(pplKey);
                        const activityHtml = expanded ? renderPplActivityDetails(activity, isLoading) : '';

                        return `<div class="mini-ppl-entry">
                            <div class="mini-ppl-row${expanded ? ' is-expanded' : ''}">
                                <button type="button" class="mini-ppl-name" data-ppl-key="${encodedKey}" aria-expanded="${expanded}">${esc(row.nama_ppl)}</button>
                                <strong>${Number(row.total_responden ?? 0).toLocaleString('id-ID')}</strong>
                            </div>
                            ${activityHtml}
                        </div>`;
                    }).join('')
                    : '<div class="muted">Belum ada data</div>';

                const isExpanded = state.expandedKecamatan.has(name);

                return `<div class="kecamatan-card${isExpanded ? ' is-expanded' : ''}" data-kecamatan="${esc(name)}">
                    <div class="kecamatan-summary">
                        <div>
                            <div class="w-name">${esc(name)}</div>
                            <div class="panel-hint">Responden</div>
                        </div>
                        <div class="w-count">${Number(perKecamatan[name] ?? 0).toLocaleString('id-ID')}</div>
                    </div>
                    <div class="kecamatan-meta">
                        <span>${Number(rows.length ?? 0).toLocaleString('id-ID')} PPL</span>
                        <button type="button" class="detail-toggle" aria-expanded="${isExpanded}">${isExpanded ? 'Tutup' : 'Lihat detail'}</button>
                    </div>
                    <div class="kecamatan-detail${isExpanded ? '' : ' is-hidden'}"><div class="ppl-detail-list">${detailHtml}</div></div>
                </div>`;
            }).join('');

            elements.kecamatanCards.querySelectorAll('.kecamatan-card').forEach((card) => {
                const toggle = card.querySelector('.detail-toggle');
                if (!toggle) return;

                toggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const detail = card.querySelector('.kecamatan-detail');
                    if (!detail) return;
                    const kecamatan = card.dataset.kecamatan;
                    const isExpanded = !state.expandedKecamatan.has(kecamatan);

                    if (isExpanded) {
                        state.expandedKecamatan.add(kecamatan);
                    } else {
                        state.expandedKecamatan.delete(kecamatan);
                    }

                    card.classList.toggle('is-expanded', isExpanded);
                    detail.classList.toggle('is-hidden', !isExpanded);
                    toggle.textContent = isExpanded ? 'Tutup' : 'Lihat detail';
                    toggle.setAttribute('aria-expanded', String(isExpanded));
                });
            });
        }

        const perKecamatanDetail = stats.per_kecamatan_detail ?? {};
        if (elements.kecamatanTable) {
            elements.kecamatanTable.innerHTML = KECAMATAN.map((name) => {
                const detail = perKecamatanDetail[name] ?? {};
                return `<tr>
                    <td>${esc(name)}</td>
                    <td class="ta-right">${Number(detail.total_responden ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${Number(detail.total_ppl ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${Number(detail.total_pml ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${Number(detail.total_sls ?? 0).toLocaleString('id-ID')}</td>
                </tr>`;
            }).join('');
        }

        const respondentByKecamatan = stats.per_kecamatan_jenis_responden ?? {};
        if (elements.respondenKecamatanTable) {
            elements.respondenKecamatanTable.innerHTML = KECAMATAN.map((name) => {
                const detail = respondentByKecamatan[name] ?? {};
                return `<tr>
                    <td>${esc(name)}</td>
                    <td class="ta-right">${Number(detail.Keluarga ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${Number(detail.Usaha ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${Number(detail.Campuran ?? 0).toLocaleString('id-ID')}</td>
                    <td class="ta-right">${Number(detail.total ?? 0).toLocaleString('id-ID')}</td>
                </tr>`;
            }).join('');
        }

        elements.topPplTable.innerHTML = renderPplRows(stats.top_ppl ?? []);
        elements.bottomPplTable.innerHTML = renderPplRows(stats.bottom_ppl ?? []);

        const latest = stats.latest ?? [];
        const latestRows = latest.length
            ? latest.map((row) => `<tr>
                <td>${badge(row.kecamatan)}</td>
                <td>${esc(row.nama_responden ?? '—')}</td>
                <td>${esc(row.nama_ppl ?? '—')}</td>
                <td>${esc(row.nama_pml ?? '—')}</td>
                <td>${formatDate(row.tanggal_pendataan ?? row.waktu_pendataan)}</td>
                <td>${formatDateTime(row.timestamp_google)}</td>
            </tr>`).join('')
            : '<tr><td colspan="6" class="muted">Belum ada data.</td></tr>';
        if (elements.latestTable) elements.latestTable.innerHTML = latestRows;

        renderPmlFieldActivity(stats.pml_field_activity ?? {});

        const dailyProgress = stats.daily_progress ?? [];
        if (elements.dailyProgressChart) {
            if (!dailyProgress.length) {
                elements.dailyProgressChart.innerHTML = '<div class="chart-empty">Belum ada data harian.</div>';
            } else {
                const maxValue = Math.max(...dailyProgress.map((row) => Number(row.total ?? 0)), 1);
                elements.dailyProgressChart.innerHTML = dailyProgress.map((row) => {
                    const height = Math.max((Number(row.total ?? 0) / maxValue) * 84, 6);
                    const label = parseDate(row.date)?.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) || row.date || '—';
                    return `<div class="chart-col">
                        <div class="chart-bar-wrap">
                            <div class="chart-bar" style="height:${height}%"></div>
                            <div class="chart-value" style="--chart-value-offset:${height}%">${Number(row.total ?? 0).toLocaleString('id-ID')}</div>
                        </div>
                        <div class="chart-label">${esc(label)}</div>
                    </div>`;
                }).join('');
            }
        }

        const filterOptions = stats.filter_options ?? {};
        fillFilterSelect(elements.filterKecamatan, filterOptions.kecamatan ?? KECAMATAN, state.filters.kecamatan);
        fillFilterSelect(elements.filterJenis, filterOptions.jenis_responden ?? [], state.filters.jenis);
        fillFilterSelect(elements.filterKelurahan, filterOptions.kelurahan ?? [], state.filters.kelurahan);
        fillFilterSelect(elements.filterSls, filterOptions.sls ?? [], state.filters.sls);
        fillFilterSelect(elements.filterPpl, filterOptions.ppl ?? [], state.filters.ppl);
        fillFilterSelect(elements.filterPml, filterOptions.pml ?? [], state.filters.pml);
        applyTableSorts();
    }

    function renderPmlFieldActivity(activity) {
        if (!elements.pmlActivityLatest) return;

        elements.pmlSubmissionsToday.textContent = Number(activity.today_submissions ?? 0).toLocaleString('id-ID');
        elements.pmlSubmissionsTotal.textContent = Number(activity.total_submissions ?? 0).toLocaleString('id-ID');
        elements.pmlActivityTotalPml.textContent = Number(activity.total_pml ?? 0).toLocaleString('id-ID');

        const latest = Array.isArray(activity.latest) ? activity.latest : [];
        elements.pmlActivityLatest.innerHTML = latest.length
            ? latest.map((row) => {
                return `<tr>
                    <td>${formatDate(row.tanggal_pendataan)}</td>
                    <td>${esc(row.nama_pml ?? '—')}</td>
                    <td>${badge(row.kecamatan)}</td>
                    <td>${esc(row.kelurahan ?? '—')}</td>
                    <td>${esc(row.sls ?? '—')}</td>
                    <td>${esc(row.jenis_responden ?? '—')}</td>
                    <td>${renderPmlDocumentation(row.dokumentasi)}</td>
                </tr>`;
            }).join('')
            : '<tr><td colspan="7" class="muted">Belum ada data yang masuk dari spreadsheet PML.</td></tr>';
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
            kecamatan: state.filters.kecamatan,
            kelurahan: state.filters.kelurahan,
            sls: state.filters.sls,
            ppl: state.filters.ppl,
            pml: state.filters.pml,
            jenis_responden: state.filters.jenis,
            tanggal: state.filters.tanggal,
            date_from: state.filters.dateFrom,
            date_to: state.filters.dateTo,
            page: state.page,
            per_page: state.perPage,
        };
    }

    function localDateISO(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function setQuickDateRange(rangeKey) {
        const today = new Date();
        let start = new Date(today);

        if (rangeKey === 'today') {
            start = new Date(today);
        }
        if (rangeKey === '7d') {
            start.setDate(today.getDate() - 6);
        }
        if (rangeKey === '30d') {
            start.setDate(today.getDate() - 29);
        }
        if (rangeKey === 'month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        }

        document.getElementById('filter-date-from').value = localDateISO(start);
        document.getElementById('filter-date-to').value = localDateISO(today);
        state.filters.dateFrom = document.getElementById('filter-date-from').value;
        state.filters.dateTo = document.getElementById('filter-date-to').value;
        state.page = 1;
        state.lastSignature = '';
        refreshAll();
    }

    function detectIncompleteRows(rows) {
        return rows.filter((row) => {
            return !row.nama_ppl || !row.nama_pml || !row.kelurahan || !row.sls || !row.nama_responden || !row.kecamatan || !row.tanggal_pendataan || !row.waktu_pendataan || !row.dokumentasi;
        });
    }

    function renderDataHealth(rows) {
        const incompleteRows = detectIncompleteRows(rows);
        if (!elements.dataHealthCount || !elements.dataHealthList) return;
        elements.dataHealthCount.textContent = Number(incompleteRows.length).toLocaleString('id-ID');

        if (incompleteRows.length === 0) {
            elements.dataHealthList.innerHTML = '<li>Belum ada data yang perlu diperbaiki.</li>';
            return;
        }

        elements.dataHealthList.innerHTML = incompleteRows.slice(0, 8).map((row) => {
            const reasons = [];
            if (!row.nama_ppl) reasons.push('PPL');
            if (!row.nama_pml) reasons.push('PML');
            if (!row.kelurahan) reasons.push('kelurahan');
            if (!row.sls) reasons.push('SLS');
            if (!row.dokumentasi) reasons.push('dokumen');
            if (!row.tanggal_pendataan && !row.waktu_pendataan) reasons.push('tanggal');
            return `<li><strong>${esc(row.kecamatan || '—')}</strong> • ${esc(row.nama_responden || 'Responden belum jelas')} • ${esc(reasons.join(', '))}</li>`;
        }).join('');
    }

    function renderData(data) {
        const rows = data.data ?? [];
        const currentPage = data.current_page ?? 1;
        const lastPage = data.last_page ?? 1;
        const totalData = state.stats?.total_responden ?? data.total ?? 0;

        elements.dataSummaryTotal.textContent = Number(totalData).toLocaleString('id-ID');
        elements.dataSummaryFiltered.textContent = Number(data.total ?? 0).toLocaleString('id-ID');
        if (rows.length === 0) {
            elements.dataTable.innerHTML = '';
            elements.dataEmpty.classList.remove('is-hidden');
        } else {
            elements.dataEmpty.classList.add('is-hidden');
            const from = ((data.current_page ?? 1) - 1) * (data.per_page ?? rows.length);
            elements.dataTable.innerHTML = rows.map((row, i) => `<tr>
                <td>${from + i + 1}</td>
                <td>${badge(row.kecamatan)}</td>
                <td>${formatDate(row.tanggal_pendataan ?? row.waktu_pendataan)}</td>
                <td>${formatDateTime(row.timestamp_google)}</td>
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

        renderDataHealth(rows);
        renderPagination(data);
        applyTableSorts();
    }

    function sortableValue(row, column, type) {
        const text = row.cells[column]?.textContent.trim() || '';

        if (type === 'date') {
            return parseDate(text)?.getTime() || 0;
        }

        const number = Number(text.replace(/[^\d,-]/g, '').replace(',', '.'));
        return Number.isNaN(number) ? text.toLocaleLowerCase() : number;
    }

    function sortTable(select) {
        const direction = select.value;
        if (!direction) return;

        const tbody = document.getElementById(select.dataset.table);
        if (!tbody) return;

        const column = Number(select.dataset.column);
        const type = select.dataset.type || 'number';
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((first, second) => {
            const firstValue = sortableValue(first, column, type);
            const secondValue = sortableValue(second, column, type);
            const comparison = typeof firstValue === 'string'
                ? firstValue.localeCompare(secondValue, 'id')
                : firstValue - secondValue;

            return direction === 'asc' ? comparison : -comparison;
        });

        rows.forEach((row) => tbody.appendChild(row));
    }

    function applyTableSorts() {
        document.querySelectorAll('.table-sort').forEach((select) => sortTable(select));
    }

    function reportValue(id) {
        return document.getElementById(id)?.value.trim() || '-';
    }

    function reportDate(value) {
        if (!value) return '';
        const date = parseDate(value);
        if (!date) return esc(value);
        return esc(date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }));
    }

    function reportParagraph(text) {
        return `<p style="margin: 0 0 15px; text-align: justify; line-height: 1.75;">${text}</p>`;
    }

    function downloadReport() {
        const firstRow = state.data?.data?.[0] || {};
        const reportInput = (id) => document.getElementById(id)?.value.trim() || '';
        const slsText = String(firstRow.sls || '');
        const slsLocation = slsText.match(/RT\s*([\w.-]+).*?RW\s*([\w.-]+)/i);
        const kecamatan = reportInput('report-kecamatan') || state.filters.kecamatan || firstRow.kecamatan || '';
        const kelurahan = reportInput('report-kelurahan') || state.filters.kelurahan || firstRow.kelurahan || '';
        const reportDateValue = reportInput('report-date') || firstRow.tanggal_pendataan || '';
        const executor = reportInput('report-executor') || firstRow.nama_ppl || '';
        const rt = reportInput('report-rt') || slsLocation?.[1] || '';
        const rw = reportInput('report-rw') || slsLocation?.[2] || '';
        const payload = {
            title: reportInput('report-title'),
            executor,
            date: reportDateValue,
            letter: reportInput('report-letter'),
            rt,
            rw,
            kelurahan,
            kecamatan,
            family_count: reportInput('report-family-count'),
            new_business: reportInput('report-new-business'),
            new_family: reportInput('report-new-family'),
            signatory: reportInput('report-signatory'),
        };

        fetch('/api/reports/perjadin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return response.blob();
        }).then((blob) => {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'Laporan Perjadin Revisit dan Penyisiran SE2026.docx';
            link.click();
            URL.revokeObjectURL(link.href);
        }).catch((error) => {
            console.error('Gagal mengunduh laporan:', error);
            window.alert('Laporan gagal dibuat. Periksa server Laravel.');
        });
    }

    function renderPplActivityDetails(activity, isLoading) {
        if (isLoading) {
            return '<div class="mini-ppl-activity muted">Memuat riwayat pengiriman...</div>';
        }

        if (activity?.error) {
            return '<div class="mini-ppl-activity muted">Riwayat gagal dimuat. Tekan nama untuk mencoba lagi.</div>';
        }

        if (!activity) {
            return '<div class="mini-ppl-activity muted">Memuat riwayat pengiriman...</div>';
        }

        const dailyActivity = Array.isArray(activity.daily_activity) ? activity.daily_activity : [];
        if (!dailyActivity.length) {
            return '<div class="mini-ppl-activity muted">Belum ada rincian tanggal.</div>';
        }

        return `<div class="mini-ppl-activity">
            <div class="mini-ppl-activity-total">
                <span>Total riwayat</span>
                <strong>${Number(activity.total_responden ?? 0).toLocaleString('id-ID')}</strong>
            </div>
            <div class="mini-ppl-activity-total">
                <span>Hari ini / target</span>
                <strong>${Number(activity.today_total ?? 0).toLocaleString('id-ID')} / ${Number(activity.daily_target_per_ppl ?? 10).toLocaleString('id-ID')} (${Number(activity.progress_percent ?? 0).toFixed(0)}%)</strong>
            </div>
            <div class="progress-track mini-ppl-progress-track">
                <span class="progress-fill" style="width:${Math.min(Number(activity.progress_percent ?? 0), 100)}%"></span>
            </div>
            ${dailyActivity.map((day) => {
                const date = parseDate(day.date);
                const label = date?.toLocaleDateString('id-ID', {
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }) || day.date || 'Tanggal tidak tersedia';
                return `<div class="mini-ppl-activity-row"><time datetime="${esc(day.date ?? '')}">${esc(label)}</time><strong>${Number(day.total ?? 0).toLocaleString('id-ID')}</strong></div>`;
            }).join('')}
        </div>`;
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
        for (let page = 1; page <= lastPage; page++) {
            if (page === 1 || page === lastPage || Math.abs(page - current) <= 1) {
                buttons.push(`<button class="page-btn ${page === current ? 'is-active' : ''}" data-page="${page}">${page}</button>`);
            } else if (Math.abs(page - current) === 2) {
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
                DataSource.getStatistics(state.filters.kecamatan),
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
        state.filters.kecamatan = elements.filterKecamatan.value;
        state.filters.tanggal = document.getElementById('filter-tanggal')?.value || '';
        state.filters.dateFrom = document.getElementById('filter-date-from')?.value || '';
        state.filters.dateTo = document.getElementById('filter-date-to')?.value || '';
        state.filters.kelurahan = elements.filterKelurahan.value;
        state.filters.sls = elements.filterSls.value;
        state.filters.ppl = elements.filterPpl.value;
        state.filters.pml = elements.filterPml.value;
        state.filters.jenis = elements.filterJenis.value;
        state.page = 1;
        state.lastSignature = '';
        refreshAll();
    }

    function resetFilters() {
        document.getElementById('filter-form').reset();
        document.getElementById('filter-search').value = '';
        state.filters = { search: '', kecamatan: '', tanggal: '', dateFrom: '', dateTo: '', kelurahan: '', sls: '', ppl: '', pml: '', jenis: '' };
        state.page = 1;
        state.lastSignature = '';
        refreshAll();
    }

    function exportCsv() {
        const params = currentParams();
        params.per_page = 5000;
        const url = `/api/kartu-kendali?${new URLSearchParams(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined)).toString()}`;

        fetch(url)
            .then((response) => response.json())
            .then((payload) => {
                const rows = payload.data ?? [];
                if (!rows.length) {
                    window.alert('Tidak ada data yang bisa diekspor dengan filter saat ini.');
                    return;
                }

                const headers = ['Kecamatan', 'Tanggal Pendataan', 'Waktu Pendataan', 'PPL', 'PML', 'Kelurahan', 'SLS', 'Nama Responden', 'Jenis Responden', 'Dokumentasi', 'Keterangan'];
                const csvRows = [headers.join(',')].concat(rows.map((row) => {
                    const values = [
                        row.kecamatan ?? '',
                        row.tanggal_pendataan ?? '',
                        row.waktu_pendataan ?? '',
                        row.nama_ppl ?? '',
                        row.nama_pml ?? '',
                        row.kelurahan ?? '',
                        row.sls ?? '',
                        row.nama_responden ?? '',
                        row.jenis_responden ?? '',
                        row.dokumentasi ?? '',
                        row.keterangan ?? '',
                    ].map((value) => `"${String(value).replace(/"/g, '""')}"`);
                    return values.join(',');
                }));

                const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'data-kartu-kendali.csv';
                link.click();
                URL.revokeObjectURL(link.href);
            })
            .catch((error) => {
                console.error('Export CSV gagal:', error);
                window.alert('Export CSV gagal dibuat.');
            });
    }

    function navigate(section) {
        const titles = { dashboard: 'Dashboard Penyisiran SE2026', data: 'Data Kartu Kendali', report: 'Laporan Perjalanan Dinas', api: 'Koneksi API' };
        ['dashboard', 'data', 'report', 'api'].forEach((key) => {
            const sectionElement = document.getElementById(`section-${key}`);
            if (sectionElement) sectionElement.classList.toggle('is-hidden', key !== section);
        });
        document.querySelectorAll('.nav-item').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.section === section);
        });
        document.getElementById('page-title').textContent = titles[section];
        document.getElementById('sidebar').classList.remove('is-open');
    }

    function init() {
        setLiveStatus(true);
        updateTimestamp();

        document.getElementById('filter-form').addEventListener('submit', (event) => {
            event.preventDefault();
            applyFilters();
        });
        document.getElementById('reset-filter').addEventListener('click', resetFilters);
        elements.exportCsv.addEventListener('click', exportCsv);
        elements.kelurahanDistrictPrev.addEventListener('click', () => {
            if (state.kelurahanDistrictIndex === 0) return;
            state.kelurahanDistrictIndex -= 1;
            renderKelurahanProgress();
        });
        elements.kelurahanDistrictNext.addEventListener('click', () => {
            if (state.kelurahanDistrictIndex >= KECAMATAN.length - 1) return;
            state.kelurahanDistrictIndex += 1;
            renderKelurahanProgress();
        });
        elements.kecamatanCards.addEventListener('click', async (event) => {
            const districtToggle = event.target.closest('.detail-toggle');
            if (districtToggle) {
                event.stopPropagation();
                const card = districtToggle.closest('.kecamatan-card');
                if (!card) return;
                const kecamatan = card.dataset.kecamatan;
                const isExpanded = !state.expandedKecamatan.has(kecamatan);

                if (isExpanded) {
                    state.expandedKecamatan.add(kecamatan);
                } else {
                    state.expandedKecamatan.delete(kecamatan);
                }

                card.classList.toggle('is-expanded', isExpanded);
                card.querySelector('.kecamatan-detail')?.classList.toggle('is-hidden', !isExpanded);
                districtToggle.textContent = isExpanded ? 'Tutup' : 'Lihat detail';
                districtToggle.setAttribute('aria-expanded', String(isExpanded));
                return;
            }

            const pplToggle = event.target.closest('.mini-ppl-name');
            if (!pplToggle) return;

            const pplKey = decodeURIComponent(pplToggle.dataset.pplKey);
            if (state.expandedPplActivity.has(pplKey)) {
                state.expandedPplActivity.delete(pplKey);
                renderStatistics(state.stats);
                return;
            }

            state.expandedPplActivity.add(pplKey);
            if (state.pplActivityCache.has(pplKey)) {
                renderStatistics(state.stats);
                return;
            }

            const separatorIndex = pplKey.indexOf('|');
            const kecamatan = pplKey.slice(0, separatorIndex);
            const namaPpl = pplKey.slice(separatorIndex + 1);
            state.loadingPplActivity.add(pplKey);
            renderStatistics(state.stats);

            try {
                state.pplActivityCache.set(pplKey, await DataSource.getPplActivity(kecamatan, namaPpl));
            } catch (error) {
                console.error('Gagal memuat riwayat harian PPL:', error);
                state.pplActivityCache.set(pplKey, { error: true });
            } finally {
                state.loadingPplActivity.delete(pplKey);
                renderStatistics(state.stats);
            }
        });
        document.querySelectorAll('[data-range]').forEach((button) => {
            button.addEventListener('click', () => setQuickDateRange(button.dataset.range));
        });
        document.getElementById('filter-search').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyFilters();
            }
        });

        document.getElementById('filter-date-from')?.addEventListener('change', () => {
            state.filters.dateFrom = document.getElementById('filter-date-from').value;
            state.page = 1;
            state.lastSignature = '';
            refreshAll();
        });
        document.getElementById('filter-date-to')?.addEventListener('change', () => {
            state.filters.dateTo = document.getElementById('filter-date-to').value;
            state.page = 1;
            state.lastSignature = '';
            refreshAll();
        });
        elements.filterKecamatan.addEventListener('change', () => {
            state.filters.kecamatan = elements.filterKecamatan.value;
            ['filterKelurahan', 'filterSls', 'filterPpl', 'filterPml', 'filterJenis'].forEach((key) => {
                elements[key].value = '';
            });
            state.filters.kelurahan = '';
            state.filters.sls = '';
            state.filters.ppl = '';
            state.filters.pml = '';
            state.filters.jenis = '';
            state.page = 1;
            state.lastSignature = '';
            refreshAll();
        });

        elements.pagination.addEventListener('click', (event) => {
            const button = event.target.closest('.page-btn');
            if (!button || button.disabled) return;
            state.page = Number(button.dataset.page);
            state.lastSignature = '';
            refreshAll();
        });
        document.querySelectorAll('.table-sort').forEach((select) => {
            select.addEventListener('change', () => sortTable(select));
        });
        document.querySelectorAll('.nav-item').forEach((button) => {
            button.addEventListener('click', () => navigate(button.dataset.section));
        });
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('is-open');
        });
        elements.downloadReport.addEventListener('click', downloadReport);

        const baseUrl = `${window.location.origin}/api/kartu-kendali`;
        elements.apiExample.textContent = [
            `curl -X POST ${baseUrl} \\`,
            `  -H "Authorization: Bearer KARTU_KENDALI_API_TOKEN" \\`,
            `  -H "Content-Type: application/json" \\`,
            `  -d '{`,
            `    "kecamatan": "SETU",`,
            `    "timestamp_google": "2026-09-22T08:30:00+07:00",`,
            `    "tanggal_pendataan": "2026-09-22",`,
            `    "waktu_pendataan": "2026-09-22T08:30:00+07:00",`,
            `    "nama_ppl": "Nama PPL",`,
            `    "nama_pml": "Nama PML",`,
            `    "kelurahan": "Setu",`,
            `    "sls": "001",`,
            `    "nama_responden": "Nama Responden",`,
            `    "jenis_responden": "Keluarga",`,
            `    "dokumentasi": "https://drive.google.com/...",`,
            `    "keterangan": "Catatan",`,
            `    "source_sheet": "SETU",`,
            `    "external_id": "abc-123"`,
            `  }'`,
        ].join('\n');

        refreshAll();
        setInterval(refreshAll, POLL_INTERVAL_MS);
    }

    // ==================== FASIH CHECK ====================

    const fasihState = {
        data: null,
        summary: null,
        filters: {
            search: '',
            kecamatan: '',
            kelurahan: '',
            sls: '',
            ppl: '',
            pml: '',
            status_pemindahan: '',
        },
        page: 1,
        perPage: 15,
    };

    const fasihElements = {
        tableBody: document.getElementById('fasih-table-body'),
        empty: document.getElementById('fasih-empty'),
        pagination: document.getElementById('fasih-pagination'),
        filterKecamatan: document.getElementById('fasih-filter-kecamatan'),
        filterKelurahan: document.getElementById('fasih-filter-kelurahan'),
        filterSls: document.getElementById('fasih-filter-sls'),
        filterPpl: document.getElementById('fasih-filter-ppl'),
        filterPml: document.getElementById('fasih-filter-pml'),
        filterStatusPemindahan: document.getElementById('fasih-filter-status-pemindahan'),
        filterSearch: document.getElementById('fasih-filter-search'),
        total: document.getElementById('fasih-total'),
        sudahDicek: document.getElementById('fasih-sudah-dicek'),
        sesuai: document.getElementById('fasih-sesuai'),
        tidakSesuai: document.getElementById('fasih-tidak-sesuai'),
        tidakDitemukan: document.getElementById('fasih-tidak-ditemukan'),
        belumDicek: document.getElementById('fasih-belum-dicek'),
        exportBtn: document.getElementById('fasih-export-btn'),
        toastContainer: document.getElementById('fasih-toast-container'),
    };

    function fasihToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        fasihElements.toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    async function fasihFetch(url, options = {}) {
        const response = await fetch(url, options);
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `HTTP ${response.status}`);
        }
        return response.json();
    }

    function fasihBuildParams({ includePage = false } = {}) {
        const params = new URLSearchParams();
        Object.entries(fasihState.filters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });

        if (includePage) {
            params.set('page', fasihState.page);
            params.set('per_page', fasihState.perPage);
        }

        return params;
    }

    function fasihStatusLabel(status) {
        return {
            belum_dipindah: 'Belum Dipindah',
            sudah_dipindah: 'Sudah Dipindah',
            sudah_sesuai_sls: 'Sudah Sesuai SLS',
        }[status] || status || 'Belum Dipindah';
    }

    async function fasihLoadSummary() {
        try {
            const params = fasihBuildParams();
            const summary = await fasihFetch(`/api/fasih-checks/summary?${params.toString()}`);
            fasihState.summary = summary;
            fasihElements.total.textContent = Number(summary.total_data || 0).toLocaleString('id-ID');
            fasihElements.sudahDicek.textContent = Number(summary.sudah_dicek || 0).toLocaleString('id-ID');
            fasihElements.sesuai.textContent = Number(summary.sesuai || 0).toLocaleString('id-ID');
            fasihElements.belumDicek.textContent = Number(summary.belum_dicek || 0).toLocaleString('id-ID');
        } catch (error) {
            console.error('FASIH summary load failed:', error);
            fasihElements.total.textContent = '0';
            fasihElements.sudahDicek.textContent = '0';
            fasihElements.sesuai.textContent = '0';
            fasihElements.belumDicek.textContent = '0';
        }
    }

    function fasihRenderKecamatanSummary(rows) {
        const container = document.getElementById('fasih-kecamatan-summary');
        if (!container) return;

        if (!rows.length) {
            container.innerHTML = '<div class="muted">Belum ada data untuk ringkasan.</div>';
            return;
        }

        const summary = rows.reduce((accumulator, row) => {
            const kecamatan = row.kecamatan || 'Lainnya';
            const status = row.status_pemindahan || 'belum_dipindah';

            if (!accumulator[kecamatan]) {
                accumulator[kecamatan] = {
                    total: 0,
                    belum_dipindah: 0,
                    sudah_dipindah: 0,
                    sudah_sesuai_sls: 0,
                };
            }

            accumulator[kecamatan].total += 1;
            accumulator[kecamatan][status] = (accumulator[kecamatan][status] || 0) + 1;
            return accumulator;
        }, {});

        const entries = Object.entries(summary)
            .map(([kecamatan, item]) => {
                const total = item.total || 1;
                const belum = item.belum_dipindah || 0;
                const sudah = item.sudah_dipindah || 0;
                const sesuai = item.sudah_sesuai_sls || 0;
                const stats = {
                    belum_dipindah: (belum / total) * 100,
                    sudah_dipindah: (sudah / total) * 100,
                    sudah_sesuai_sls: (sesuai / total) * 100,
                };

                return {
                    kecamatan,
                    total,
                    belum,
                    sudah,
                    sesuai,
                    stats,
                };
            })
            .sort((a, b) => b.total - a.total);

        container.innerHTML = entries.map(({ kecamatan, total, belum, sudah, sesuai, stats }) => `
            <div class="fasih-kecamatan-summary-card">
                <div class="fasih-kecamatan-summary-header">
                    <strong>${fasihEsc(kecamatan)}</strong>
                    <span>${Number(total).toLocaleString('id-ID')} data</span>
                </div>
                <div class="fasih-kecamatan-status-bar">
                    <span class="fasih-status-bar belum" style="width:${Math.max(stats.belum_dipindah, 0)}%"></span>
                    <span class="fasih-status-bar sudah" style="width:${Math.max(stats.sudah_dipindah, 0)}%"></span>
                    <span class="fasih-status-bar sesuai" style="width:${Math.max(stats.sudah_sesuai_sls, 0)}%"></span>
                </div>
                <div class="fasih-kecamatan-status-labels">
                    <span><em class="status-dot belum"></em> Belum: ${Number((stats.belum_dipindah || 0)).toFixed(0)}%</span>
                    <span><em class="status-dot sudah"></em> Sudah: ${Number((stats.sudah_dipindah || 0)).toFixed(0)}%</span>
                    <span><em class="status-dot sesuai"></em> SLS: ${Number((stats.sudah_sesuai_sls || 0)).toFixed(0)}%</span>
                </div>
            </div>
        `).join('');
    }

    async function fasihLoadKecamatanSummary() {
        try {
            const params = fasihBuildParams();
            params.set('per_page', '5000');
            params.delete('page');
            const data = await fasihFetch(`/api/fasih-checks?${params.toString()}`);
            fasihRenderKecamatanSummary(data.data || []);
            await fasihLoadKelurahanProgress();
        } catch (error) {
            console.error('FASIH district summary load failed:', error);
            document.getElementById('fasih-kecamatan-summary').innerHTML = '<div class="muted">Ringkasan gagal dimuat.</div>';
        }
    }

    function fasihRenderKelurahanProgress(data) {
        const container = document.getElementById('fasih-kelurahan-progress');
        const overview = document.getElementById('fasih-progress-overview');
        if (!container) return;

        const rows = data.data || [];
        const summary = data.summary || {};
        if (overview) {
            overview.textContent = `${Number(summary.total_kelurahan || 0).toLocaleString('id-ID')} kelurahan · ${Number(summary.total_responden || 0).toLocaleString('id-ID')} data · ${Number(summary.completion_rate || 0).toFixed(1)}% selesai`;
        }

        if (!rows.length) {
            container.innerHTML = '<div class="muted">Belum ada data untuk progress kelurahan.</div>';
            return;
        }

        const groups = rows.reduce((grouped, row) => {
            const kecamatan = row.kecamatan || 'Lainnya';
            grouped[kecamatan] ??= [];
            grouped[kecamatan].push(row);
            return grouped;
        }, {});

        container.innerHTML = Object.entries(groups).map(([kecamatan, kelurahanRows]) => {
            const totalData = kelurahanRows.reduce((total, row) => total + Number(row.total_data || 0), 0);
            const completedData = kelurahanRows.reduce((total, row) => total + Number(row.completed_data || 0), 0);

            return `
                <details class="fasih-district-group">
                    <summary>
                        <strong>${fasihEsc(kecamatan)}</strong>
                        <span>${kelurahanRows.length} kelurahan</span>
                        <span>${totalData.toLocaleString('id-ID')} data</span>
                        <span>${totalData ? Math.round((completedData / totalData) * 100) : 0}% selesai</span>
                    </summary>
                    <div class="table-wrap fasih-progress-table-wrap">
                        <table class="table fasih-progress-table">
                            <thead>
                                <tr>
                                    <th>Kelurahan</th>
                                    <th class="ta-right">Total</th>
                                    <th class="ta-right">Dipindah</th>
                                    <th class="ta-right">Sesuai SLS</th>
                                    <th class="ta-right">Belum</th>
                                    <th>Progres</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                ${kelurahanRows.map((row) => {
                                    const progress = Number(row.progress_percentage || 0);
                                    const badgeClass = progress >= 100 ? 'selesai' : progress > 0 ? 'proses' : 'belum';

                                    return `
                                        <tr>
                                            <td><strong>${fasihEsc(row.kelurahan)}</strong></td>
                                            <td class="ta-right">${Number(row.total_data || 0).toLocaleString('id-ID')}</td>
                                            <td class="ta-right">${Number(row.sudah_dipindah || 0).toLocaleString('id-ID')}</td>
                                            <td class="ta-right">${Number(row.sudah_sesuai_sls || 0).toLocaleString('id-ID')}</td>
                                            <td class="ta-right">${Number(row.remaining_data || 0).toLocaleString('id-ID')}</td>
                                            <td>
                                                <div class="fasih-progress-cell">
                                                    <div class="fasih-kelurahan-progress-bar"><span style="width:${Math.min(Math.max(progress, 0), 100)}%"></span></div>
                                                    <span>${progress.toFixed(0)}%</span>
                                                </div>
                                            </td>
                                            <td><span class="fasih-kelurahan-status ${badgeClass}">${fasihEsc(row.status || 'Belum Mulai')}</span></td>
                                            <td>
                                                <button type="button" class="fasih-kelurahan-detail-btn" data-kecamatan="${fasihEsc(row.kecamatan)}" data-kelurahan="${fasihEsc(row.kelurahan)}">Lihat rincian</button>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </details>
            `;
        }).join('');
    }

    async function fasihLoadKelurahanProgress() {
        try {
            const params = fasihBuildParams();
            const data = await fasihFetch(`/api/fasih-checks/kelurahan-progress?${params.toString()}`);
            fasihRenderKelurahanProgress(data);
        } catch (error) {
            console.error('FASIH kelurahan progress load failed:', error);
            const container = document.getElementById('fasih-kelurahan-progress');
            if (container) {
                container.innerHTML = '<div class="muted">Progress kelurahan gagal dimuat.</div>';
            }
        }
    }

    async function fasihShowKelurahanDetails(kecamatan, kelurahan) {
        fasihState.filters = {
            search: '',
            kecamatan,
            kelurahan,
            sls: '',
            ppl: '',
            pml: '',
            status_pemindahan: '',
        };
        fasihState.page = 1;
        fasihElements.filterSearch.value = '';
        fasihElements.filterStatusPemindahan.value = '';

        await fasihPopulateFilters();
        await fasihLoadSummary();
        await fasihLoadData();
        document.getElementById('fasih-table-body').closest('.table-wrap')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }

    async function fasihLoadData() {
        try {
            const params = fasihBuildParams({ includePage: true });
            const data = await fasihFetch(`/api/fasih-checks?${params.toString()}`);
            fasihState.data = data;
            fasihRenderTable(data);
            fasihRenderPagination(data);
            await fasihLoadKelurahanProgress();
        } catch (error) {
            console.error('FASIH data load failed:', error);
        }
    }

    function fasihEsc(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function getRTNumber(sls) {
        const match = String(sls || '').match(/RT[^0-9]*(\d+)/i);
        return match ? parseInt(match[1], 10) : 0;
    }

    function getRWNumber(sls) {
        const match = String(sls || '').match(/RW[^0-9]*(\d+)/i);
        return match ? parseInt(match[1], 10) : 0;
    }

    function sortSLS(slsList) {
        return [...slsList].sort((a, b) => {
            const rwA = getRWNumber(a);
            const rwB = getRWNumber(b);
            if (rwA !== rwB) return rwA - rwB;
            const rtA = getRTNumber(a);
            const rtB = getRTNumber(b);
            if (rtA !== rtB) return rtA - rtB;
            return String(a || '').localeCompare(String(b || ''));
        });
    }

    function fasihRenderTable(data) {
        const rows = data.data || [];
        if (rows.length === 0) {
            fasihElements.tableBody.innerHTML = '';
            fasihElements.empty.classList.remove('is-hidden');
            return;
        }
        fasihElements.empty.classList.add('is-hidden');

        const from = ((data.current_page || 1) - 1) * (data.per_page || rows.length);

        fasihElements.tableBody.innerHTML = rows.map((row, i) => {
            const statusPemindahan = row.status_pemindahan || 'belum_dipindah';
            const statusLabel = fasihStatusLabel(statusPemindahan);

            return `<tr>
                <td>${from + i + 1}</td>
                <td>${fasihEsc(row.kecamatan)}</td>
                <td>${fasihEsc(row.kelurahan)}</td>
                <td>${fasihEsc(row.sls)}</td>
                <td>${fasihEsc(row.nama_responden)}</td>
                <td>${fasihEsc(row.nama_ppl)}</td>
                <td>${fasihEsc(row.nama_pml)}</td>
                <td>
                    <select class="fasih-status-select" data-id="${Number(row.id)}" data-value="${statusPemindahan}" aria-label="Ubah status pemindahan">
                        <option value="belum_dipindah" ${statusPemindahan === 'belum_dipindah' ? 'selected' : ''}>Belum Dipindah</option>
                        <option value="sudah_dipindah" ${statusPemindahan === 'sudah_dipindah' ? 'selected' : ''}>Sudah Dipindah</option>
                        <option value="sudah_sesuai_sls" ${statusPemindahan === 'sudah_sesuai_sls' ? 'selected' : ''}>Sudah Sesuai SLS</option>
                    </select>
                </td>
                <td>${fasihEsc(row.keterangan || '—')}</td>
            </tr>`;
        }).join('');

        fasihElements.tableBody.querySelectorAll('.fasih-status-select').forEach((select) => {
            select.dataset.value = select.value;
        });
    }

    function fasihRenderPagination(data) {
        const lastPage = data.last_page || 1;
        const current = data.current_page || 1;
        if (lastPage <= 1) {
            fasihElements.pagination.innerHTML = '';
            return;
        }

        const buttons = [];
        buttons.push(`<button class="page-btn" data-page="${current - 1}" ${current === 1 ? 'disabled' : ''}>&#9664;</button>`);
        for (let page = 1; page <= lastPage; page++) {
            if (page === 1 || page === lastPage || Math.abs(page - current) <= 1) {
                buttons.push(`<button class="page-btn ${page === current ? 'is-active' : ''}" data-page="${page}">${page}</button>`);
            } else if (Math.abs(page - current) === 2) {
                buttons.push('<span class="muted">…</span>');
            }
        }
        buttons.push(`<button class="page-btn" data-page="${current + 1}" ${current === lastPage ? 'disabled' : ''}>&#9654;</button>`);
        fasihElements.pagination.innerHTML = buttons.join('');
    }

    function fasihFillSelect(select, values, current) {
        if (!select) return;
        const normalized = values.map(String);
        const existing = Array.from(select.options).filter((o) => o.value !== '').map((o) => o.value);
        const match = existing.length === normalized.length && existing.every((v, i) => v === normalized[i]);
        if (!match) {
            const firstLabel = select.options[0]?.text || 'Semua';
            select.innerHTML = `<option value="">${fasihEsc(firstLabel)}</option>` +
                normalized.map((v) => `<option value="${fasihEsc(v)}">${fasihEsc(v)}</option>`).join('');
        }
        select.value = normalized.includes(current) ? current : '';
    }

    async function fasihPopulateFilters() {
        try {
            const params = new URLSearchParams();
            if (fasihState.filters.kecamatan) params.set('kecamatan', fasihState.filters.kecamatan);
            if (fasihState.filters.kelurahan) params.set('kelurahan', fasihState.filters.kelurahan);
            const options = (await fasihFetch(`/api/fasih-checks/options?${params.toString()}`)).filter_options || {};

            fasihFillSelect(fasihElements.filterKecamatan, [...(options.kecamatan || [])].sort((a, b) => String(a).localeCompare(String(b))), fasihState.filters.kecamatan);
            fasihFillSelect(fasihElements.filterKelurahan, [...(options.kelurahan || [])].sort((a, b) => String(a).localeCompare(String(b))), fasihState.filters.kelurahan);
            fasihFillSelect(fasihElements.filterSls, sortSLS(options.sls || []), fasihState.filters.sls);
            fasihFillSelect(fasihElements.filterPpl, [...(options.ppl || [])].sort((a, b) => String(a).localeCompare(String(b))), fasihState.filters.ppl);
            fasihFillSelect(fasihElements.filterPml, [...(options.pml || [])].sort((a, b) => String(a).localeCompare(String(b))), fasihState.filters.pml);
        } catch (error) {
            console.error('FASIH filter load failed:', error);
        }
    }

    async function fasihCheck(id) {
        try {
            const result = await fasihFetch(`/api/fasih-checks/${id}/check`, { method: 'POST' });
            fasihToast(`Pengecekan selesai: ${result.status.replace('_', ' ')}`);
            fasihLoadSummary();
            fasihLoadData();
        } catch (error) {
            fasihToast(error.message, 'error');
        }
    }

    async function fasihUpdateStatus(id, newStatus, selectElement) {
        try {
            await fasihFetch(`/api/fasih-checks/${id}/status`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status_pemindahan: newStatus }),
            });
            if (selectElement) {
                selectElement.dataset.value = newStatus;
                selectElement.value = newStatus;
                selectElement.setAttribute('data-value', newStatus);
            }
            fasihToast('Status berhasil diperbarui');
            await fasihLoadSummary();
            await fasihLoadData();
        } catch (error) {
            fasihToast(error.message, 'error');
            if (selectElement) {
                selectElement.dataset.value = selectElement.value;
                selectElement.setAttribute('data-value', selectElement.value);
            }
            await fasihLoadData();
        }
    }

    function fasihExport() {
        const params = new URLSearchParams();
        if (fasihState.filters.status_pemindahan) {
            params.set('status_pemindahan', fasihState.filters.status_pemindahan);
        }
        const url = `/api/fasih-checks/export?${params.toString()}`;

        fetch(url)
            .then((response) => response.json())
            .then((data) => {
                const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = data.filename;
                link.click();
                URL.revokeObjectURL(link.href);
                fasihToast(`Export selesai: ${data.total} data`);
            })
            .catch((error) => {
                fasihToast('Export gagal: ' + error.message, 'error');
            });
    }

    async function fasihApplyFilters() {
        fasihState.filters.kecamatan = fasihElements.filterKecamatan.value;
        fasihState.filters.kelurahan = fasihElements.filterKelurahan.value;
        fasihState.filters.sls = fasihElements.filterSls.value;
        fasihState.filters.ppl = fasihElements.filterPpl.value;
        fasihState.filters.pml = fasihElements.filterPml.value;
        fasihState.filters.status_pemindahan = fasihElements.filterStatusPemindahan.value;
        fasihState.filters.search = fasihElements.filterSearch.value.trim();
        fasihState.page = 1;

        await fasihPopulateFilters();
        await fasihLoadSummary();
        await fasihLoadData();
    }

    async function fasihResetFilters() {
        fasihElements.filterForm.reset();
        fasihState.filters = { search: '', kecamatan: '', kelurahan: '', sls: '', ppl: '', pml: '', status_pemindahan: '' };
        fasihState.page = 1;

        await fasihPopulateFilters();
        await fasihLoadSummary();
        await fasihLoadData();
    }

    function fasihInit() {
        if (!fasihElements.tableBody) return;

        fasihElements.filterForm = document.getElementById('fasih-filter-form');
        fasihElements.resetFilter = document.getElementById('fasih-reset-filter');

        fasihLoadSummary();
        fasihPopulateFilters();
        fasihLoadData();

        fasihElements.filterKecamatan.addEventListener('change', async () => {
            fasihState.filters.kecamatan = fasihElements.filterKecamatan.value;
            fasihState.filters.kelurahan = '';
            fasihState.filters.sls = '';
            await fasihApplyFilters();
        });

        fasihElements.filterKelurahan.addEventListener('change', async () => {
            fasihState.filters.kelurahan = fasihElements.filterKelurahan.value;
            fasihState.filters.sls = '';
            await fasihApplyFilters();
        });

        fasihElements.filterSls.addEventListener('change', async () => {
            fasihState.filters.sls = fasihElements.filterSls.value;
            await fasihApplyFilters();
        });

        fasihElements.filterPpl.addEventListener('change', async () => {
            fasihState.filters.ppl = fasihElements.filterPpl.value;
            await fasihApplyFilters();
        });

        fasihElements.filterPml.addEventListener('change', async () => {
            fasihState.filters.pml = fasihElements.filterPml.value;
            await fasihApplyFilters();
        });

        fasihElements.filterStatusPemindahan.addEventListener('change', async () => {
            fasihState.filters.status_pemindahan = fasihElements.filterStatusPemindahan.value;
            await fasihApplyFilters();
        });

        fasihElements.filterSearch.addEventListener('input', async () => {
            fasihState.filters.search = fasihElements.filterSearch.value.trim();
            await fasihApplyFilters();
        });

        fasihElements.filterForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await fasihApplyFilters();
        });

        fasihElements.resetFilter.addEventListener('click', () => {
            void fasihResetFilters();
        });

        fasihElements.tableBody.addEventListener('change', (event) => {
            const select = event.target.closest('.fasih-status-select');
            if (!select) return;
            fasihUpdateStatus(select.dataset.id, select.value, select);
        });

        document.getElementById('fasih-kelurahan-progress')?.addEventListener('click', (event) => {
            const button = event.target.closest('.fasih-kelurahan-detail-btn');
            if (!button) return;
            void fasihShowKelurahanDetails(button.dataset.kecamatan, button.dataset.kelurahan);
        });

        fasihElements.pagination.addEventListener('click', async (event) => {
            const btn = event.target.closest('.page-btn');
            if (!btn || btn.disabled) return;
            fasihState.page = Number(btn.dataset.page);
            await fasihLoadData();
        });

        fasihElements.exportBtn.addEventListener('click', fasihExport);
    }

    // Extend navigate to include fasih
    const originalNavigate = navigate;
    navigate = function(section) {
        const titles = {
            dashboard: 'Dashboard Penyisiran SE2026',
            data: 'Data Kartu Kendali',
            report: 'Laporan Perjalanan Dinas',
            api: 'Koneksi API',
            fasih: 'Bahan Realokasi Cek FASIH',
        };
        ['dashboard', 'data', 'report', 'api', 'fasih'].forEach((key) => {
            const sectionElement = document.getElementById(`section-${key}`);
            if (sectionElement) sectionElement.classList.toggle('is-hidden', key !== section);
        });
        document.querySelectorAll('.nav-item').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.section === section);
        });
        document.getElementById('page-title').textContent = titles[section] || 'Dashboard';
        document.getElementById('sidebar').classList.remove('is-open');

        // Save current section to localStorage
        localStorage.setItem('lastSection', section);

        if (section === 'fasih') {
            fasihLoadSummary();
            fasihPopulateFilters();
            fasihLoadData();
        }
    };

    // Restore last visited section on page load
    (function restoreLastSection() {
        const lastSection = localStorage.getItem('lastSection');
        if (lastSection && ['dashboard', 'data', 'report', 'api', 'fasih'].includes(lastSection)) {
            navigate(lastSection);
        }
    })();

    fasihInit();

    document.addEventListener('DOMContentLoaded', init);
})();
