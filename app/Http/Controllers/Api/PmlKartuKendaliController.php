<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmlKartuKendali;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PmlKartuKendaliController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'timestamp_google' => ['nullable', 'string', 'max:255'],
            'email_address' => ['nullable', 'email', 'max:255'],
            'tanggal_pendataan' => ['required', 'date'],
            'nama_pml' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', Rule::in(PmlKartuKendali::KECAMATAN)],
            'kelurahan' => ['required', 'string', 'max:255'],
            'sls' => ['nullable', 'string', 'max:255'],
            'jenis_responden' => ['required', 'string', 'max:255'],
            'dokumentasi' => ['required', 'string', 'max:20000'],
            'source_sheet' => ['required', 'string', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi data PML gagal.',
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $validated['external_id'] = $validated['external_id']
            ?? 'pml-'.hash('sha256', implode('|', [
                $validated['source_sheet'],
                $validated['timestamp_google'] ?? '',
                $validated['email_address'] ?? '',
                $validated['tanggal_pendataan'],
                $validated['nama_pml'],
                $validated['kecamatan'],
                $validated['kelurahan'],
                $validated['sls'] ?? '',
                $validated['jenis_responden'],
                $validated['dokumentasi'],
            ]));
        $validated['timestamp_masuk'] = now();

        $existing = PmlKartuKendali::query()
            ->where('source_sheet', $validated['source_sheet'])
            ->where('external_id', $validated['external_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Data PML sudah diterima sebelumnya.',
                'data' => $this->publicRecord($existing),
                'duplicate' => true,
            ]);
        }

        try {
            $record = PmlKartuKendali::query()->create($validated);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Data PML sudah diterima sebelumnya.',
                'duplicate' => true,
            ]);
        }

        return response()->json([
            'message' => 'Data PML berhasil disimpan.',
            'data' => $this->publicRecord($record),
        ], JsonResponse::HTTP_CREATED);
    }

    public function index(Request $request): JsonResponse
    {
        $query = PmlKartuKendali::query();

        foreach (['kecamatan', 'nama_pml', 'kelurahan', 'jenis_responden'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->trim()->toString());
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_pendataan', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_pendataan', '<=', $request->date('date_to'));
        }

        $perPage = min(max($request->integer('per_page', 15), 5), 100);
        $records = $query
            ->select([
                'id', 'timestamp_google', 'tanggal_pendataan', 'nama_pml', 'kecamatan',
                'kelurahan', 'sls', 'jenis_responden', 'dokumentasi', 'source_sheet',
                'external_id', 'timestamp_masuk', 'created_at',
            ])
            ->orderByDesc('tanggal_pendataan')
            ->orderByDesc('timestamp_google')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($records);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicRecord(PmlKartuKendali $record): array
    {
        return $record->only([
            'id', 'timestamp_google', 'tanggal_pendataan', 'nama_pml', 'kecamatan',
            'kelurahan', 'sls', 'jenis_responden', 'dokumentasi', 'source_sheet',
            'external_id', 'timestamp_masuk', 'created_at',
        ]);
    }
}
