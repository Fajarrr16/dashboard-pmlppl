<?php

namespace Tests\Feature;

use App\Models\KartuKendali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FasihCheckApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_data_is_displayed_in_stable_hierarchical_pages(): void
    {
        KartuKendali::factory()->createMany([
            [
                'kecamatan' => 'SERPONG',
                'kelurahan' => 'Kelurahan B',
                'sls' => 'RT 01 RW 01',
                'nama_responden' => 'Estell Olson',
                'source_sheet' => 'SETU',
                'external_id' => 'database-1',
            ],
            [
                'kecamatan' => 'SETU',
                'kelurahan' => 'Kelurahan A',
                'sls' => 'RT 01 RW 01',
                'nama_responden' => 'Velva Ward',
                'source_sheet' => 'SETU',
                'external_id' => 'database-2',
            ],
            [
                'kecamatan' => 'SETU',
                'kelurahan' => 'Kelurahan A',
                'sls' => 'RT 02 RW 01',
                'nama_responden' => 'Joel Casper',
                'source_sheet' => 'SETU',
                'external_id' => 'database-3',
            ],
            [
                'kecamatan' => 'SETU',
                'kelurahan' => 'Kelurahan A',
                'sls' => 'RT 02 RW 02',
                'nama_responden' => 'Alvena Cummerata',
                'source_sheet' => 'SETU',
                'external_id' => 'database-4',
            ],
        ]);

        $firstPage = $this->getJson('/api/fasih-checks?per_page=2&page=1');
        $secondPage = $this->getJson('/api/fasih-checks?per_page=2&page=2');

        $firstPage->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.kecamatan', 'SERPONG')
            ->assertJsonPath('data.0.sls', 'RT 01 RW 01')
            ->assertJsonPath('data.1.kecamatan', 'SETU')
            ->assertJsonPath('data.1.sls', 'RT 01 RW 01')
            ->assertJsonPath('total', 4)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('source', 'kartu_kendali');

        $secondPage->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sls', 'RT 02 RW 01')
            ->assertJsonPath('data.1.sls', 'RT 02 RW 02');
    }

    public function test_options_returns_non_empty_values_without_filters(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 01 RW 01',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'source_sheet' => 'SETU',
            'external_id' => 'options-all-1',
        ]);

        $response = $this->getJson('/api/fasih-checks/options');

        $response->assertOk()
            ->assertJsonPath('filter_options.kecamatan', ['SETU'])
            ->assertJsonPath('filter_options.kelurahan', ['Kelurahan A'])
            ->assertJsonPath('filter_options.sls', ['RT 01 RW 01'])
            ->assertJsonPath('filter_options.ppl', ['PPL A'])
            ->assertJsonPath('filter_options.pml', ['PML A']);
    }

    public function test_kelurahan_progress_summary_groups_data_by_kelurahan_and_respects_status_logic(): void
    {
        KartuKendali::factory()->createMany([
            [
                'kecamatan' => 'SETU',
                'kelurahan' => 'Kelurahan A',
                'sls' => 'RT 01 RW 01',
                'status_pemindahan' => 'sudah_dipindah',
                'source_sheet' => 'SETU',
                'external_id' => 'progress-1',
            ],
            [
                'kecamatan' => 'SETU',
                'kelurahan' => 'Kelurahan A',
                'sls' => 'RT 01 RW 02',
                'status_pemindahan' => 'belum_dipindah',
                'source_sheet' => 'SETU',
                'external_id' => 'progress-2',
            ],
            [
                'kecamatan' => 'SETU',
                'kelurahan' => 'Kelurahan B',
                'sls' => 'RT 02 RW 01',
                'status_pemindahan' => 'sudah_sesuai_sls',
                'source_sheet' => 'SETU',
                'external_id' => 'progress-3',
            ],
            [
                'kecamatan' => 'SERPONG',
                'kelurahan' => 'Kelurahan C',
                'sls' => 'RT 03 RW 01',
                'status_pemindahan' => 'belum_dipindah',
                'source_sheet' => 'SERPONG',
                'external_id' => 'progress-4',
            ],
        ]);

        $response = $this->getJson('/api/fasih-checks/kelurahan-progress?kecamatan=SETU');

        $response->assertOk()
            ->assertJsonPath('summary.total_kelurahan', 2)
            ->assertJsonPath('summary.total_responden', 3)
            ->assertJsonPath('summary.sudah_dipindah', 1)
            ->assertJsonPath('summary.sudah_sesuai_sls', 1)
            ->assertJsonPath('summary.belum_dipindah', 1)
            ->assertJsonPath('data.0.kecamatan', 'SETU')
            ->assertJsonPath('data.0.kelurahan', 'Kelurahan A')
            ->assertJsonPath('data.0.total_data', 2)
            ->assertJsonPath('data.0.sudah_dipindah', 1)
            ->assertJsonPath('data.0.belum_dipindah', 1)
            ->assertJsonPath('data.0.progress_percentage', 50)
            ->assertJsonPath('data.0.status', 'Dalam Proses')
            ->assertJsonPath('data.1.kelurahan', 'Kelurahan B')
            ->assertJsonPath('data.1.sudah_dipindah', 0)
            ->assertJsonPath('data.1.sudah_sesuai_sls', 1)
            ->assertJsonPath('data.1.belum_dipindah', 0)
            ->assertJsonPath('data.1.progress_percentage', 100)
            ->assertJsonPath('data.1.status', 'Selesai');
    }
}
