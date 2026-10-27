<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local testing: one sample clinic and two staff logins.
 * The sample content is made up and must be replaced for a real business.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::query()->firstOrCreate(
            ['slug' => 'lumiere'],
            [
                'name' => 'Lumiere Aesthetic Clinic (sample)',
                'timezone' => 'Asia/Kuala_Lumpur',
                'default_language' => 'en',
                'languages' => ['en', 'zh'],
                'status' => 'active',
                'settings' => ['hours' => [
                    'mon' => ['10:00', '19:00'], 'tue' => ['10:00', '19:00'], 'wed' => ['10:00', '19:00'],
                    'thu' => ['10:00', '19:00'], 'fri' => ['10:00', '19:00'], 'sat' => ['10:00', '17:00'],
                ]],
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Sarah Lim', 'password' => 'password', 'business_id' => $business->id, 'role' => 'owner'],
        );

        User::query()->firstOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Amy Tan', 'password' => 'password', 'business_id' => $business->id, 'role' => 'staff'],
        );
    }
}
