<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Practitioner;
use App\Models\QuickReply;
use App\Models\Tag;
use App\Models\Service;
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

        // The person who runs the whole service (sign in at /login, then you land on /platform).
        User::query()->firstOrCreate(
            ['email' => 'platform@example.com'],
            ['name' => 'Platform Owner', 'password' => 'password', 'role' => 'platform_owner', 'email_verified_at' => now()],
        );

        User::query()->firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Sarah Lim', 'password' => 'password', 'business_id' => $business->id, 'role' => 'owner', 'email_verified_at' => now()],
        );

        User::query()->firstOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Amy Tan', 'password' => 'password', 'business_id' => $business->id, 'role' => 'staff', 'email_verified_at' => now()],
        );

        if (Service::query()->withoutGlobalScopes()->where('business_id', $business->id)->doesntExist()) {
            app(\App\Support\CurrentBusiness::class)->runAs($business, function () {
                $practitioners = [
                    Practitioner::create(['name' => 'Dr. Mei Ling']),
                    Practitioner::create(['name' => 'Amy (therapist)']),
                ];

                foreach ([
                    [['en' => 'Hydrating Facial', 'zh' => '补水护理'], 60, 188],
                    [['en' => 'Pigmentation Laser', 'zh' => '色斑激光'], 45, 450],
                    [['en' => 'HIFU Skin Tightening', 'zh' => 'HIFU 紧致'], 90, 980],
                    [['en' => 'Acne Treatment', 'zh' => '祛痘护理'], 60, 220],
                    [['en' => 'Consultation', 'zh' => '咨询'], 30, 0],
                ] as $i => [$name, $minutes, $price]) {
                    $service = Service::create(['name' => $name, 'duration_min' => $minutes, 'price' => $price, 'sort' => $i]);
                    $service->practitioners()->sync(array_map(fn ($p) => $p->id, $practitioners));
                }
            });
        }

        if (Tag::query()->withoutGlobalScopes()->where('business_id', $business->id)->doesntExist()) {
            app(\App\Support\CurrentBusiness::class)->runAs($business, function () {
                Tag::create(['name' => 'VIP', 'type' => 'customer', 'color' => '#b45309']);
                Tag::create(['name' => 'First visit', 'type' => 'customer', 'color' => '#0f766e']);
                Tag::create(['name' => 'Price question', 'type' => 'chat', 'color' => '#2563eb']);
                Tag::create(['name' => 'Complaint', 'type' => 'chat', 'color' => '#c2410c']);

                QuickReply::create(['title' => 'Thanks and confirm', 'sort' => 1, 'body' => [
                    'en' => 'Thank you! We have noted your request and will confirm shortly.',
                    'zh' => '谢谢！我们已记下您的需求，稍后会向您确认。',
                ]]);
                QuickReply::create(['title' => 'Aftercare', 'sort' => 2, 'body' => [
                    'en' => 'For the next 24 hours please avoid direct sun and heavy make-up. Message us if you have any concern.',
                    'zh' => '接下来 24 小时请避免日晒和浓妆。如有任何疑问请随时联系我们。',
                ]]);
            });
        }
    }
}
