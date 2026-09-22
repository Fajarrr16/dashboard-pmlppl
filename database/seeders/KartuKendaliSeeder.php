<?php

namespace Database\Seeders;

use App\Models\KartuKendali;
use Illuminate\Database\Seeder;

class KartuKendaliSeeder extends Seeder
{
    /**
     * Data dummy untuk menguji dashboard sebelum Google Apps Script terhubung.
     * Semua nama bersifat fiktif, bukan data pribadi nyata.
     */
    public function run(): void
    {
        $kelurahan = [
            'SETU' => ['Setu', 'Babakan', 'Muncul', 'Keranggan', 'Bakti Jaya'],
            'SERPONG' => ['Serpong', 'Rawa Buntu', 'Cilenggang', 'Rawabuntu'],
            'PAMULANG' => ['Pamulang Barat', 'Pamulang Timur', 'Pondok Benda', 'Kedaung', 'Benda Baru'],
            'CIPUTAT' => ['Ciputat', 'Sawah Baru', 'Cipayung', 'Jombang', 'Sawah Lama'],
            'CIPUTAT TIMUR' => ['Cempaka Putih', 'Pondok Ranji', 'Pisangan', 'Cireundeu', 'Rengas'],
            'PONDOK AREN' => ['Pondok Karya', 'Pondok Aren', 'Jurang Mangu', 'Parigi', 'Pondok Betung'],
            'SERPONG UTARA' => ['Pakulonan', 'Pondok Jagung', 'Lengkong Karya', 'Paku Alam', 'Jombang'],
        ];

        $namaPpl = ['Yuni Astuti', 'Bambang Prasetyo', 'Siti Rahmawati', 'Agus Salim', 'Dewi Lestari', 'Hendra Gunawan'];
        $namaPml = ['Ratna Dewi', 'Slamet Riyadi', 'Nurhayati', 'Eko Santoso', 'Fitri Handayani'];

        $jenisResponden = ['Rumah Tangga Biasa', 'Rumah Tangga Khusus', 'Usaha', 'Non Usaha', 'Perusahaan'];

        $dokumentasi = [
            'https://drive.google.com/file/d/sample1/view',
            'https://drive.google.com/file/d/sample2/view',
            'https://drive.google.com/file/d/sample3/view',
            'https://drive.google.com/file/d/sample4/view',
            'https://drive.google.com/file/d/sample5/view',
        ];

        $index = 1;

        foreach (array_keys($kelurahan) as $wilayah) {
            for ($i = 0; $i < 5; $i++) {
                $tanggal = fake()->dateTimeBetween('-30 days', 'today');
                $sls = str_pad((string) fake()->numberBetween(1, 200), 3, '0', STR_PAD_LEFT);

                KartuKendali::firstOrCreate(
                    ['external_id' => "seed-{$index}", 'source_sheet' => $wilayah],
                    [
                        'wilayah' => $wilayah,
                        'timestamp_google' => $tanggal->format('m/d/Y H:i:s'),
                        'tanggal_pendataan' => $tanggal->format('Y-m-d'),
                        'nama_ppl' => $namaPpl[$index % count($namaPpl)],
                        'nama_pml' => $namaPml[$index % count($namaPml)],
                        'kelurahan' => $kelurahan[$wilayah][$i % count($kelurahan[$wilayah])],
                        'sls' => $sls,
                        'nama_responden' => 'Responden '.$wilayah.'-'.$i,
                        'jenis_responden' => $jenisResponden[$index % count($jenisResponden)],
                        'dokumentasi' => $dokumentasi[$index % count($dokumentasi)],
                        'keterangan' => 'Data dummy uji dashboard (seed).',
                    ]
                );

                $index++;
            }
        }
    }
}
