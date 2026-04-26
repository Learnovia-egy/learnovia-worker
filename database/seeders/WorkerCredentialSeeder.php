<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkerCredential;
use Illuminate\Support\Facades\Hash;

class WorkerCredentialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WorkerCredential::create([
            'password_hash' => Hash::make('L3arn0vi@2026'),
        ]);
    }
}
