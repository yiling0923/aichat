<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BotFlow;
use App\Models\User;
use App\Setup\SetupApplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Makes a new business with its first owner: used by sign-up and by the platform console. */
class BusinessProvisioner
{
    /** @return array{0: Business, 1: User} */
    public function create(string $businessName, string $ownerName, string $email, string $password, bool $emailConfirmed = false): array
    {
        return DB::transaction(function () use ($businessName, $ownerName, $email, $password, $emailConfirmed): array {
            $business = Business::create([
                'name' => $businessName,
                'slug' => $this->uniqueSlug($businessName),
                'timezone' => 'Asia/Kuala_Lumpur',
                'default_language' => 'en',
                'languages' => ['en', 'zh'],
                'status' => 'active',
                'plan' => 'trial',
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays((int) config('plans.trial_days')),
                'settings' => ['hours' => [
                    'mon' => ['09:00', '18:00'], 'tue' => ['09:00', '18:00'], 'wed' => ['09:00', '18:00'],
                    'thu' => ['09:00', '18:00'], 'fri' => ['09:00', '18:00'],
                ]],
            ]);

            $flow = SetupApplier::neutralFlow();
            (new BotFlow(['draft' => $flow, 'published' => $flow, 'published_at' => now()]))->forceFill(['business_id' => $business->id])->save();

            $owner = User::create(['name' => $ownerName, 'email' => $email, 'password' => $password, 'business_id' => $business->id, 'role' => 'owner', 'active' => true]);
            if ($emailConfirmed) {
                $owner->forceFill(['email_verified_at' => now()])->save();
            }

            return [$business, $owner];
        });
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $i = 1;

        while (Business::query()->where('slug', $slug)->exists() || in_array($slug, ['admin', 'api', 'login', 'signup', 'platform', 'chat', 'webhook'], true)) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
