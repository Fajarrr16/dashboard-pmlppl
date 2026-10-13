<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FasihCheck;
use App\Models\FasihCheckLog;
use App\Models\KartuKendali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FasihCheckController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KartuKendali::query()
            ->select([
                'kartu_kendali.id',
                'kartu_kendali.kecamatan',
                'kartu_kendali.kelurahan',
                'kartu_kendali.sls',
                'kartu_kendali.nama_responden',
                'kartu_kendali.nama_ppl',
                'kartu_kendali.nama_pml',
                'kartu_kendali.status_pemindahan',
                'kartu_kendali.keterangan',
            ]);

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($query) use ($search): void {
                $query->where('kartu_kendali.nama_responden', 'like', "%{$search}%")
                    ->orWhere('kartu_kendali.sls', 'like', "%{$search}%");
            });
        }

        foreach ([
            'kecamatan' => 'kartu_kendali.kecamatan',
            'kelurahan' => 'kartu_kendali.kelurahan',
            'sls' => 'kartu_kendali.sls',
            'ppl' => 'kartu_kendali.nama_ppl',
            'pml' => 'kartu_kendali.nama_pml',
            'status_pemindahan' => 'kartu_kendali.status_pemindahan',
        ] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->string($param)->trim());
            }
        }

        $records = $this->sortRecords($query->get()->all());
        $page = max($request->integer('page', 1), 1);
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($records, $offset, $perPage);
        $total = count($records);

        return response()->json([
            'data' => $paginated,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($offset + $perPage, $total),
            'source' => 'kartu_kendali',
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $kecamatan = $request->string('kecamatan')->trim();
        $kelurahan = $request->string('kelurahan')->trim();

        $kecamatanQuery = $this->optionQuery('kecamatan', $kecamatan, $kelurahan);
        $kelurahanQuery = $this->optionQuery('kelurahan', $kecamatan, $kelurahan);
        $slsQuery = $this->optionQuery('sls', $kecamatan, $kelurahan);
        $pplQuery = $this->optionQuery('nama_ppl', $kecamatan, $kelurahan);
        $pmlQuery = $this->optionQuery('nama_pml', $kecamatan, $kelurahan);

        return response()->json([
            'filter_options' => [
                'kecamatan' => $kecamatanQuery->distinct()->orderBy('kecamatan')->pluck('kecamatan')->values()->all(),
                'kelurahan' => $kelurahanQuery->distinct()->orderBy('kelurahan')->pluck('kelurahan')->values()->all(),
                'sls' => $slsQuery->distinct()->orderBy('sls')->pluck('sls')->values()->all(),
                'ppl' => $pplQuery->distinct()->orderBy('nama_ppl')->pluck('nama_ppl')->values()->all(),
                'pml' => $pmlQuery->distinct()->orderBy('nama_pml')->pluck('nama_pml')->values()->all(),
            ],
        ]);
    }

    private function optionQuery(string $column, string $kecamatan, string $kelurahan): Builder
    {
        $query = KartuKendali::query()
            ->whereNotNull($column)
            ->where($column, '!=', '');

        if ($kecamatan !== '') {
            $query->where('kecamatan', $kecamatan);
        }

        if ($kelurahan !== '' && $column !== 'kecamatan') {
            $query->where('kelurahan', $kelurahan);
        }

        return $query;
    }

    public function summary(Request $request): JsonResponse
    {
        $query = KartuKendali::query();

        foreach ([
            'kecamatan' => 'kecamatan',
            'kelurahan' => 'kelurahan',
            'sls' => 'sls',
            'ppl' => 'nama_ppl',
            'pml' => 'nama_pml',
            'status_pemindahan' => 'status_pemindahan',
        ] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->string($param)->trim());
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($query) use ($search): void {
                $query->where('nama_responden', 'like', "%{$search}%")
                    ->orWhere('sls', 'like', "%{$search}%");
            });
        }

        $records = $query->get();
        $statusCounts = $records->groupBy('status_pemindahan')->map(fn ($group) => $group->count());

        return response()->json([
            'total_data' => $records->count(),
            'sudah_dicek' => (int) ($statusCounts['sudah_dipindah'] ?? 0),
            'sesuai' => (int) ($statusCounts['sudah_sesuai_sls'] ?? 0),
            'tidak_sesuai' => 0,
            'tidak_ditemukan' => 0,
            'belum_dicek' => (int) ($statusCounts['belum_dipindah'] ?? 0),
            'source' => 'kartu_kendali',
        ]);
    }

    public function kelurahanProgress(Request $request): JsonResponse
    {
        $query = KartuKendali::query();

        foreach ([
            'kecamatan' => 'kecamatan',
            'kelurahan' => 'kelurahan',
            'sls' => 'sls',
            'ppl' => 'nama_ppl',
            'pml' => 'nama_pml',
            'status_pemindahan' => 'status_pemindahan',
        ] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->string($param)->trim());
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($query) use ($search): void {
                $query->where('nama_responden', 'like', "%{$search}%")
                    ->orWhere('sls', 'like', "%{$search}%");
            });
        }

        $records = $this->sortRecords($query->get()->all());
        $grouped = collect($records)->groupBy(function (KartuKendali $record): string {
            return ($record->kecamatan ?? 'Lainnya').'|'.($record->kelurahan ?? 'Lainnya');
        });

        $rows = $grouped->map(function ($items) {
            $total = count($items);
            $sudahDipindah = $items->filter(fn ($item) => $item->status_pemindahan === 'sudah_dipindah')->count();
            $sudahSesuaiSls = $items->filter(fn ($item) => $item->status_pemindahan === 'sudah_sesuai_sls')->count();
            $belumDipindah = $items->filter(fn ($item) => $item->status_pemindahan === 'belum_dipindah')->count();
            $completed = $sudahDipindah + $sudahSesuaiSls;
            $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

            if ($percentage >= 100) {
                $status = 'Selesai';
            } elseif ($percentage > 0) {
                $status = 'Dalam Proses';
            } else {
                $status = 'Belum Mulai';
            }

            $first = $items->first();

            return [
                'kecamatan' => $first?->kecamatan ?? 'Lainnya',
                'kelurahan' => $first?->kelurahan ?? 'Lainnya',
                'total_data' => $total,
                'sudah_dipindah' => $sudahDipindah,
                'sudah_sesuai_sls' => $sudahSesuaiSls,
                'belum_dipindah' => $belumDipindah,
                'completed_data' => $completed,
                'remaining_data' => max($total - $completed, 0),
                'progress_percentage' => $percentage,
                'status' => $status,
            ];
        })->values()->all();

        usort($rows, function (array $left, array $right): int {
            $comparison = strnatcasecmp($left['kecamatan'], $right['kecamatan']);
            if ($comparison !== 0) {
                return $comparison;
            }

            return strnatcasecmp($left['kelurahan'], $right['kelurahan']);
        });

        $totalKelurahan = count($rows);
        $totalResponden = collect($rows)->sum('total_data');
        $totalSudahDipindah = collect($rows)->sum('sudah_dipindah');
        $totalSudahSesuaiSls = collect($rows)->sum('sudah_sesuai_sls');
        $totalBelumDipindah = collect($rows)->sum('belum_dipindah');

        return response()->json([
            'summary' => [
                'total_kelurahan' => $totalKelurahan,
                'total_responden' => $totalResponden,
                'sudah_dipindah' => $totalSudahDipindah,
                'sudah_sesuai_sls' => $totalSudahSesuaiSls,
                'belum_dipindah' => $totalBelumDipindah,
                'completed_data' => $totalSudahDipindah + $totalSudahSesuaiSls,
                'completion_rate' => $totalResponden > 0 ? round((($totalSudahDipindah + $totalSudahSesuaiSls) / $totalResponden) * 100, 2) : 0.0,
            ],
            'data' => $rows,
        ]);
    }

    public function check(Request $request, int $id): JsonResponse
    {
        $kartuKendali = KartuKendali::find($id);

        if (! $kartuKendali) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $status = FasihCheck::STATUS_SESUAI;
        $fieldTidakSesuai = [];

        $check = FasihCheck::updateOrCreate(
            ['kartu_kendali_id' => $kartuKendali->id],
            [
                'status' => $status,
                'field_tidak_sesuai' => null,
                'petugas' => $request->user()?->name ?? 'System',
                'checked_at' => now(),
            ],
        );

        return response()->json([
            'kartu_kendali' => $kartuKendali,
            'status' => $status,
            'field_tidak_sesuai' => $fieldTidakSesuai,
            'check' => $check,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $kartuKendali = KartuKendali::find($id);

        if (! $kartuKendali) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'status_pemindahan' => ['required', Rule::in(KartuKendali::STATUS_PEMINDAHAN)],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $statusSebelumnya = $kartuKendali->status_pemindahan;

        // Prevent no-op updates
        if ($statusSebelumnya === $validated['status_pemindahan']) {
            return response()->json([
                'message' => 'Status tidak berubah.',
                'data' => $kartuKendali,
            ]);
        }

        $kartuKendali->update([
            'status_pemindahan' => $validated['status_pemindahan'],
            'keterangan' => $validated['keterangan'] ?? $kartuKendali->keterangan,
        ]);

        FasihCheckLog::create([
            'kartu_kendali_id' => $kartuKendali->id,
            'status_sebelumnya' => $statusSebelumnya,
            'status_baru' => $validated['status_pemindahan'],
            'petugas' => $request->user()?->name ?? 'System',
            'keterangan' => $validated['keterangan'] ?? null,
            'changed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Status berhasil diperbarui.',
            'data' => $kartuKendali,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $kartuKendali = KartuKendali::query()
            ->leftJoin('fasih_checks', 'fasih_checks.kartu_kendali_id', '=', 'kartu_kendali.id')
            ->select([
                'kartu_kendali.*',
                'fasih_checks.status as status_pengecekan',
                'fasih_checks.field_tidak_sesuai',
                'fasih_checks.keterangan as hasil_keterangan',
                'fasih_checks.petugas',
                'fasih_checks.checked_at',
            ])
            ->where('kartu_kendali.id', $id)
            ->first();

        if (! $kartuKendali) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return response()->json([
            'kartu_kendali' => $kartuKendali,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $records = $this->sortRecords(
            KartuKendali::query()
                ->select([
                    'kartu_kendali.id',
                    'kartu_kendali.kecamatan',
                    'kartu_kendali.kelurahan',
                    'kartu_kendali.sls',
                    'kartu_kendali.nama_responden',
                    'kartu_kendali.nama_ppl',
                    'kartu_kendali.nama_pml',
                    'kartu_kendali.status_pemindahan',
                    'kartu_kendali.keterangan',
                ])
                ->get()
                ->all(),
        );
        $headers = [
            'Kecamatan',
            'Kelurahan',
            'SLS',
            'Nama Responden',
            'PPL',
            'PML',
            'Status Pemindahan',
            'Keterangan',
        ];
        $rows = [$headers];

        foreach ($records as $record) {
            $rows[] = [
                $record->kecamatan,
                $record->kelurahan,
                $record->sls,
                $record->nama_responden,
                $record->nama_ppl,
                $record->nama_pml,
                $record->status_pemindahan,
                $record->keterangan,
            ];
        }

        return response()->json([
            'filename' => 'bahan-realokasi-cek-fasih-'.date('Y-m-d-His').'.csv',
            'csv' => $this->arrayToCsv($rows),
            'total' => count($records),
        ]);
    }

    public function logs(int $id): JsonResponse
    {
        $logs = FasihCheckLog::query()
            ->where('kartu_kendali_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($logs);
    }

    /**
     * @return array<int, KartuKendali>
     */
    private function sortRecords($records): array
    {
        usort($records, function (KartuKendali $left, KartuKendali $right): int {
            $comparison = strnatcasecmp($left->kecamatan, $right->kecamatan);
            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = strnatcasecmp($left->kelurahan, $right->kelurahan);
            if ($comparison !== 0) {
                return $comparison;
            }

            [$leftRw, $leftRt] = $this->parseRwRt($left->sls);
            [$rightRw, $rightRt] = $this->parseRwRt($right->sls);

            return [$leftRw, $leftRt] <=> [$rightRw, $rightRt]
                ?: strnatcasecmp($left->sls, $right->sls);
        });

        return $records;
    }

    private function parseRwRt(string $sls): array
    {
        $text = trim($sls);
        preg_match('/RW\s*([0-9]+)/i', $text, $rwMatch);
        preg_match('/RT\s*([0-9]+)/i', $text, $rtMatch);

        $rw = isset($rwMatch[1]) ? (int) $rwMatch[1] : 0;
        $rt = isset($rtMatch[1]) ? (int) $rtMatch[1] : 0;

        if ($rw === 0 && $rt === 0) {
            $digits = preg_replace('/\D+/', '', $text);
            if ($digits !== '') {
                $rw = (int) $digits;
                $rt = (int) $digits;
            }
        }

        return [$rw, $rt];
    }

    private function arrayToCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
