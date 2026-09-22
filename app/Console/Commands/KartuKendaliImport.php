<?php

namespace App\Console\Commands;

use App\Models\KartuKendali;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KartuKendaliImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kartu-kendali:import {file : Path ke file CSV} {--sheet= : Nama source_sheet yang digunakan}';

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
                $normalized['source_sheet'] ??= $normalized['wilayah'] ?? 'IMPORT';
            }

            $normalized = array_filter($normalized, fn ($value) => $value !== null && $value !== '');

            if (! empty($normalized['external_id'])) {
                $externalId = $normalized['external_id'];
            } else {
                $externalId = 'csv-'.hash('sha256', implode('|', [
                    (string) ($normalized['source_sheet'] ?? ''),
                    (string) ($normalized['timestamp_google'] ?? ''),
                    (string) ($normalized['tanggal_pendataan'] ?? ''),
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

            if (KartuKendali::where('source_sheet', $normalized['source_sheet'] ?? null)
                ->where('external_id', $externalId)
                ->exists()) {
                $failed++;

                continue;
            }

            $validator = Validator::make($normalized, [
                'wilayah' => ['required', 'string', 'max:50', Rule::in(KartuKendali::WILAYAH)],
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
                'external_id' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $failed++;
                $this->warn('Baris gagal: '.$validator->errors()->first());

                continue;
            }

            try {
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
            'nama_ppl' => 'nama_ppl',
            'nama ppl' => 'nama_ppl',
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
            'wilayah' => 'wilayah',
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
}
