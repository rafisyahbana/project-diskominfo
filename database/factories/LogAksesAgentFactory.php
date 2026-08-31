  <?php

namespace Database\Factories;

use App\Models\LogAksesAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogAksesAgent>
 */
class LogAksesAgentFactory extends Factory
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
            'nik' => $this->faker->numerify('################'),
            'tool_dipanggil' => 'cek_status_permohonan',
            'payload' => ['nik' => '1234567890123456'],
            'hasil' => 'success',
            'created_at' => now(),
        ];
    }
}
