<?php

namespace Tests\Feature;

use App\Models\PmlKartuKendali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmlKartuKendaliApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_pml_submission_is_saved_without_exposing_email(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);

        $response = $this->postJson('/api/pml-kartu-kendali', [
            'timestamp_google' => '09/29/2026 08:30:00',
            'email_address' => 'pml@example.test',
            'tanggal_pendataan' => '2026-09-28',
            'nama_pml' => 'PML Tangsel',
            'kecamatan' => 'PAMULANG',
            'kelurahan' => 'Benda Baru',
            'sls' => '001',
            'jenis_responden' => 'Usaha',
            'dokumentasi' => "https://drive.google.com/photo-1\nhttps://drive.google.com/photo-2",
            'source_sheet' => 'PML - Pamulang',
            'external_id' => 'pml-row-1',
        ], ['Authorization' => 'Bearer test-token']);

        $response->assertCreated()
            ->assertJsonPath('data.nama_pml', 'PML Tangsel')
            ->assertJsonPath('data.timestamp_google', '09/29/2026 08:30:00')
            ->assertJsonPath('data.sls', '001')
            ->assertJsonMissingPath('data.email_address');

        $this->assertDatabaseHas('pml_kartu_kendalis', [
            'external_id' => 'pml-row-1',
            'email_address' => 'pml@example.test',
            'kecamatan' => 'PAMULANG',
            'dokumentasi' => "https://drive.google.com/photo-1\nhttps://drive.google.com/photo-2",
        ]);
    }

    public function test_duplicate_spreadsheet_row_is_not_saved_twice(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);
        $payload = [
            'timestamp_google' => '09/29/2026 08:30:00',
            'tanggal_pendataan' => '2026-09-28',
            'nama_pml' => 'PML Tangsel',
            'kecamatan' => 'PAMULANG',
            'kelurahan' => 'Benda Baru',
            'jenis_responden' => 'Usaha',
            'dokumentasi' => 'https://drive.google.com/photo-1',
            'source_sheet' => 'PML - Pamulang',
            'external_id' => 'pml-row-duplicate',
        ];

        $this->postJson('/api/pml-kartu-kendali', $payload, [
            'Authorization' => 'Bearer test-token',
        ])->assertCreated();

        $this->postJson('/api/pml-kartu-kendali', $payload, [
            'Authorization' => 'Bearer test-token',
        ])->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('pml_kartu_kendalis', 1);
    }

    public function test_invalid_pml_submission_is_rejected(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);

        $this->postJson('/api/pml-kartu-kendali', [
            'tanggal_pendataan' => 'bukan tanggal',
            'nama_pml' => '',
            'kecamatan' => 'KOTA LAIN',
            'kelurahan' => '',
            'jenis_responden' => '',
            'dokumentasi' => '',
            'source_sheet' => '',
        ], ['Authorization' => 'Bearer test-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tanggal_pendataan',
                'nama_pml',
                'kecamatan',
                'kelurahan',
                'jenis_responden',
                'dokumentasi',
                'source_sheet',
            ]);

        $this->assertDatabaseCount('pml_kartu_kendalis', 0);
    }

    public function test_pml_submission_requires_the_ingestion_token(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);

        $this->postJson('/api/pml-kartu-kendali', [], [
            'Authorization' => 'Bearer invalid-token',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('pml_kartu_kendalis', 0);
    }

    public function test_pml_statistics_and_list_do_not_return_email_addresses(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);
        PmlKartuKendali::factory()->create([
            'nama_pml' => 'PML Statistik',
            'email_address' => 'private@example.test',
            'kecamatan' => 'SETU',
            'source_sheet' => 'PML - Setu',
            'external_id' => 'pml-stat-1',
        ]);

        $statistics = $this->getJson('/api/kartu-kendali/statistics');
        $statistics->assertOk()
            ->assertJsonPath('pml_field_activity.total_submissions', 1)
            ->assertJsonPath('pml_field_activity.total_pml', 1)
            ->assertJsonPath('pml_field_activity.latest.0.nama_pml', 'PML Statistik')
            ->assertJsonMissing(['email_address' => 'private@example.test']);

        $this->getJson('/api/pml-kartu-kendali')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_pml', 'PML Statistik')
            ->assertJsonMissing(['email_address' => 'private@example.test']);
    }
}
