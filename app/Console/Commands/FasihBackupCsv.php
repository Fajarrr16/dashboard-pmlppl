<?php

namespace App\Console\Commands;

use App\Models\KartuKendali;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:fasih-backup-csv')]
#[Description('Backup daily FASIH data to CSV file')]
class FasihBackupCsv extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = now()->format('Y-m-d');
        $filename = "fasih-backup-{$date}.csv";
        $path = "backups/{$filename}";

        $data = KartuKendali::query()
            ->orderBy('kecamatan')
            ->orderBy('kelurahan')
            ->orderByRaw("CAST(SUBSTR(sls, INSTR(sls, 'RT ') + 3, INSTR(sls, 'RW ') - INSTR(sls, 'RT ') - 3) AS INTEGER)")
            ->orderByRaw("CAST(SUBSTR(sls, INSTR(sls, 'RW ') + 3) AS INTEGER)")
            ->get();

        if ($data->isEmpty()) {
            $this->warn('Tidak ada data untuk di-backup.');

            return;
        }

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'No', 'Kecamatan', 'Kelurahan', 'SLS', 'Nama Responden',
            'PPL', 'PML', 'Status Pemindahan', 'Keterangan', 'Tanggal Pendataan',
        ]);

        $data->each(function ($row, $index) use ($handle) {
            fputcsv($handle, [
                $index + 1,
                $row->kecamatan,
                $row->kelurahan,
                $row->sls,
                $row->nama_responden,
                $row->nama_ppl,
                $row->nama_pml,
                $row->status_pemindahan,
                $row->keterangan,
                $row->tanggal_pendataan?->format('Y-m-d'),
            ]);
        });

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($path, $csv);

        $this->info("Backup selesai: {$filename} ({$data->count()} data)");
    }
}
