<?php

namespace Tests\Feature;

use App\Models\KartuKendali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuKendaliApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_accepts_valid_normalized_payload_and_stores_timestamps(): void
    {
        config(['services.kartu_kendali.token' => 'test-token']);

        $response = $this->postJson('/api/kartu-kendali', [
            'KECAMATAN' => 'SETU',
            'Timestamp' => '09/22/2026 08:30:00',
            'Tanggal Pendataan' => '2026-09-22',
            'Waktu Pendataan' => '2026-09-22 08:30:00',
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
            ->assertJsonPath('data.kecamatan', 'SETU')
            ->assertJsonPath('data.timestamp_google', '09/22/2026 08:30:00')
            ->assertJsonPath('data.waktu_pendataan', '2026-09-22T01:30:00.000000Z');

        $this->assertDatabaseHas('kartu_kendali', [
            'source_sheet' => 'SETU',
            'external_id' => 'test-1',
            'kecamatan' => 'SETU',
            'nama_responden' => 'Responden Test',
        ]);
        $this->assertNotNull(KartuKendali::query()->firstOrFail()->timestamp_masuk);
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
            'kecamatan' => 'KECAMATAN TIDAK VALID',
        ], ['Authorization' => 'Bearer test-token']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'kecamatan',
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
            'kecamatan' => 'SETU',
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

        $response->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('kartu_kendali', 1);
    }

    public function test_api_lists_records(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'source_sheet' => 'SETU',
            'external_id' => 'list-1',
        ]);

        $response = $this->getJson('/api/kartu-kendali?per_page=5');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kecamatan', 'SETU');
    }

    public function test_api_filters_by_kecamatan(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'source_sheet' => 'SETU',
            'external_id' => 'filter-setu',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'source_sheet' => 'SERPONG',
            'external_id' => 'filter-serpong',
        ]);

        $response = $this->getJson('/api/kartu-kendali?kecamatan=SERPONG');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kecamatan', 'SERPONG');
    }

    public function test_api_filters_by_date_range(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'tanggal_pendataan' => '2026-09-05',
            'source_sheet' => 'SETU',
            'external_id' => 'date-range-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'tanggal_pendataan' => '2026-09-12',
            'source_sheet' => 'SERPONG',
            'external_id' => 'date-range-2',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'PAMULANG',
            'tanggal_pendataan' => '2026-09-20',
            'source_sheet' => 'PAMULANG',
            'external_id' => 'date-range-3',
        ]);

        $response = $this->getJson('/api/kartu-kendali?date_from=2026-09-01&date_to=2026-09-15');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.kecamatan', 'SERPONG')
            ->assertJsonPath('data.1.kecamatan', 'SETU');
    }

    public function test_api_orders_records_globally_by_kecamatan_kelurahan_and_sls(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 02 RW 02',
            'source_sheet' => 'SETU',
            'external_id' => 'order-3',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 01 RW 01',
            'source_sheet' => 'SETU',
            'external_id' => 'order-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'kelurahan' => 'Kelurahan A',
            'sls' => 'RT 02 RW 01',
            'source_sheet' => 'SETU',
            'external_id' => 'order-2',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'kelurahan' => 'Kelurahan B',
            'sls' => 'RT 01 RW 01',
            'source_sheet' => 'SERPONG',
            'external_id' => 'order-4',
        ]);

        $response = $this->getJson('/api/fasih-checks?per_page=2&page=1');

        $response->assertOk()
            ->assertJsonPath('data.0.kecamatan', 'SERPONG')
            ->assertJsonPath('data.0.sls', 'RT 01 RW 01')
            ->assertJsonPath('data.1.sls', 'RT 01 RW 01');

        $pageTwo = $this->getJson('/api/fasih-checks?per_page=2&page=2');

        $pageTwo->assertOk()
            ->assertJsonPath('data.0.sls', 'RT 02 RW 01')
            ->assertJsonPath('data.1.sls', 'RT 02 RW 02');
    }

    public function test_statistics_returns_dependent_filter_options(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL B',
            'nama_pml' => 'PML B',
            'kelurahan' => 'Kelurahan B',
            'sls' => '002',
            'source_sheet' => 'SETU',
            'external_id' => 'options-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'kelurahan' => 'Kelurahan A',
            'sls' => '001',
            'source_sheet' => 'SERPONG',
            'external_id' => 'options-2',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics?kecamatan=SETU');

        $response->assertOk()
            ->assertJsonPath('filter_options.ppl', ['PPL B'])
            ->assertJsonPath('filter_options.pml', ['PML B'])
            ->assertJsonPath('filter_options.kelurahan', ['Kelurahan B'])
            ->assertJsonPath('filter_options.sls', ['002']);
    }

    public function test_statistics_returns_ppl_detail_per_kecamatan(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'jenis_responden' => 'Keluarga',
            'source_sheet' => 'SETU',
            'external_id' => 'ppl-kec-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML B',
            'jenis_responden' => 'Usaha',
            'source_sheet' => 'SETU',
            'external_id' => 'ppl-kec-2',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'nama_ppl' => 'PPL B',
            'nama_pml' => 'PML C',
            'jenis_responden' => 'Keluarga',
            'source_sheet' => 'SERPONG',
            'external_id' => 'ppl-kec-3',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics');

        $response->assertOk()
            ->assertJsonPath('per_kecamatan_ppl.SETU.0.nama_ppl', 'PPL A')
            ->assertJsonPath('per_kecamatan_ppl.SETU.0.total_responden', 2)
            ->assertJsonPath('per_kecamatan_ppl.SETU.0.keluarga', 1)
            ->assertJsonPath('per_kecamatan_ppl.SETU.0.usaha', 1)
            ->assertJsonPath('per_kecamatan_ppl.SERPONG.0.nama_ppl', 'PPL B');
    }

    public function test_api_returns_all_daily_activity_for_a_ppl_in_the_selected_kecamatan(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $twoDaysAgo = now()->subDays(2)->toDateString();

        KartuKendali::factory()->create([
            'kecamatan' => 'PAMULANG',
            'nama_ppl' => 'PPL Detail',
            'nama_pml' => 'PML A',
            'tanggal_pendataan' => $yesterday,
            'waktu_pendataan' => $yesterday.' 08:00:00',
            'source_sheet' => 'PAMULANG',
            'external_id' => 'activity-pamulang-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'PAMULANG',
            'nama_ppl' => 'PPL Detail',
            'nama_pml' => 'PML A',
            'tanggal_pendataan' => $yesterday,
            'waktu_pendataan' => $yesterday.' 09:00:00',
            'source_sheet' => 'PAMULANG-2',
            'external_id' => 'activity-pamulang-2',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'PAMULANG',
            'nama_ppl' => 'PPL Detail',
            'nama_pml' => 'PML A',
            'tanggal_pendataan' => $twoDaysAgo,
            'waktu_pendataan' => $twoDaysAgo.' 10:00:00',
            'source_sheet' => 'PAMULANG-3',
            'external_id' => 'activity-pamulang-3',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'PAMULANG',
            'nama_ppl' => 'PPL Detail',
            'nama_pml' => 'PML A',
            'tanggal_pendataan' => $today,
            'waktu_pendataan' => $today.' 10:00:00',
            'source_sheet' => 'PAMULANG-4',
            'external_id' => 'activity-pamulang-today',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL Detail',
            'nama_pml' => 'PML B',
            'tanggal_pendataan' => $today,
            'waktu_pendataan' => $today.' 11:00:00',
            'source_sheet' => 'SETU',
            'external_id' => 'activity-setu-1',
        ]);

        $response = $this->getJson('/api/kartu-kendali/ppl-activity?kecamatan=PAMULANG&nama_ppl=PPL%20Detail');

        $response->assertOk()
            ->assertJsonPath('kecamatan', 'PAMULANG')
            ->assertJsonPath('nama_ppl', 'PPL Detail')
            ->assertJsonPath('total_responden', 4)
            ->assertJsonPath('today_total', 1)
            ->assertJsonPath('daily_target_per_ppl', 10)
            ->assertJsonPath('progress_percent', 10)
            ->assertJsonCount(3, 'daily_activity')
            ->assertJsonPath('daily_activity.0.date', $today)
            ->assertJsonPath('daily_activity.0.total', 1)
            ->assertJsonPath('daily_activity.1.date', $yesterday)
            ->assertJsonPath('daily_activity.1.total', 2)
            ->assertJsonPath('daily_activity.2.date', $twoDaysAgo)
            ->assertJsonPath('daily_activity.2.total', 1);
    }

    public function test_statistics_returns_kecamatan_respondent_and_ppl_aggregates(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL Satu',
            'nama_pml' => 'PML Satu',
            'sls' => '001',
            'jenis_responden' => 'Keluarga',
            'source_sheet' => 'SETU',
            'external_id' => 'stats-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'nama_ppl' => 'PPL Satu',
            'nama_pml' => 'PML Dua',
            'sls' => '002',
            'jenis_responden' => 'Usaha',
            'source_sheet' => 'SERPONG',
            'external_id' => 'stats-2',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL Dua',
            'nama_pml' => 'PML Satu',
            'sls' => '003',
            'jenis_responden' => 'Campuran',
            'source_sheet' => 'SETU',
            'external_id' => 'stats-3',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics');

        $response->assertOk()
            ->assertJsonPath('total_responden', 3)
            ->assertJsonPath('total_ppl', 2)
            ->assertJsonPath('total_pml', 2)
            ->assertJsonPath('total_sls', 3)
            ->assertJsonPath('per_kecamatan.SETU', 2)
            ->assertJsonPath('per_kecamatan_detail.SETU.total_responden', 2)
            ->assertJsonPath('per_kecamatan_detail.SETU.total_ppl', 2)
            ->assertJsonPath('per_kecamatan_jenis_responden.SETU.Keluarga', 1)
            ->assertJsonPath('per_kecamatan_jenis_responden.SETU.Campuran', 1)
            ->assertJsonPath('top_ppl.0.nama', 'PPL Satu')
            ->assertJsonPath('top_ppl.0.total_responden', 1)
            ->assertJsonPath('latest.0.external_id', 'stats-3')
            ->assertJsonCount(3, 'latest');
    }

    public function test_statistics_returns_daily_progress_and_ppl_activity(): void
    {
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'tanggal_pendataan' => '2026-09-20',
            'waktu_pendataan' => '2026-09-20 08:30:00',
            'source_sheet' => 'SETU',
            'external_id' => 'daily-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SERPONG',
            'nama_ppl' => 'PPL B',
            'nama_pml' => 'PML B',
            'tanggal_pendataan' => '2026-09-20',
            'waktu_pendataan' => '2026-09-20 09:00:00',
            'source_sheet' => 'SERPONG',
            'external_id' => 'daily-2',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'tanggal_pendataan' => '2026-09-21',
            'waktu_pendataan' => '2026-09-21 15:00:00',
            'source_sheet' => 'SETU-2',
            'external_id' => 'daily-3',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics');

        $response->assertOk()
            ->assertJsonPath('daily_progress.0.date', '2026-09-20')
            ->assertJsonPath('daily_progress.0.total', 2)
            ->assertJsonPath('ppl_activity.0.nama_ppl', 'PPL A')
            ->assertJsonPath('ppl_activity.0.total_responden', 2)
            ->assertJsonPath('ppl_activity.0.last_activity', '2026-09-21T08:00:00.000000Z');
    }

    public function test_statistics_includes_recently_active_ppl_even_when_not_active_today(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'kelurahan' => 'Kelurahan A',
            'tanggal_pendataan' => $today,
            'waktu_pendataan' => $today.' 08:00:00',
            'source_sheet' => 'SETU',
            'external_id' => 'target-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL B',
            'nama_pml' => 'PML B',
            'kelurahan' => 'Kelurahan A',
            'tanggal_pendataan' => $yesterday,
            'waktu_pendataan' => $yesterday.' 09:00:00',
            'source_sheet' => 'SETU-2',
            'external_id' => 'target-2',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics');

        $response->assertOk()
            ->assertJsonPath('daily_snapshot.active_ppl_today', 1)
            ->assertJsonPath('per_kecamatan_target_progress.SETU.total_ppl', 2)
            ->assertJsonPath('per_kecamatan_target_progress.SETU.target_total', 20)
            ->assertJsonFragment(['nama_ppl' => 'PPL B', 'total_today' => 0])
            ->assertJsonFragment(['nama_ppl' => 'PPL A', 'total_today' => 1]);
    }

    public function test_statistics_returns_daily_target_progress_per_ppl_and_kecamatan(): void
    {
        $today = now()->toDateString();

        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML A',
            'kelurahan' => 'Kelurahan A',
            'tanggal_pendataan' => $today,
            'waktu_pendataan' => $today.' 08:00:00',
            'source_sheet' => 'SETU',
            'external_id' => 'target-1',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML B',
            'kelurahan' => 'Kelurahan A',
            'tanggal_pendataan' => $today,
            'waktu_pendataan' => $today.' 09:00:00',
            'source_sheet' => 'SETU-2',
            'external_id' => 'target-2',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL B',
            'nama_pml' => 'PML C',
            'kelurahan' => 'Kelurahan A',
            'tanggal_pendataan' => $today,
            'waktu_pendataan' => $today.' 10:00:00',
            'source_sheet' => 'SETU-3',
            'external_id' => 'target-3',
        ]);
        KartuKendali::factory()->create([
            'kecamatan' => 'SETU',
            'nama_ppl' => 'PPL A',
            'nama_pml' => 'PML D',
            'kelurahan' => 'Kelurahan A',
            'tanggal_pendataan' => $today,
            'waktu_pendataan' => $today.' 11:00:00',
            'source_sheet' => 'SETU-4',
            'external_id' => 'target-4',
        ]);

        $response = $this->getJson('/api/kartu-kendali/statistics');

        $response->assertOk()
            ->assertJsonPath('daily_target_per_ppl', 10)
            ->assertJsonPath('daily_snapshot.today_total', 4)
            ->assertJsonPath('daily_snapshot.yesterday_total', 0)
            ->assertJsonPath('daily_snapshot.average_per_day_7d', 0.57)
            ->assertJsonPath('per_ppl_daily_progress.0.nama_ppl', 'PPL A')
            ->assertJsonPath('per_ppl_daily_progress.0.total_today', 3)
            ->assertJsonPath('per_ppl_daily_progress.0.progress_percent', 30)
            ->assertJsonPath('per_kecamatan_target_progress.SETU.total_today', 4)
            ->assertJsonPath('per_kecamatan_target_progress.SETU.target_total', 20)
            ->assertJsonPath('per_kecamatan_target_progress.SETU.remaining', 16)
            ->assertJsonPath('per_kecamatan_target_progress.SETU.day_change_percent', 0)
            ->assertJsonPath('per_kecamatan_target_progress.SETU.progress_percent', 20)
            ->assertJsonPath('per_kelurahan_daily_progress.0.kecamatan', 'SETU')
            ->assertJsonPath('per_kelurahan_daily_progress.0.target_total', 20)
            ->assertJsonPath('per_kelurahan_daily_progress.0.progress_percent', 20);
    }
}
