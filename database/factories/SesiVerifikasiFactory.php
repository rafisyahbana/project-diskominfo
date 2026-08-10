<?php

namespace Database\Factories;

use App\Models\SesiVerifikasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SesiVerifikasi>
 */
class SesiVerifikasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no_wa' => $this->faker->phoneNumber(),
            'nik' => $this->faker->numerify('################'), // 16 digits
            'otp_hash' => bcrypt('123456'), // dummy hashed OTP
            'status' => 'pending',
            'percobaan_gagal' => 0,
            'expired_at' => now()->addMinutes(5),
        ];
    }
}
