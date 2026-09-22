<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KartuKendali;
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
     * Daftar wilayah yang didukung dashboard.
     *
     * @var array<int, string>
     */
    public const WILAYAH = KartuKendali::WILAYAH;

    /**
     * Menerima data baru dari Google Apps Script.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->normalizePayload($request->all());

        $rules = [
            'wilayah' => ['required', 'string', 'max:50', Rule::in(self::WILAYAH)],
            'timestamp_google' => ['nullable', 'string', 'max:255'],
            'tanggal_pendataan' => ['nullable', 'date'],
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

        $validated['tanggal_pendataan'] = $this->parseTanggalPendataan(
            $validated['tanggal_pendataan'] ?? null
        );

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
                    ->orWhere('kelurahan', 'like', "%{$search}%")
                    ->orWhere('sls', 'like', "%{$search}%");
            });
        }

        foreach ([
            'wilayah' => 'wilayah',
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

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal_pendataan', $request->string('tanggal'));
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 5), 100);

        $records = $query->orderByDesc('tanggal_pendataan')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($records);
    }

    /**
     * Menampilkan statistik agregat.
     */
    public function statistics(): JsonResponse
    {
        $totalResponden = KartuKendali::count();
        $totalPpl = KartuKendali::distinct('nama_ppl')->count('nama_ppl');
        $totalPml = KartuKendali::distinct('nama_pml')->count('nama_pml');
        $totalSls = KartuKendali::distinct('sls')->count('sls');

        $perWilayah = KartuKendali::query()
            ->selectRaw('wilayah, COUNT(*) as total')
            ->groupBy('wilayah')
            ->orderByDesc('total')
            ->pluck('total', 'wilayah');
        $perWilayahDetail = KartuKendali::query()
            ->selectRaw('
                wilayah,
                COUNT(*) as total_responden,
                COUNT(DISTINCT nama_ppl) as total_ppl,
                COUNT(DISTINCT nama_pml) as total_pml,
                COUNT(DISTINCT sls) as total_sls
            ')
            ->groupBy('wilayah')
            ->get()
            ->keyBy('wilayah');

        $perJenisResponden = KartuKendali::query()
            ->selectRaw('jenis_responden, COUNT(*) as total')
            ->groupBy('jenis_responden')
            ->orderByDesc('total')
            ->pluck('total', 'jenis_responden');

        $filterOptions = collect([
            'ppl' => 'nama_ppl',
            'pml' => 'nama_pml',
            'kelurahan' => 'kelurahan',
            'sls' => 'sls',
        ])->mapWithKeys(function (string $column, string $key): array {
            $values = KartuKendali::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->values()
                ->all();

            return [$key => $values];
        });

        $latest = KartuKendali::query()
            ->orderByDesc('id')
            ->first();

        $perWilayahDefault = collect(self::WILAYAH)->mapWithKeys(
            fn (string $w) => [$w => (int) ($perWilayah[$w] ?? 0)]
        );
        $perWilayahDetailDefault = collect(self::WILAYAH)->mapWithKeys(
            function (string $wilayah) use ($perWilayahDetail): array {
                $detail = $perWilayahDetail->get($wilayah);

                return [$wilayah => [
                    'total_responden' => (int) ($detail->total_responden ?? 0),
                    'total_ppl' => (int) ($detail->total_ppl ?? 0),
                    'total_pml' => (int) ($detail->total_pml ?? 0),
                    'total_sls' => (int) ($detail->total_sls ?? 0),
                ]];
            }
        );

        return response()->json([
            'total_responden' => $totalResponden,
            'total_ppl' => $totalPpl,
            'total_pml' => $totalPml,
            'total_sls' => $totalSls,
            'per_wilayah' => $perWilayahDefault,
            'per_wilayah_detail' => $perWilayahDetailDefault,
            'per_jenis_responden' => $perJenisResponden,
            'filter_options' => $filterOptions,
            'latest' => $latest,
            'updated_at' => now()->toIso8601String(),
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
            'wilayah' => 'wilayah',
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
}
