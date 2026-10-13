<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KartuKendali;
use App\Models\PmlKartuKendali;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KartuKendaliController extends Controller
{
    /**
     * Daftar kecamatan yang didukung dashboard.
     *
     * @var array<int, string>
     */
    public const KECAMATAN = KartuKendali::KECAMATAN;

    /**
     * Menerima data baru dari Google Apps Script.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->normalizePayload($request->all());

        $rules = [
            'kecamatan' => ['required', 'string', 'max:50', Rule::in(self::KECAMATAN)],
            'timestamp_google' => ['nullable', 'string', 'max:255'],
            'tanggal_pendataan' => ['nullable', 'date'],
            'waktu_pendataan' => ['nullable', 'date'],
            'nama_ppl' => ['required', 'string', 'max:255'],
            'nama_pml' => ['required', 'string', 'max:255'],
            'kelurahan' => ['nullable', 'string', 'max:255'],
            'sls' => ['nullable', 'string', 'max:255'],
            'nama_responden' => ['required', 'string', 'max:255'],
            'jenis_responden' => ['nullable', 'string', 'max:255'],
            'dokumentasi' => ['nullable', 'string'],
            'keterangan' => ['nullable', 'string'],
            'source_sheet' => ['required', 'string', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $externalId = $this->resolveExternalId($validated);
        $validated['external_id'] = $externalId;
        $pendataanValue = $validated['waktu_pendataan']
            ?? $validated['tanggal_pendataan']
            ?? null;
        $validated['tanggal_pendataan'] = $this->parseTanggalPendataan(
            $validated['tanggal_pendataan'] ?? null
        );
        $validated['waktu_pendataan'] = $this->parseWaktuPendataan($pendataanValue);
        $validated['timestamp_masuk'] = now();

        $record = KartuKendali::where('source_sheet', $validated['source_sheet'])
            ->where('external_id', $externalId)
            ->first();

        if ($record) {
            return response()->json([
                'message' => 'Data sudah ada, tidak disimpan ulang.',
                'data' => $record,
                'duplicate' => true,
            ], JsonResponse::HTTP_OK);
        }

        try {
            $created = KartuKendali::create($validated);
        } catch (UniqueConstraintViolationException $e) {
            Log::warning('KartuKendali duplicate race detected, ignoring.', [
                'source_sheet' => $validated['source_sheet'],
                'external_id' => $externalId,
            ]);

            return response()->json([
                'message' => 'Data sudah ada, tidak disimpan ulang.',
                'duplicate' => true,
            ], JsonResponse::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan data KartuKendali.', [
                'source_sheet' => $validated['source_sheet'],
                'external_id' => $externalId,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Data kartu kendali gagal disimpan.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Data kartu kendali berhasil disimpan.',
            'data' => $created,
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Menampilkan daftar data kartu kendali dengan pagination, search, dan filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = KartuKendali::query();

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nama_responden', 'like', "%{$search}%")
                    ->orWhere('nama_ppl', 'like', "%{$search}%")
                    ->orWhere('nama_pml', 'like', "%{$search}%")
                    ->orWhere('kecamatan', 'like', "%{$search}%")
                    ->orWhere('kelurahan', 'like', "%{$search}%")
                    ->orWhere('sls', 'like', "%{$search}%");
            });
        }

        foreach ([
            'kecamatan' => 'kecamatan',
            'kelurahan' => 'kelurahan',
            'sls' => 'sls',
            'ppl' => 'nama_ppl',
            'pml' => 'nama_pml',
            'jenis_responden' => 'jenis_responden',
        ] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->string($param)->trim());
            }
        }

        $dateFrom = $request->input('date_from', $request->input('tanggal_mulai'));
        $dateTo = $request->input('date_to', $request->input('tanggal_akhir'));

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal_pendataan', $request->string('tanggal'));
        }

        if (! empty($dateFrom)) {
            $query->where(function ($q) use ($dateFrom) {
                $q->whereDate('tanggal_pendataan', '>=', $dateFrom)
                    ->orWhereDate('waktu_pendataan', '>=', $dateFrom);
            });
        }

        if (! empty($dateTo)) {
            $query->where(function ($q) use ($dateTo) {
                $q->whereDate('tanggal_pendataan', '<=', $dateTo)
                    ->orWhereDate('waktu_pendataan', '<=', $dateTo);
            });
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 5), 100);

        $records = $query->orderByDesc('waktu_pendataan')
            ->orderByDesc('tanggal_pendataan')
            ->orderByDesc('timestamp_masuk')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($records);
    }

    /**
     * Menampilkan statistik agregat.
     */
    public function statistics(Request $request): JsonResponse
    {
        $totalResponden = KartuKendali::count();
        $totalPpl = KartuKendali::distinct('nama_ppl')->count('nama_ppl');
        $totalPml = KartuKendali::distinct('nama_pml')->count('nama_pml');
        $totalSls = KartuKendali::distinct('sls')->count('sls');

        $perKecamatan = KartuKendali::query()
            ->selectRaw('kecamatan, COUNT(*) as total')
            ->groupBy('kecamatan')
            ->orderByDesc('total')
            ->pluck('total', 'kecamatan');
        $perKecamatanDetail = KartuKendali::query()
            ->selectRaw('
                kecamatan,
                COUNT(*) as total_responden,
                COUNT(DISTINCT nama_ppl) as total_ppl,
                COUNT(DISTINCT nama_pml) as total_pml,
                COUNT(DISTINCT sls) as total_sls
            ')
            ->groupBy('kecamatan')
            ->get()
            ->keyBy('kecamatan');

        $perKecamatanJenis = KartuKendali::query()
            ->selectRaw('kecamatan, jenis_responden, COUNT(*) as total')
            ->groupBy('kecamatan', 'jenis_responden')
            ->get()
            ->groupBy('kecamatan');
        $perKecamatanJenisDefault = collect(self::KECAMATAN)->mapWithKeys(
            function (string $kecamatan) use ($perKecamatanJenis): array {
                $counts = [
                    'Keluarga' => 0,
                    'Usaha' => 0,
                    'Campuran' => 0,
                ];

                foreach ($perKecamatanJenis->get($kecamatan, []) as $row) {
                    $type = $this->respondentType((string) $row->jenis_responden);
                    if (isset($counts[$type])) {
                        $counts[$type] += (int) $row->total;
                    }
                }

                return [$kecamatan => [
                    ...$counts,
                    'total' => array_sum($counts),
                ]];
            }
        );

        $selectedKecamatan = $request->string('kecamatan')->trim()->toString();
        $selectedKelurahan = $request->string('kelurahan')->trim()->toString();
        $filterOptions = collect([
            'kelurahan' => 'kelurahan',
            'sls' => 'sls',
            'ppl' => 'nama_ppl',
            'pml' => 'nama_pml',
            'jenis_responden' => 'jenis_responden',
        ])->mapWithKeys(function (string $column, string $key) use ($selectedKecamatan, $selectedKelurahan): array {
            $query = KartuKendali::query()
                ->whereNotNull($column)
                ->where($column, '!=', '');

            if ($selectedKecamatan !== '') {
                $query->where('kecamatan', $selectedKecamatan);
            }

            if ($selectedKelurahan !== '' && $key === 'sls') {
                $query->where('kelurahan', $selectedKelurahan);
            }

            return [$key => $query->distinct()->orderBy($column)->pluck($column)->values()->all()];
        });
        $filterOptions['kecamatan'] = self::KECAMATAN;

        $latestQuery = KartuKendali::query();
        if ($selectedKecamatan !== '') {
            $latestQuery->where('kecamatan', $selectedKecamatan);
        }
        $latest = $latestQuery
            ->orderByDesc('timestamp_masuk')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $perKecamatanDefault = collect(self::KECAMATAN)->mapWithKeys(
            fn (string $kecamatan) => [$kecamatan => (int) ($perKecamatan[$kecamatan] ?? 0)]
        );
        $perKecamatanDetailDefault = collect(self::KECAMATAN)->mapWithKeys(
            function (string $kecamatan) use ($perKecamatanDetail): array {
                $detail = $perKecamatanDetail->get($kecamatan);

                return [$kecamatan => [
                    'total_responden' => (int) ($detail->total_responden ?? 0),
                    'total_ppl' => (int) ($detail->total_ppl ?? 0),
                    'total_pml' => (int) ($detail->total_pml ?? 0),
                    'total_sls' => (int) ($detail->total_sls ?? 0),
                ]];
            }
        );

        $pplRankings = $this->pplRankings();
        $perKecamatanPpl = KartuKendali::query()
            ->selectRaw("kecamatan, nama_ppl, COUNT(*) as total_responden,
                SUM(CASE WHEN UPPER(TRIM(jenis_responden)) IN ('KELUARGA', 'RUMAH TANGGA BIASA') THEN 1 ELSE 0 END) as keluarga,
                SUM(CASE WHEN UPPER(TRIM(jenis_responden)) = 'USAHA' THEN 1 ELSE 0 END) as usaha,
                SUM(CASE WHEN UPPER(TRIM(jenis_responden)) = 'CAMPURAN' THEN 1 ELSE 0 END) as campuran")
            ->whereNotNull('nama_ppl')
            ->where('nama_ppl', '!=', '')
            ->groupBy('kecamatan', 'nama_ppl')
            ->orderBy('kecamatan')
            ->orderByDesc('total_responden')
            ->get()
            ->groupBy('kecamatan')
            ->map(function ($rows) {
                return $rows->map(fn ($row) => [
                    'nama_ppl' => $row->nama_ppl,
                    'total_responden' => (int) $row->total_responden,
                    'keluarga' => (int) $row->keluarga,
                    'usaha' => (int) $row->usaha,
                    'campuran' => (int) $row->campuran,
                ])->values()->all();
            })
            ->mapWithKeys(function ($rows, $kecamatan) {
                return [strtoupper((string) $kecamatan) => $rows];
            })
            ->all();

        $perKecamatanPplDefault = collect(self::KECAMATAN)->mapWithKeys(
            fn (string $kecamatan) => [$kecamatan => $perKecamatanPpl[$kecamatan] ?? []]
        );

        $dailyTargetPerPpl = 10;
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $sevenDaysAgo = Carbon::today()->subDays(6)->toDateString();

        $dailyProgress = KartuKendali::query()
            ->selectRaw('DATE(COALESCE(waktu_pendataan, tanggal_pendataan, created_at)) as date, COUNT(*) as total')
            ->whereNotNull('tanggal_pendataan')
            ->orWhereNotNull('waktu_pendataan')
            ->groupByRaw('DATE(COALESCE(waktu_pendataan, tanggal_pendataan, created_at))')
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date ? Carbon::parse($row->date)->format('Y-m-d') : null,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $pplActivity = KartuKendali::query()
            ->selectRaw('nama_ppl, COUNT(*) as total_responden, MAX(COALESCE(waktu_pendataan, tanggal_pendataan, timestamp_masuk, created_at)) as last_activity')
            ->whereNotNull('nama_ppl')
            ->where('nama_ppl', '!=', '')
            ->groupBy('nama_ppl')
            ->orderByDesc('total_responden')
            ->get()
            ->map(fn ($row) => [
                'nama_ppl' => $row->nama_ppl,
                'total_responden' => (int) $row->total_responden,
                'last_activity' => $row->last_activity ? Carbon::parse($row->last_activity)->timezone('UTC')->format('Y-m-d\TH:i:s.u\Z') : null,
            ])
            ->values()
            ->all();

        $todayTotal = KartuKendali::query()
            ->where(function ($query) use ($today) {
                $query->whereDate('tanggal_pendataan', $today)
                    ->orWhereDate('waktu_pendataan', $today);
            })
            ->count();

        $yesterdayTotal = KartuKendali::query()
            ->where(function ($query) use ($yesterday) {
                $query->whereDate('tanggal_pendataan', $yesterday)
                    ->orWhereDate('waktu_pendataan', $yesterday);
            })
            ->count();

        $lastSevenDaysTotal = KartuKendali::query()
            ->where(function ($query) use ($sevenDaysAgo, $today) {
                $query->whereDate('tanggal_pendataan', '>=', $sevenDaysAgo)
                    ->whereDate('tanggal_pendataan', '<=', $today)
                    ->orWhere(function ($inner) use ($sevenDaysAgo, $today) {
                        $inner->whereDate('waktu_pendataan', '>=', $sevenDaysAgo)
                            ->whereDate('waktu_pendataan', '<=', $today);
                    });
            })
            ->count();

        $averagePerDayLastSevenDays = $lastSevenDaysTotal > 0 ? round($lastSevenDaysTotal / 7, 2) : 0;
        $yesterdayRateChange = $yesterdayTotal > 0 ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 2) : 0;

        $recentPplRows = KartuKendali::query()
            ->selectRaw('kecamatan, nama_ppl, SUM(CASE WHEN DATE(COALESCE(waktu_pendataan, tanggal_pendataan, created_at)) = ? THEN 1 ELSE 0 END) as total_today', [$today])
            ->whereNotNull('nama_ppl')
            ->where('nama_ppl', '!=', '')
            ->where(function ($query) use ($sevenDaysAgo, $today) {
                $query->where(function ($inner) use ($sevenDaysAgo, $today) {
                    $inner->whereDate('tanggal_pendataan', '>=', $sevenDaysAgo)
                        ->whereDate('tanggal_pendataan', '<=', $today);
                })->orWhere(function ($inner) use ($sevenDaysAgo, $today) {
                    $inner->whereDate('waktu_pendataan', '>=', $sevenDaysAgo)
                        ->whereDate('waktu_pendataan', '<=', $today);
                });
            })
            ->groupBy('kecamatan', 'nama_ppl')
            ->orderByDesc('total_today')
            ->get();

        $perPplDailyProgress = $recentPplRows->map(function ($row) use ($dailyTargetPerPpl): array {
            $totalToday = (int) ($row->total_today ?? 0);
            $progressPercent = $dailyTargetPerPpl > 0
                ? round(($totalToday / $dailyTargetPerPpl) * 100, 2)
                : 0;

            return [
                'kecamatan' => $row->kecamatan,
                'nama_ppl' => $row->nama_ppl,
                'total_today' => $totalToday,
                'target_per_hari' => $dailyTargetPerPpl,
                'progress_percent' => $progressPercent,
            ];
        })->values()->all();
        $todayPplActive = collect($perPplDailyProgress)
            ->filter(fn (array $row): bool => $row['total_today'] > 0)
            ->pluck('nama_ppl')
            ->unique()
            ->count();

        $perKecamatanTargetProgress = collect(self::KECAMATAN)->mapWithKeys(function (string $kecamatan) use ($dailyTargetPerPpl, $today, $yesterday, $sevenDaysAgo): array {
            $totalPpl = KartuKendali::query()
                ->where('kecamatan', $kecamatan)
                ->whereNotNull('nama_ppl')
                ->where('nama_ppl', '!=', '')
                ->where(function ($query) use ($sevenDaysAgo, $today) {
                    $query->where(function ($inner) use ($sevenDaysAgo, $today) {
                        $inner->whereDate('tanggal_pendataan', '>=', $sevenDaysAgo)
                            ->whereDate('tanggal_pendataan', '<=', $today);
                    })->orWhere(function ($inner) use ($sevenDaysAgo, $today) {
                        $inner->whereDate('waktu_pendataan', '>=', $sevenDaysAgo)
                            ->whereDate('waktu_pendataan', '<=', $today);
                    });
                })
                ->distinct('nama_ppl')
                ->count('nama_ppl');

            $summary = KartuKendali::query()
                ->selectRaw('COUNT(*) as total_today')
                ->where('kecamatan', $kecamatan)
                ->where(function ($query) use ($today) {
                    $query->whereDate('tanggal_pendataan', $today)
                        ->orWhereDate('waktu_pendataan', $today);
                })
                ->first();

            $yesterdaySummary = KartuKendali::query()
                ->where('kecamatan', $kecamatan)
                ->where(function ($query) use ($yesterday) {
                    $query->whereDate('tanggal_pendataan', $yesterday)
                        ->orWhereDate('waktu_pendataan', $yesterday);
                })
                ->count();

            $totalToday = (int) ($summary->total_today ?? 0);
            $targetTotal = $totalPpl * $dailyTargetPerPpl;
            $remaining = max($targetTotal - $totalToday, 0);
            $progressPercent = $targetTotal > 0 ? round(($totalToday / $targetTotal) * 100, 2) : 0;
            $dayChange = $yesterdaySummary > 0 ? round((($totalToday - $yesterdaySummary) / $yesterdaySummary) * 100, 2) : 0;

            return [$kecamatan => [
                'kecamatan' => $kecamatan,
                'total_today' => $totalToday,
                'total_ppl' => $totalPpl,
                'target_total' => $targetTotal,
                'remaining' => $remaining,
                'progress_percent' => $progressPercent,
                'yesterday_total' => $yesterdaySummary,
                'day_change_percent' => $dayChange,
            ]];
        })->all();

        $perKelurahanDailyProgress = KartuKendali::query()
            ->selectRaw('kecamatan, kelurahan, COUNT(DISTINCT nama_ppl) as total_ppl, SUM(CASE WHEN DATE(COALESCE(waktu_pendataan, tanggal_pendataan, created_at)) = ? THEN 1 ELSE 0 END) as total_today, SUM(CASE WHEN DATE(COALESCE(waktu_pendataan, tanggal_pendataan, created_at)) = ? THEN 1 ELSE 0 END) as total_yesterday', [$today, $yesterday])
            ->whereNotNull('kelurahan')
            ->where('kelurahan', '!=', '')
            ->whereNotNull('nama_ppl')
            ->where('nama_ppl', '!=', '')
            ->where(function ($query) use ($sevenDaysAgo, $today) {
                $query->where(function ($inner) use ($sevenDaysAgo, $today) {
                    $inner->whereDate('tanggal_pendataan', '>=', $sevenDaysAgo)
                        ->whereDate('tanggal_pendataan', '<=', $today);
                })->orWhere(function ($inner) use ($sevenDaysAgo, $today) {
                    $inner->whereDate('waktu_pendataan', '>=', $sevenDaysAgo)
                        ->whereDate('waktu_pendataan', '<=', $today);
                });
            })
            ->groupBy('kecamatan', 'kelurahan')
            ->orderByDesc('total_today')
            ->get()
            ->map(function ($row) use ($dailyTargetPerPpl, $yesterday): array {
                $totalToday = (int) ($row->total_today ?? 0);
                $totalPpl = (int) ($row->total_ppl ?? 0);
                $targetTotal = $totalPpl * $dailyTargetPerPpl;
                $remaining = max($targetTotal - $totalToday, 0);
                $progressPercent = $targetTotal > 0 ? round(($totalToday / $targetTotal) * 100, 2) : 0;

                $yesterdaySummary = (int) ($row->total_yesterday ?? 0);

                $dayChange = $yesterdaySummary > 0 ? round((($totalToday - $yesterdaySummary) / $yesterdaySummary) * 100, 2) : 0;

                return [
                    'kecamatan' => $row->kecamatan,
                    'kelurahan' => $row->kelurahan,
                    'total_today' => $totalToday,
                    'total_ppl' => $totalPpl,
                    'target_total' => $targetTotal,
                    'remaining' => $remaining,
                    'progress_percent' => $progressPercent,
                    'yesterday_total' => $yesterdaySummary,
                    'day_change_percent' => $dayChange,
                ];
            })
            ->values()
            ->all();

        $pmlActivitySummary = PmlKartuKendali::query()
            ->selectRaw('COUNT(*) as total_submissions, COUNT(DISTINCT nama_pml) as total_pml')
            ->first();
        $pmlActivityLatest = PmlKartuKendali::query()
            ->select([
                'id', 'timestamp_google', 'tanggal_pendataan', 'nama_pml', 'kecamatan',
                'kelurahan', 'sls', 'jenis_responden', 'dokumentasi',
            ])
            ->orderByDesc('timestamp_masuk')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return response()->json([
            'total_responden' => $totalResponden,
            'total_ppl' => $totalPpl,
            'total_pml' => $totalPml,
            'total_sls' => $totalSls,
            'daily_target_per_ppl' => $dailyTargetPerPpl,
            'today_label' => $today,
            'daily_snapshot' => [
                'today_total' => $todayTotal,
                'yesterday_total' => $yesterdayTotal,
                'last_7_days_total' => $lastSevenDaysTotal,
                'average_per_day_7d' => $averagePerDayLastSevenDays,
                'active_ppl_today' => $todayPplActive,
                'change_vs_yesterday_percent' => $yesterdayRateChange,
            ],
            'per_kecamatan' => $perKecamatanDefault,
            'per_kecamatan_detail' => $perKecamatanDetailDefault,
            'per_kecamatan_jenis_responden' => $perKecamatanJenisDefault,
            'per_kecamatan_ppl' => $perKecamatanPplDefault,
            'per_ppl_daily_progress' => $perPplDailyProgress,
            'per_kecamatan_target_progress' => $perKecamatanTargetProgress,
            'per_kecamatan_daily_progress' => $perKecamatanTargetProgress,
            'per_kelurahan_daily_progress' => $perKelurahanDailyProgress,
            'daily_progress' => $dailyProgress,
            'ppl_activity' => $pplActivity,
            'top_ppl' => $pplRankings['top'],
            'bottom_ppl' => $pplRankings['bottom'],
            'filter_options' => $filterOptions,
            'latest' => $latest,
            'pml_field_activity' => [
                'total_submissions' => (int) ($pmlActivitySummary->total_submissions ?? 0),
                'total_pml' => (int) ($pmlActivitySummary->total_pml ?? 0),
                'today_submissions' => PmlKartuKendali::query()
                    ->whereDate('tanggal_pendataan', $today)
                    ->count(),
                'latest' => $pmlActivityLatest,
            ],
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Menampilkan rekap responden harian sepanjang riwayat satu PPL.
     */
    public function pplActivity(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'kecamatan' => ['required', 'string', Rule::in(self::KECAMATAN)],
            'nama_ppl' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Kecamatan dan nama PPL harus diisi dengan benar.',
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $dailyActivity = KartuKendali::query()
            ->selectRaw('DATE(COALESCE(waktu_pendataan, tanggal_pendataan, created_at)) as date, COUNT(*) as total')
            ->where('kecamatan', $validated['kecamatan'])
            ->where('nama_ppl', $validated['nama_ppl'])
            ->groupByRaw('DATE(COALESCE(waktu_pendataan, tanggal_pendataan, created_at))')
            ->orderByDesc('date')
            ->get()
            ->map(fn ($row): array => [
                'date' => $row->date ? Carbon::parse($row->date)->format('Y-m-d') : null,
                'total' => (int) $row->total,
            ])
            ->values();
        $dailyTargetPerPpl = 10;
        $todayTotal = (int) ($dailyActivity->firstWhere('date', Carbon::today()->toDateString())['total'] ?? 0);
        $progressPercent = round(($todayTotal / $dailyTargetPerPpl) * 100, 2);

        return response()->json([
            'kecamatan' => $validated['kecamatan'],
            'nama_ppl' => $validated['nama_ppl'],
            'total_responden' => (int) $dailyActivity->sum('total'),
            'today_total' => $todayTotal,
            'daily_target_per_ppl' => $dailyTargetPerPpl,
            'progress_percent' => $progressPercent,
            'daily_activity' => $dailyActivity,
        ]);
    }

    /**
     * Menampilkan detail satu record.
     */
    public function show(int $id): JsonResponse
    {
        $record = KartuKendali::find($id);

        if (! $record) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $record]);
    }

    /**
     * Menormalkan payload yang dikirim Google Apps Script.
     * Nama kolom Google Sheet bisa mengandung spasi dan kapitalisasi berbeda.
     *
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $map = [
            'timestamp' => 'timestamp_google',
            'timestamp_google' => 'timestamp_google',
            'tanggal_pendataan' => 'tanggal_pendataan',
            'tanggalpendataan' => 'tanggal_pendataan',
            'tanggal pendataan' => 'tanggal_pendataan',
            'waktu_pendataan' => 'waktu_pendataan',
            'waktu pendataan' => 'waktu_pendataan',
            'nama_ppl' => 'nama_ppl',
            'namappl' => 'nama_ppl',
            'nama ppl' => 'nama_ppl',
            'nama_pml' => 'nama_pml',
            'namapml' => 'nama_pml',
            'nama pml' => 'nama_pml',
            'kelurahan' => 'kelurahan',
            'sls' => 'sls',
            'nama_responden' => 'nama_responden',
            'namaresponden' => 'nama_responden',
            'nama responden' => 'nama_responden',
            'jenis_responden' => 'jenis_responden',
            'jenisresponden' => 'jenis_responden',
            'jenis responden' => 'jenis_responden',
            'dokumentasi' => 'dokumentasi',
            'keterangan' => 'keterangan',
            'kecamatan' => 'kecamatan',
            'source_sheet' => 'source_sheet',
            'sourcesheet' => 'source_sheet',
            'source sheet' => 'source_sheet',
            'external_id' => 'external_id',
            'externalid' => 'external_id',
            'external id' => 'external_id',
        ];

        $normalized = [];

        foreach ($payload as $key => $value) {
            $lookup = strtolower(trim((string) $key));
            $lookup = preg_replace('/\s+/', ' ', $lookup);

            if (isset($map[$lookup])) {
                $normalized[$map[$lookup]] = $value;
            }
        }

        if (isset($normalized['kecamatan'])) {
            $normalized['kecamatan'] = strtoupper(trim((string) $normalized['kecamatan']));
        }

        return $normalized;
    }

    /**
     * Membuat identifier unik bila external_id tidak dikirim.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveExternalId(array $data): string
    {
        if (! empty($data['external_id'])) {
            return $data['external_id'];
        }

        $signature = implode('|', [
            (string) ($data['source_sheet'] ?? ''),
            (string) ($data['timestamp_google'] ?? ''),
            (string) ($data['tanggal_pendataan'] ?? ''),
            (string) ($data['waktu_pendataan'] ?? ''),
            (string) ($data['nama_ppl'] ?? ''),
            (string) ($data['nama_pml'] ?? ''),
            (string) ($data['kelurahan'] ?? ''),
            (string) ($data['sls'] ?? ''),
            (string) ($data['nama_responden'] ?? ''),
            (string) ($data['jenis_responden'] ?? ''),
            (string) ($data['dokumentasi'] ?? ''),
            (string) ($data['keterangan'] ?? ''),
        ]);

        return 'auto-'.hash('sha256', $signature);
    }

    /**
     * Mengubah berbagai format tanggal menjadi format date Y-m-d.
     */
    private function parseTanggalPendataan(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            Log::warning('Gagal mem-parse tanggal_pendataan.', ['value' => $value]);

            return null;
        }
    }

    /**
     * Mengubah berbagai format tanggal menjadi format datetime database.
     */
    private function parseWaktuPendataan(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            Log::warning('Gagal mem-parse waktu_pendataan.', ['value' => $value]);

            return null;
        }
    }

    /**
     * Mengelompokkan variasi label jenis responden ke tiga kategori dashboard.
     */
    private function respondentType(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'KELUARGA', 'RUMAH TANGGA BIASA' => 'Keluarga',
            'USAHA' => 'Usaha',
            'CAMPURAN' => 'Campuran',
            default => '',
        };
    }

    /**
     * Menghasilkan daftar PPL dengan jumlah responden per kecamatan dan jenis.
     *
     * @return array{top: array<int, array<string, mixed>>, bottom: array<int, array<string, mixed>>}
     */
    private function pplRankings(): array
    {
        $rows = KartuKendali::query()
            ->selectRaw("kecamatan, nama_ppl, COUNT(*) as total_responden,
                SUM(CASE WHEN UPPER(TRIM(jenis_responden)) IN ('KELUARGA', 'RUMAH TANGGA BIASA') THEN 1 ELSE 0 END) as keluarga,
                SUM(CASE WHEN UPPER(TRIM(jenis_responden)) = 'USAHA' THEN 1 ELSE 0 END) as usaha,
                SUM(CASE WHEN UPPER(TRIM(jenis_responden)) = 'CAMPURAN' THEN 1 ELSE 0 END) as campuran")
            ->groupBy('kecamatan', 'nama_ppl')
            ->get()
            ->map(fn ($row): array => [
                'nama' => $row->nama_ppl,
                'kecamatan' => $row->kecamatan,
                'total_responden' => (int) $row->total_responden,
                'keluarga' => (int) $row->keluarga,
                'usaha' => (int) $row->usaha,
                'campuran' => (int) $row->campuran,
            ]);

        return [
            'top' => $rows->sortByDesc('total_responden')->values()->take(5)->all(),
            'bottom' => $rows->sortBy('total_responden')->values()->take(5)->all(),
        ];
    }
}
