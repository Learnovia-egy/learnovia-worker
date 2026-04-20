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
        $servers = ['new', 'tms', 'sun', 'ramsis'];
        foreach ($servers as $server) {
            WorkerCredential::create([
                'service_name' => $server,
                'password_hash' => Hash::make('L3arn0vi@2026'),
            ]);
        }
    }
}
