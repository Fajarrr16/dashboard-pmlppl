<?php

namespace Tests\Feature;

use App\Models\KartuKendali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuKendaliImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_record_is_updated_from_csv_status(): void
    {
        $record = KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 01 RW 01',
            'status_pemindahan' => 'belum_dipindah',
            'source_sheet' => 'SETU',
            'external_id' => 'gas-existing-record',
        ]);

        $csvPath = tempnam(sys_get_temp_dir(), 'kartu-kendali-');
        file_put_contents($csvPath, implode("\n", [
            'Kecamatan,Kelurahan,SLS,"Nama Responden",PPL,PML,"Status Pemindahan",Keterangan',
            'SETU,Kelurahan A,RT 01 RW 01,Responden Test,PPL Test,PML Test,sudah_dipindah,Telah dipindahkan',
        ]));

        try {
            $this->artisan('kartu-kendali:import', [
                'file' => $csvPath,
                '--sheet' => 'SETU',
            ])->expectsOutputToContain('[IMPORT SELESAI]')->assertSuccessful();
        } finally {
            unlink($csvPath);
        }

        $record->refresh();

        $this->assertSame('sudah_dipindah', $record->status_pemindahan);
        $this->assertSame('Telah dipindahkan', $record->keterangan);
    }

    public function test_status_only_import_updates_status_without_replacing_spreadsheet_record(): void
    {
        $record = KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 01 RW 01',
            'nama_ppl' => 'PPL Spreadsheet',
            'nama_pml' => 'PML Spreadsheet',
            'nama_responden' => 'Responden Spreadsheet',
            'status_pemindahan' => 'belum_dipindah',
            'source_sheet' => 'SETU',
            'external_id' => 'gas-spreadsheet-record',
            'keterangan' => 'Data utama dari spreadsheet',
        ]);

        $csvPath = tempnam(sys_get_temp_dir(), 'kartu-kendali-');
        file_put_contents($csvPath, implode("\n", [
            'Kecamatan,Kelurahan,SLS,"Nama Responden",PPL,PML,"Status Pemindahan",Keterangan',
            'SETU,Kelurahan A,RT 01 RW 01,Responden Spreadsheet,PPL Spreadsheet,PML Spreadsheet,sudah_dipindah,Status dari CSV',
        ]));

        try {
            $this->artisan('kartu-kendali:import', [
                'file' => $csvPath,
                '--sheet' => 'SETU',
                '--status-only' => true,
            ])->expectsOutputToContain('[IMPORT SELESAI]')->assertSuccessful();
        } finally {
            unlink($csvPath);
        }

        $record->refresh();

        $this->assertSame('sudah_dipindah', $record->status_pemindahan);
        $this->assertSame('Data utama dari spreadsheet', $record->keterangan);
        $this->assertSame('gas-spreadsheet-record', $record->external_id);
    }

    public function test_filtered_summary_counts_follow_active_filters(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 01 RW 01',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'nama_responden' => 'Responden A',
            'status_pemindahan' => 'belum_dipindah',
        ]);

        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 01 RW 01',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'nama_responden' => 'Responden B',
            'status_pemindahan' => 'sudah_dipindah',
        ]);

        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'kelurahan' => 'Kelurahan B',
            'sls' => 'RT 02 RW 02',
            'nama_ppl' => 'PPL B',
            'nama_pml' => 'PML B',
            'nama_responden' => 'Responden C',
            'status_pemindahan' => 'sudah_sesuai_sls',
        ]);

        $response = $this->getJson('/api/fasih-checks/summary?kecamatan=SETU&status_pemindahan=sudah_dipindah');

        $response->assertOk();
        $this->assertSame(1, $response->json('total_data'));
        $this->assertSame(1, $response->json('sudah_dicek'));
        $this->assertSame(0, $response->json('belum_dicek'));
        $this->assertSame(0, $response->json('sesuai'));
    }

    public function test_replace_mode_rebuilds_data_from_csv(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan Lama',
            'sls' => 'RT 99 RW 99',
            'status_pemindahan' => 'belum_dipindah',
            'source_sheet' => 'SETU',
            'external_id' => 'old-record',
        ]);

        $csvPath = tempnam(sys_get_temp_dir(), 'kartu-kendali-');
        file_put_contents($csvPath, implode("\n", [
            'Kecamatan,Kelurahan,SLS,"Nama Responden",PPL,PML,"Status Pemindahan",Keterangan',
            'SETU,Kelurahan Baru,RT 01 RW 01,Responden Baru,PPL Baru,PML Baru,sudah_dipindah,Status baru',
            'SETU,Kelurahan Baru,RT 02 RW 02,Responden Kedua,PPL Baru,PML Baru,belum_dipindah,Status lama',
        ]));

        try {
            $this->artisan('kartu-kendali:import', [
                'file' => $csvPath,
                '--sheet' => 'SETU',
                '--replace' => true,
            ])->expectsOutputToContain('[IMPORT SELESAI]')->assertSuccessful();
        } finally {
            unlink($csvPath);
        }

        $this->assertSame(2, KartuKendali::count());
        $this->assertDatabaseHas('kartu_kendali', ['kelurahan' => 'Kelurahan Baru', 'status_pemindahan' => 'sudah_dipindah']);
        $this->assertDatabaseMissing('kartu_kendali', ['kelurahan' => 'Kelurahan Lama']);
    }

    public function test_multiple_respondents_in_same_sls_are_all_imported(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'kartu-kendali-');
        file_put_contents($csvPath, implode("\n", [
            'Kecamatan,Kelurahan,SLS,"Nama Responden",PPL,PML,"Status Pemindahan",Keterangan',
            'SETU,Kelurahan Sama,RT 01 RW 01,Responden A,PPL A,PML A,belum_dipindah,Ket A',
            'SETU,Kelurahan Sama,RT 01 RW 01,Responden B,PPL A,PML A,sudah_dipindah,Ket B',
        ]));

        try {
            $this->artisan('kartu-kendali:import', [
                'file' => $csvPath,
                '--sheet' => 'SETU',
                '--replace' => true,
            ])->expectsOutputToContain('[IMPORT SELESAI]')->assertSuccessful();
        } finally {
            unlink($csvPath);
        }

        $this->assertSame(2, KartuKendali::count());
        $this->assertDatabaseHas('kartu_kendali', ['nama_responden' => 'Responden A', 'status_pemindahan' => 'belum_dipindah']);
        $this->assertDatabaseHas('kartu_kendali', ['nama_responden' => 'Responden B', 'status_pemindahan' => 'sudah_dipindah']);
    }
}
