<?php

namespace Database\Factories;

use App\Models\KartuKendali;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KartuKendali>
 */
class KartuKendaliFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kecamatan = fake()->randomElement(KartuKendali::KECAMATAN);

        return [
            'kecamatan' => $kecamatan,
            'timestamp_google' => now()->format('m/d/Y H:i:s'),
            'timestamp_masuk' => now(),
            'tanggal_pendataan' => fake()->date(),
            'waktu_pendataan' => now(),
            'nama_ppl' => fake()->name(),
            'nama_pml' => fake()->name(),
            'kelurahan' => fake()->city(),
            'sls' => fake()->numerify('###'),
            'nama_responden' => fake()->name(),
            'jenis_responden' => fake()->randomElement([
                'Rumah Tangga Biasa',
                'Rumah Tangga Khusus',
                'Usaha',
                'Non Usaha',
                'Perusahaan',
            ]),
            'dokumentasi' => null,
            'keterangan' => null,
            'source_sheet' => $kecamatan,
            'external_id' => fake()->unique()->uuid(),
        ];
    }
}
