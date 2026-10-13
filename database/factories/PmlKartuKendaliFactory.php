<?php

namespace Database\Factories;

use App\Models\PmlKartuKendali;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PmlKartuKendali>
 */
class PmlKartuKendaliFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kecamatan = fake()->randomElement(PmlKartuKendali::KECAMATAN);

        return [
            'timestamp_google' => now()->format('m/d/Y H:i:s'),
            'email_address' => fake()->safeEmail(),
            'tanggal_pendataan' => fake()->date(),
            'nama_pml' => fake()->name(),
            'kecamatan' => $kecamatan,
            'kelurahan' => fake()->city(),
            'sls' => fake()->numerify('###'),
            'jenis_responden' => fake()->randomElement(['Keluarga', 'Usaha', 'Campuran']),
            'dokumentasi' => 'https://drive.google.com/'.fake()->uuid(),
            'timestamp_masuk' => now(),
            'source_sheet' => 'PML - '.$kecamatan,
            'external_id' => fake()->unique()->uuid(),
        ];
    }
}
