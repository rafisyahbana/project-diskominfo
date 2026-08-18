<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warga;

class WargaTestSeeder extends Seeder
{
    /**
     * Warga dummy untuk testing manual.
     * HARUS dihapus atau dinonaktifkan di production.
     */
    public function run(): void
    {
        $data = [
            [
                'nik'            => '1111111111111111',
                'nama'           => 'Budi Santoso',
                'no_hp_terdaftar' => '628111111111',
                'alamat'         => 'Jl. Melati No. 1, Kota Contoh',
            ],
            [
                'nik'            => '2222222222222222',
                'nama'           => 'Siti Rahayu',
                'no_hp_terdaftar' => '628222222222',
                'alamat'         => 'Jl. Mawar No. 2, Kota Contoh',
            ],
            [
                'nik'            => '3333333333333333',
                'nama'           => 'Ahmad Fauzi',
                'no_hp_terdaftar' => '628333333333',
                'alamat'         => 'Jl. Kenanga No. 3, Kota Contoh',
            ],
        ];

        foreach ($data as $row) {
            Warga::firstOrCreate(['nik' => $row['nik']], $row);
        }

        $this->command->info('WargaTestSeeder: 3 warga dummy berhasil di-seed.');
    }
}
