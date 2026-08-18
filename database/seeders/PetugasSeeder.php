<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PetugasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // HARUS DIGANTI SEBELUM PRODUCTION
        \App\Models\Petugas::create([
            'nama' => 'Admin Diskominfo',
            'email' => 'admin@diskominfo.local',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'admin',
        ]);
    }
}
