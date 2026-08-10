<?php

namespace Database\Factories;

use App\Models\Permohonan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permohonan>
 */
class PermohonanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => $this->faker->numerify('################'),
            'no_wa' => $this->faker->phoneNumber(),
            'jenis_surat' => $this->faker->randomElement(['Surat Keterangan Usaha', 'Surat Domisili']),
            'data_form' => ['keperluan' => 'Untuk pengajuan kredit', 'nama_usaha' => 'Toko Makmur'],
            'status' => 'draft',
            'catatan_petugas' => null,
            'file_surat_url' => null,
        ];
    }
}
