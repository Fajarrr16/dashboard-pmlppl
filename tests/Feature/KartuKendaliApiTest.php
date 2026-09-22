<?php

namespace Tests\Feature;

use App\Models\KartuKendali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuKendaliApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_accepts_valid_normalized_payload(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);

        $response = $this->postJson('/api/kartu-kendali', [
            'WILAYAH' => 'SETU',
            'Timestamp' => '09/22/2026 08:30:00',
            'Tanggal Pendataan' => '2026-09-22',
            'Nama PPL' => 'PPL Test',
            'Nama PML' => 'PML Test',
            'Kelurahan' => 'Setu',
            'SLS' => '001',
            'Nama Responden' => 'Responden Test',
            'Jenis Responden' => 'Usaha',
            'Dokumentasi' => 'https://drive.google.com/test',
            'Keterangan' => 'Data uji',
            'Source Sheet' => 'SETU',
            'External ID' => 'test-1',
        ], ['Authorization' => 'Bearer test-token']);

        $response->assertCreated()
            ->assertJsonPath('data.external_id', 'test-1')
            ->assertJsonPath('data.wilayah', 'SETU');

        $this->assertDatabaseHas('kartu_kendali', [
            'source_sheet' => 'SETU',
            'external_id' => 'test-1',
            'nama_responden' => 'Responden Test',
        ]);
    }

    public function test_api_rejects_invalid_token(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);

        $response = $this->postJson('/api/kartu-kendali', [], [
            'Authorization' => 'Bearer wrong-token',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('kartu_kendali', 0);
    }

    public function test_api_rejects_invalid_payload(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);

        $response = $this->postJson('/api/kartu-kendali', [
            'wilayah' => 'WILAYAH TIDAK VALID',
        ], ['Authorization' => 'Bearer test-token']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'wilayah',
                'nama_ppl',
                'nama_pml',
                'nama_responden',
                'source_sheet',
            ]);
    }

    public function test_api_ignores_duplicate_payload(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);
        $payload = [
            'wilayah' => 'SETU',
            'nama_ppl' => 'PPL Test',
            'nama_pml' => 'PML Test',
            'nama_responden' => 'Responden Test',
            'source_sheet' => 'SETU',
            'external_id' => 'duplicate-1',
        ];

        $this->postJson('/api/kartu-kendali', $payload, [
            'Authorization' => 'Bearer test-token',
        ])->assertCreated();

        $response = $this->postJson('/api/kartu-kendali', $payload, [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('kartu_kendali', 1);
    }

    public function test_api_lists_records(): void
    {
        KartuKendali::factory()->create([
            'wilayah' => 'SETU',
            'source_sheet' => 'SETU',
            'external_id' => 'list-1',
        ]);

        $response = $this->getJson('/api/kartu-kendali?per_page=5');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.wilayah', 'SETU');
    }

    public function test_api_filters_by_wilayah(): void
    {
        KartuKendali::factory()->create([
            'wilayah' => 'SETU',
            'source_sheet' => 'SETU',
            'external_id' => 'filter-setu',
        ]);
        KartuKendali::factory()->create([
            'wilayah' => 'SERPONG',
            'source_sheet' => 'SERPONG',
            'external_id' => 'filter-serpong',
        ]);

        $response = $this->getJson('/api/kartu-kendali?wilayah=SERPONG');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.wilayah', 'SERPONG');
    }

    public function test_statistics_returns_distinct_filter_options(): void
    {
        KartuKendali::factory()->create([
            'nama_ppl' => 'PPL B',
            'nama_pml' => 'PML B',
            'kelurahan' => 'Kelurahan B',
            'sls' => '002',
            'source_sheet' => 'SETU',
            'external_id' => 'options-1',
        ]);
        KartuKendali::factory()->create([
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'kelurahan' => 'Kelurahan A',
            'sls' => '001',
            'source_sheet' => 'SERPONG',
            'external_id' => 'options-2',
        ]);
        KartuKendali::factory()->create([
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'kelurahan' => 'Kelurahan A',
            'sls' => '001',
            'source_sheet' => 'PAMULANG',
            'external_id' => 'options-3',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics');

        $response->assertOk()
            ->assertJsonPath('filter_options.ppl', ['PPL A', 'PPL B'])
            ->assertJsonPath('filter_options.pml', ['PML A', 'PML B'])
            ->assertJsonPath('filter_options.kelurahan', ['Kelurahan A', 'Kelurahan B'])
            ->assertJsonPath('filter_options.sls', ['001', '002']);
    }

    public function test_statistics_returns_expected_aggregates(): void
    {
        KartuKendali::factory()->create([
            'wilayah' => 'SETU',
            'nama_ppl' => 'PPL Satu',
            'nama_pml' => 'PML Satu',
            'sls' => '001',
            'jenis_responden' => 'Usaha',
            'source_sheet' => 'SETU',
            'external_id' => 'stats-1',
        ]);
        KartuKendali::factory()->create([
            'wilayah' => 'SERPONG',
            'nama_ppl' => 'PPL Satu',
            'nama_pml' => 'PML Dua',
            'sls' => '002',
            'jenis_responden' => 'Usaha',
            'source_sheet' => 'SERPONG',
            'external_id' => 'stats-2',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics');

        $response->assertOk()
            ->assertJsonPath('total_responden', 2)
            ->assertJsonPath('total_ppl', 1)
            ->assertJsonPath('total_pml', 2)
            ->assertJsonPath('total_sls', 2)
            ->assertJsonPath('per_wilayah.SETU', 1)
            ->assertJsonPath('per_wilayah.SERPONG', 1)
            ->assertJsonPath('per_wilayah_detail.SETU.total_responden', 1)
            ->assertJsonPath('per_wilayah_detail.SETU.total_ppl', 1)
            ->assertJsonPath('per_wilayah_detail.SETU.total_pml', 1)
            ->assertJsonPath('per_wilayah_detail.SETU.total_sls', 1)
            ->assertJsonPath('per_wilayah_detail.SERPONG.total_responden', 1)
            ->assertJsonPath('latest.external_id', 'stats-2');
    }
}
