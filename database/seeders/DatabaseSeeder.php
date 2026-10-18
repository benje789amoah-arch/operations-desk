<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data is always seeded. Demonstration accounts and records are
     * only created in local/testing environments, or when explicitly enabled
     * with OPERATIONS_SEED_DEMO_DATA=true for a demo deployment.
     *
     * Production administrators are created with:  php artisan operations:create-admin
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        if (app()->environment(['local', 'testing']) || config('operations.seed_demo_data')) {
            $this->call(DemoDataSeeder::class);
        } else {
            $this->command?->warn('Demo data skipped (not a local environment). Run "php artisan operations:create-admin" to create the first administrator.');
        }
    }
}
