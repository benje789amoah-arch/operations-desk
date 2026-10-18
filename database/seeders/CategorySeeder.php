<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Starter activity categories. Safe to run repeatedly in any environment.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Messaging & Telco' => 'SMS, USSD and aggregator gateway checks.',
            'Application Health' => 'Error logs, exceptions and application behaviour.',
            'Scheduled Jobs & Queues' => 'Cron jobs, workers and background processing.',
            'Payments & Transactions' => 'Transaction records, callbacks and reconciliation.',
            'Service Availability' => 'Endpoint, API and channel availability.',
            'Incident Management' => 'Follow-up of open incidents and tickets.',
            'Infrastructure' => 'Backups, storage and database housekeeping.',
            'Security' => 'Certificates, access and security reviews.',
        ];

        foreach ($categories as $name => $description) {
            Category::query()->firstOrCreate(['name' => $name], ['description' => $description, 'is_active' => true]);
        }
    }
}
