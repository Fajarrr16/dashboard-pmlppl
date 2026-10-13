<?php

namespace App\Console\Commands;

use App\Models\KartuKendali;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KartuKendaliImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kartu-kendali:import {file : Path ke file CSV} {--sheet= : Nama source_sheet yang digunakan} {--replace : Hapus data lama lalu import ulang seluruh CSV agar status sama persis dengan file CSV} {--status-only : Hanya memperbarui status pemindahan dari CSV tanpa mengganti data spreadsheet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data kartu kendali dari file CSV';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error("File tidak ditemukan atau tidak dapat dibaca: {$file}");

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $this->info('Mode replace aktif: data lama akan dihapus sebelum import CSV.');
            KartuKendali::query()->delete();
        }

        $rows = $this->parseCsv($file);

        if ($rows === []) {
            $this->error('File CSV kosong atau header tidak ditemukan.');

            return self::FAILURE;
        }

        $sourceSheet = $this->option('sheet');
        $headers = $this->normalizeHeaders(array_keys($rows[0]));
        $success = 0;
        $failed = 0;

        $this->info('Import dimulai. '.($sourceSheet ? 'source_sheet = '.$sourceSheet : ''));

        foreach ($rows as $row) {
            $normalized = array_combine($headers, array_values($row));

            if ($sourceSheet) {
                $normalized['source_sheet'] = $sourceSheet;
            } else {
                $normalized['source_sheet'] ??= $normalized['kecamatan'] ?? 'IMPORT';
            }

            $normalized = array_filter($normalized, fn ($value) => $value !== null && $value !== '');

            if ($this->option('status-only')) {
                $status = $normalized['status_pemindahan'] ?? null;

                if (! in_array($status, KartuKendali::STATUS_PEMINDAHAN, true)) {
                    $failed++;
                    $this->warn('Baris gagal: status pemindahan tidak valid.');

                    continue;
                }

                $existingRecord = $this->findStatusRecord($normalized);

                if (! $existingRecord) {
                    $failed++;
                    $this->warn('Baris gagal: record spreadsheet tidak ditemukan di CSV.');

                    continue;
                }

                if ($existingRecord->status_pemindahan !== $status) {
                    $existingRecord->update(['status_pemindahan' => $status]);
                    $success++;
                }

                continue;
            }

            if (! empty($normalized['external_id'])) {
                $externalId = $normalized['external_id'];
            } else {
                $externalId = 'csv-'.hash('sha256', implode('|', [
                    (string) ($normalized['source_sheet'] ?? ''),
                    (string) ($normalized['timestamp_google'] ?? ''),
                    (string) ($normalized['tanggal_pendataan'] ?? ''),
                    (string) ($normalized['waktu_pendataan'] ?? ''),
                    (string) ($normalized['nama_ppl'] ?? ''),
                    (string) ($normalized['nama_pml'] ?? ''),
                    (string) ($normalized['kelurahan'] ?? ''),
                    (string) ($normalized['sls'] ?? ''),
                    (string) ($normalized['nama_responden'] ?? ''),
                    (string) ($normalized['jenis_responden'] ?? ''),
                    (string) ($normalized['dokumentasi'] ?? ''),
                    (string) ($normalized['keterangan'] ?? ''),
                ]));
                $normalized['external_id'] = $externalId;
            }

            if ($this->option('replace')) {
                $existingRecord = null;
            } else {
                $existingRecord = KartuKendali::where('source_sheet', $normalized['source_sheet'] ?? null)
                    ->where('external_id', $externalId)
                    ->first();

                if (! $existingRecord && $this->isMatchingRecord($normalized)) {
                    $sameSlsMatches = KartuKendali::where('source_sheet', $normalized['source_sheet'])
                        ->where('kecamatan', $normalized['kecamatan'])
                        ->where('kelurahan', $normalized['kelurahan'])
                        ->where('sls', $normalized['sls'])
                        ->get();

                    if ($sameSlsMatches->count() === 1) {
                        $existingRecord = $sameSlsMatches->first();
                    }
                }
            }

            if ($existingRecord) {
                $status = $normalized['status_pemindahan'] ?? null;

                if ($status !== null && ! in_array($status, KartuKendali::STATUS_PEMINDAHAN, true)) {
                    $failed++;
                    $this->warn('Baris gagal: status pemindahan tidak valid.');

                    continue;
                }

                if ($status !== null && $existingRecord->status_pemindahan !== $status) {
                    $existingRecord->update([
                        'status_pemindahan' => $status,
                        'keterangan' => $normalized['keterangan'] ?? $existingRecord->keterangan,
                    ]);
                    $success++;
                }

                continue;
            }

            $validator = Validator::make($normalized, [
                'kecamatan' => ['required', 'string', 'max:50', Rule::in(KartuKendali::KECAMATAN)],
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
                'status_pemindahan' => ['nullable', Rule::in(KartuKendali::STATUS_PEMINDAHAN)],
                'source_sheet' => ['required', 'string', 'max:255'],
                'external_id' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $failed++;
                $this->warn('Baris gagal: '.$validator->errors()->first());

                continue;
            }

            try {
                $pendataanValue = $normalized['waktu_pendataan']
                    ?? $normalized['tanggal_pendataan']
                    ?? null;
                $normalized['tanggal_pendataan'] = $this->parseTanggalPendataan(
                    $normalized['tanggal_pendataan'] ?? null
                );
                $normalized['waktu_pendataan'] = $this->parseWaktuPendataan($pendataanValue);
                $normalized['timestamp_masuk'] = now();
                KartuKendali::create($normalized);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Baris gagal disimpan: {$e->getMessage()}");
            }
        }

        $this->info("[IMPORT SELESAI] Berhasil: {$success}, Gagal/duplikat: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Mem-parse file CSV menjadi array asosiatif.
     *
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $file): array
    {
        $rows = [];

        if (($handle = fopen($file, 'r')) === false) {
            return $rows;
        }

        $header = null;

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn (string $h) => trim($h), $data);

                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim($data[$i] ?? '');
            }

            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Menormalkan header CSV (spasi, kapitalisasi) supaya cocok dengan kolom database.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $map = [
            'timestamp' => 'timestamp_google',
            'timestamp_google' => 'timestamp_google',
            'tanggal_pendataan' => 'tanggal_pendataan',
            'tanggal pendataan' => 'tanggal_pendataan',
            'waktu_pendataan' => 'waktu_pendataan',
            'waktu pendataan' => 'waktu_pendataan',
            'ppl' => 'nama_ppl',
            'nama_ppl' => 'nama_ppl',
            'nama ppl' => 'nama_ppl',
            'pml' => 'nama_pml',
            'nama_pml' => 'nama_pml',
            'nama pml' => 'nama_pml',
            'kelurahan' => 'kelurahan',
            'sls' => 'sls',
            'nama_responden' => 'nama_responden',
            'nama responden' => 'nama_responden',
            'jenis_responden' => 'jenis_responden',
            'jenis responden' => 'jenis_responden',
            'dokumentasi' => 'dokumentasi',
            'keterangan' => 'keterangan',
            'status pemindahan' => 'status_pemindahan',
            'status_pemindahan' => 'status_pemindahan',
            'kecamatan' => 'kecamatan',
            'source_sheet' => 'source_sheet',
            'source sheet' => 'source_sheet',
            'external_id' => 'external_id',
            'external id' => 'external_id',
        ];

        return array_map(function (string $header) use ($map): string {
            $normalized = strtolower(preg_replace('/\s+/', ' ', trim($header)));

            return $map[$normalized] ?? $normalized;
        }, $headers);
    }

    /**
     * Memastikan CSV memiliki identitas yang dapat digunakan untuk menemukan record database.
     */
    private function isMatchingRecord(array $normalized): bool
    {
        return ! empty($normalized['kecamatan'])
            && ! empty($normalized['kelurahan'])
            && ! empty($normalized['sls'])
            && ! empty($normalized['source_sheet']);
    }

    private function findStatusRecord(array $normalized): ?KartuKendali
    {
        $query = KartuKendali::where('source_sheet', $normalized['source_sheet'] ?? null)
            ->where('kecamatan', $normalized['kecamatan'] ?? null)
            ->where('kelurahan', $normalized['kelurahan'] ?? null)
            ->where('sls', $normalized['sls'] ?? null);

        $matches = $query->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if (! empty($normalized['nama_responden'])) {
            $respondentMatches = $matches
                ->where('nama_responden', $normalized['nama_responden']);

            if ($respondentMatches->count() === 1) {
                return $respondentMatches->first();
            }
        }

        if (! empty($normalized['nama_ppl']) && ! empty($normalized['nama_pml'])) {
            $identityMatches = $matches
                ->where('nama_ppl', $normalized['nama_ppl'])
                ->where('nama_pml', $normalized['nama_pml']);

            if ($identityMatches->count() === 1) {
                return $identityMatches->first();
            }
        }

        return null;
    }

    private function parseTanggalPendataan(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseWaktuPendataan(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
