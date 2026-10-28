<?php

namespace App\Console\Commands;

use App\Models\AiUsage;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\StaffNotification;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills a business with made-up customers, chats, bookings, orders and more, so every page has something to show.
 * Everything it makes is marked (email ends in @sample.test) and can be removed again with --remove.
 */
class SampleData extends Command
{
    protected $signature = 'aichat:sample-data {--business=lumiere : The business slug} {--remove : Only remove the sample data} {--customers=45}';

    protected $description = 'Fill a business with sample customers, chats, bookings, orders and reports (testing only)';

    private const MARK = '@sample.test';

    public function handle(CurrentBusiness $current): int
    {
        $business = Business::query()->where('slug', $this->option('business'))->first();

        if ($business === null) {
            $this->error('No business with that slug. Try --business=lumiere');

            return self::FAILURE;
        }

        if (app()->environment('production')) {
            $this->error('Sample data is for testing only and is blocked in production.');

            return self::FAILURE;
        }

        $current->runAs($business, function () use ($business) {
            DB::transaction(function () use ($business) {
                $this->remove($business);

                if (! $this->option('remove')) {
                    mt_srand(42);
                    $this->fill($business);
                }
            });
        });

        $this->info($this->option('remove') ? 'Sample data removed.' : 'Sample data added. Refresh your pages (Ctrl+F5). Run again with --remove to clear it.');

        return self::SUCCESS;
    }

    private function remove(Business $business): void
    {
        $ids = Customer::query()->where('email', 'like', '%'.self::MARK)->pluck('id');
        $convIds = Conversation::query()->whereIn('customer_id', $ids)->pluck('id')
            ->merge(Conversation::query()->where('contact_name', 'like', '%(sample)')->pluck('id'));
        $bookingIds = Booking::query()->whereIn('customer_id', $ids)->pluck('id');

        StaffNotification::query()->where('business_id', $business->id)->where(fn ($q) => $q->whereIn('url', $convIds->map(fn ($i) => url('/admin?c='.$i))->all())->orWhere('body', 'like', '%[sample]%'))->delete();
        BroadcastRecipient::query()->whereIn('customer_id', $ids)->delete();
        Broadcast::query()->where('name', 'like', '%(sample)')->delete();
        Survey::query()->whereIn('customer_id', $ids)->delete();
        AutomationLog::query()->whereIn('customer_id', $ids)->delete();
        Task::query()->whereIn('customer_id', $ids)->orWhere('title', 'like', '% [sample]')->delete();
        Order::query()->whereIn('customer_id', $ids)->delete();
        Booking::query()->whereIn('id', $bookingIds)->delete();
        Message::query()->whereIn('conversation_id', $convIds)->delete();
        DB::table('conversation_tag')->whereIn('conversation_id', $convIds)->delete();
        Conversation::query()->whereIn('id', $convIds)->delete();
        DB::table('customer_tag')->whereIn('customer_id', $ids)->delete();
        Customer::query()->whereIn('id', $ids)->delete();
        AiUsage::query()->where('note', 'sample')->delete();
    }

    private function fill(Business $business): void
    {
        $tz = $business->timezone ?: 'UTC';
        $now = Carbon::now($tz);
        $services = Service::query()->where('active', true)->get();
        $people = Practitioner::query()->where('active', true)->get();
        $owner = User::query()->where('business_id', $business->id)->where('role', 'owner')->first();
        $staff = User::query()->where('business_id', $business->id)->get();

        if ($services->isEmpty() || $people->isEmpty()) {
            $this->warn('This business has no services or people yet, so no bookings were made. Add them in Booking setup and run again.');
        }

        $vip = Tag::query()->firstOrCreate(['name' => 'VIP', 'type' => 'customer'], ['color' => '#b45309']);
        $first = Tag::query()->firstOrCreate(['name' => 'First visit', 'type' => 'customer'], ['color' => '#0f766e']);
        $regular = Tag::query()->firstOrCreate(['name' => 'Regular', 'type' => 'customer'], ['color' => '#2563eb']);
        $priceTag = Tag::query()->firstOrCreate(['name' => 'Price question', 'type' => 'chat'], ['color' => '#2563eb']);
        $complaint = Tag::query()->firstOrCreate(['name' => 'Complaint', 'type' => 'chat'], ['color' => '#c2410c']);

        // ---- customers ----
        $first_en = ['Emma', 'Liam', 'Aisha', 'Daniel', 'Priya', 'Hannah', 'Farah', 'Kevin', 'Sofia', 'Marcus', 'Nurul', 'Jason', 'Chloe', 'Ryan', 'Siti', 'Aaron', 'Mei', 'Grace', 'Isaac', 'Zara'];
        $last_en = ['Tan', 'Lim', 'Wong', 'Lee', 'Ahmad', 'Raj', 'Ng', 'Chong', 'Teo', 'Hassan', 'Chan', 'Ong'];
        $zh = ['陈美玲', '林佳颖', '王晓明', '李慧敏', '张婉婷', '黄俊杰', '刘雅琪', '吴志强', '郑欣怡', '周美华', '谢嘉豪', '杨静雯'];
        $interests = $services->map(fn ($s) => $s->label('en'))->all() ?: ['Facial'];
        $customers = [];
        $count = max(10, min(120, (int) $this->option('customers')));

        for ($i = 0; $i < $count; $i++) {
            $isZh = $i % 4 === 3;
            $name = $isZh ? $zh[$i % count($zh)] : $first_en[$i % count($first_en)].' '.$last_en[($i * 7) % count($last_en)];
            $score = ['hot', 'hot', 'warm', 'warm', 'warm', 'cold'][$i % 6];
            $added = $now->copy()->subDays(mt_rand(0, 75))->subMinutes(mt_rand(0, 900));
            $c = new Customer([
                'name' => $name,
                'phone' => '+60'.(1700000000 + $i * 7919 % 99999999),
                'email' => 'sample'.($i + 1).self::MARK,
                'language' => $isZh ? 'zh' : 'en',
                'interest' => $interests[$i % count($interests)],
                'score' => $score,
                'consented_at' => $i % 9 === 8 ? null : $added->copy()->utc(),
                'opted_out_at' => $i % 17 === 16 ? $added->copy()->addDays(3)->utc() : null,
                'notes' => $i % 5 === 0 ? ($isZh ? '喜欢周末预约。' : 'Prefers weekend appointments.') : null,
            ]);
            $c->forceFill(['created_at' => $added->copy()->utc(), 'updated_at' => $added->copy()->utc()])->save();
            $c->tags()->sync(array_values(array_filter([$i % 7 === 0 ? $vip->id : null, $i % 3 === 0 ? $first->id : null, $i % 4 === 1 ? $regular->id : null])));
            $customers[] = $c;
        }

        // ---- bookings: busy last 6 weeks, a lighter next 2 weeks, never double booked ----
        $made = [];
        if ($services->isNotEmpty() && $people->isNotEmpty()) {
            $taken = [];
            for ($d = -42; $d <= 14; $d++) {
                $day = $now->copy()->startOfDay()->addDays($d);
                if ($day->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }
                $perDay = $d < 0 ? mt_rand(2, 5) : mt_rand(1, 3);
                for ($k = 0; $k < $perDay; $k++) {
                    $person = $people[mt_rand(0, $people->count() - 1)];
                    $service = $services[mt_rand(0, $services->count() - 1)];
                    $start = $day->copy()->setTime(mt_rand(10, 16), mt_rand(0, 1) * 30);
                    $end = $start->copy()->addMinutes($service->duration_min);
                    // Mark every half hour the visit covers, so one person is never booked twice at the same time.
                    $keys = [];
                    for ($t = $start->copy(); $t->lt($end); $t->addMinutes(30)) {
                        $keys[] = $person->id.'|'.$t->format('YmdHi');
                    }
                    if (array_intersect($keys, $taken)) {
                        continue;
                    }
                    array_push($taken, ...$keys);
                    $customer = $customers[mt_rand(0, count($customers) - 1)];
                    $past = $end->lt($now);
                    $roll = mt_rand(1, 100);
                    $status = $past ? ($roll <= 82 ? 'completed' : ($roll <= 91 ? 'no_show' : 'cancelled')) : ($roll <= 94 ? 'confirmed' : 'cancelled');
                    $b = new Booking(['customer_id' => $customer->id, 'service_id' => $service->id, 'practitioner_id' => $person->id, 'starts_at' => $start->copy()->utc(), 'ends_at' => $end->copy()->utc(), 'status' => $status, 'cancelled_at' => $status === 'cancelled' ? $start->copy()->subDays(1)->utc() : null]);
                    $b->forceFill(['created_at' => $start->copy()->subDays(mt_rand(1, 6))->utc(), 'updated_at' => $start->copy()->utc()])->save();
                    $made[] = [$b, $customer, $service];
                }
            }
        }

        // ---- orders: paid sales for completed visits plus a few extras ----
        $n = 0;
        foreach ($made as [$b, $customer, $service]) {
            if ($b->status !== 'completed' || mt_rand(1, 100) > 70) {
                continue;
            }
            $o = new Order(['customer_id' => $customer->id, 'reference' => 'S-'.str_pad((string) (1000 + ++$n), 4, '0', STR_PAD_LEFT), 'items' => $service->label('en'), 'amount' => $service->price ?: 120, 'status' => 'paid', 'ordered_at' => $b->ends_at]);
            $o->forceFill(['created_at' => $b->ends_at, 'updated_at' => $b->ends_at])->save();
        }
        foreach ([['3 facial package', 480, 'paid'], ['Skin care set', 265, 'paid'], ['Gift voucher', 300, 'pending'], ['HIFU deposit', 200, 'pending'], ['Product refund', 85, 'refunded']] as $i => [$items, $amount, $status]) {
            $at = $now->copy()->subDays(2 + $i * 6)->utc();
            $o = new Order(['customer_id' => $customers[$i * 3]->id, 'reference' => 'S-'.(2000 + $i), 'items' => $items, 'amount' => $amount, 'status' => $status, 'ordered_at' => $at]);
            $o->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }

        // ---- surveys for finished visits ----
        foreach (collect($made)->filter(fn ($m) => $m[0]->status === 'completed')->take(24) as $i => [$b, $customer]) {
            $rating = [5, 5, 5, 4, 4, 5, 3, 4, 2, 5][$i % 10];
            $s = new Survey(['booking_id' => $b->id, 'customer_id' => $customer->id, 'rating' => $rating, 'comment' => $rating <= 2 ? 'Waited a long time.' : null, 'sent_at' => $b->ends_at->copy()->addHours(3), 'answered_at' => $b->ends_at->copy()->addHours(5), 'review_sent_at' => $rating >= 4 && $i % 2 === 0 ? $b->ends_at->copy()->addHours(6) : null]);
            $s->forceFill(['created_at' => $s->sent_at, 'updated_at' => $s->answered_at])->save();
        }

        // ---- automation log ----
        foreach (collect($made)->filter(fn ($m) => in_array($m[0]->status, ['completed', 'no_show'], true))->take(30) as $i => [$b, $customer]) {
            $key = $b->status === 'no_show' ? 'no_show' : ($i % 3 === 0 ? 'rebooking' : 'survey');
            $skip = $i % 7 === 0;
            $l = new AutomationLog(['automation_key' => $key, 'customer_id' => $customer->id, 'booking_id' => $b->id, 'status' => $skip ? 'skipped' : 'sent', 'reason' => $skip ? 'Customer has not agreed to promotions' : null]);
            $l->forceFill(['created_at' => $b->ends_at->copy()->addHours(2)])->save();
        }

        // ---- chats ----
        $scripts = [
            [['in', 'customer', 'Hi, how much is the hydrating facial?'], ['out', 'bot', 'Our Hydrating Facial is RM 188 for 60 minutes. Would you like to book a time?'], ['in', 'customer', 'Yes please, this Saturday afternoon?'], ['out', 'bot', 'Saturday has 14:00 and 15:30 free. Which suits you?']],
            [['in', 'customer', 'Do you have parking?'], ['out', 'bot', 'Yes, free parking is behind the building for two hours.'], ['in', 'customer', 'Great, thanks!']],
            [['in', 'customer', '你好，请问激光祛斑多少钱？'], ['out', 'bot', '色斑激光 RM 450，约 45 分钟。需要帮您预约吗？'], ['in', 'customer', '好的，下周三可以吗？']],
            [['in', 'customer', 'Can I change my booking to next week?'], ['out', 'bot', 'Sure, I will ask a team member to help you with that.'], ['out', 'staff', 'Hi! Of course, which day and time works for you?'], ['in', 'customer', 'Tuesday 11am please']],
            [['in', 'customer', 'My skin is red after yesterday’s treatment. Is this normal?'], ['out', 'bot', 'I am sorry to hear that. I am passing you to our team right away.']],
            [['in', 'customer', 'What are your opening hours?'], ['out', 'bot', 'We are open Monday to Friday 10:00 to 19:00 and Saturday 10:00 to 17:00.']],
            [['in', 'customer', 'Any promotion this month?'], ['out', 'bot', 'Yes! 10% off any two treatments booked this month.'], ['in', 'customer', 'Nice, I will think about it']],
            [['in', 'customer', 'I want to cancel my appointment'], ['out', 'bot', 'No problem. Choose My booking in the menu and press Cancel.'], ['in', 'customer', 'done, thank you']],
        ];
        $statusPlan = ['open', 'open', 'open', 'open', 'open', 'open', 'resolved', 'resolved', 'resolved', 'resolved', 'resolved', 'resolved', 'resolved', 'resolved', 'resolved', 'resolved'];
        foreach ($statusPlan as $i => $status) {
            $script = $scripts[$i % count($scripts)];
            $at = $now->copy()->subDays($status === 'open' ? 0 : mt_rand(1, 20))->subMinutes(mt_rand(5, 600));
            $customer = $i % 5 === 4 ? null : $customers[($i * 2) % count($customers)];
            $needsPerson = $i === 4 || $i === 3 || $i === 10;
            $conv = Conversation::startFor('web');
            $conv->forceFill([
                'customer_id' => $customer?->id,
                'contact_name' => $customer ? null : 'Visitor '.(200 + $i).' (sample)',
                'language' => $customer?->language ?? 'en',
                'status' => $status,
                'mode' => $needsPerson && $status === 'open' ? 'human' : 'bot',
                'handover_reason' => $needsPerson && $status === 'open' ? ($i === 4 ? 'Customer reported a skin reaction' : 'Customer asked for a person') : null,
                'handover_at' => $needsPerson && $status === 'open' ? $at->copy()->utc() : null,
                'ai_intent' => ['price', 'info', 'booking', 'change', 'complaint', 'info', 'promo', 'cancel'][$i % 8],
                'ai_mood' => $i === 4 ? 'upset' : 'neutral',
                'created_at' => $at->copy()->utc(), 'updated_at' => $at->copy()->utc(),
            ])->save();
            if ($i === 4) { $conv->tags()->sync([$complaint->id]); }
            if ($i % 8 === 0) { $conv->tags()->sync([$priceTag->id]); }
            foreach ($script as $j => [$dir, $who, $text]) {
                $m = new Message(['direction' => $dir, 'sender_type' => $who, 'body' => $text, 'is_private' => false]);
                $m->forceFill(['business_id' => $business->id, 'conversation_id' => $conv->id, 'created_at' => $at->copy()->addMinutes($j * 2)->utc(), 'updated_at' => $at->copy()->addMinutes($j * 2)->utc()])->save();
            }
            $conv->forceFill(['last_message_at' => $at->copy()->addMinutes(count($script) * 2)->utc(), 'last_inbound_at' => $at->copy()->addMinutes(count($script) * 2)->utc()])->save();
        }

        // ---- follow-up tasks ----
        foreach ([
            ['Call back about HIFU quote', 1, null], ['Send aftercare tips to new customers', 0, null], ['Confirm Saturday bookings', -1, null],
            ['Order more facial masks', 3, null], ['Reply to complaint about waiting time', -2, null], ['Update the price list', 5, now()->subDay()], ['Follow up with VIP customers', 2, now()->subHours(5)],
        ] as $i => [$title, $dueDays, $done]) {
            $t = new Task(['title' => $title.' [sample]', 'due_at' => $now->copy()->addDays($dueDays)->setTime(10 + $i, 0)->utc(), 'assigned_to' => $staff->isNotEmpty() ? $staff[$i % $staff->count()]->id : null, 'created_by' => $owner?->id, 'customer_id' => $i % 2 === 0 ? $customers[$i]->id : null, 'done_at' => $done]);
            $t->forceFill(['created_at' => $now->copy()->subDays(3)->utc(), 'updated_at' => $now->utc()])->save();
        }

        // ---- broadcasts ----
        $sent = new Broadcast(['name' => 'Chinese New Year offer (sample)', 'message' => ['en' => 'Hi {name}, enjoy 10% off any two treatments at {business} this month!', 'zh' => '{name} 您好，本月任何两项护理享九折优惠！'], 'audience' => [], 'status' => 'sent', 'sent_at' => $now->copy()->subDays(9)->utc(), 'created_by' => $owner?->id]);
        $sent->save();
        $counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (collect($customers)->take(30) as $i => $c) {
            $status = $c->opted_out_at ? 'skipped' : ($i % 13 === 12 ? 'failed' : ($c->consented_at ? 'sent' : 'skipped'));
            $reason = $status === 'skipped' ? ($c->opted_out_at ? 'Sent STOP' : 'No consent') : ($status === 'failed' ? 'Message could not be delivered' : null);
            BroadcastRecipient::create(['broadcast_id' => $sent->id, 'customer_id' => $c->id, 'status' => $status, 'reason' => $reason]);
            $counts[$status]++;
        }
        $sent->forceFill(['sent_count' => $counts['sent'], 'skipped_count' => $counts['skipped'], 'failed_count' => $counts['failed']])->save();
        (new Broadcast(['name' => 'Weekend facial special (sample)', 'message' => ['en' => 'Hi {name}, book a Hydrating Facial this weekend and get a free mask.'], 'audience' => ['score' => 'hot'], 'status' => 'draft', 'created_by' => $owner?->id]))->save();

        // ---- AI usage this month, so the billing bars have something to show ----
        $month = $now->copy()->startOfMonth();
        for ($i = 0; $i < 38; $i++) {
            $u = new AiUsage(['purpose' => $i % 5 === 0 ? 'summary' : 'reply', 'provider' => 'sample', 'model' => 'sample', 'input_tokens' => mt_rand(300, 900), 'output_tokens' => mt_rand(60, 220), 'status' => 'ok', 'note' => 'sample', 'created_at' => $month->copy()->addMinutes(mt_rand(0, max(1, $month->diffInMinutes($now))))->utc()]);
            $u->save();
        }

        // ---- bell notifications ----
        if ($owner !== null) {
            foreach ([['handover', 'Customer asked for a person', 'Visitor 204', 5], ['booking', 'New booking', 'Hydrating Facial, tomorrow 11:00', 40], ['message', 'New chat', 'Do you have parking?', 90], ['task', 'Task due today', 'Confirm Saturday bookings', 180]] as [$type, $title, $body, $minutes]) {
                $n = StaffNotification::create(['business_id' => $business->id, 'user_id' => $owner->id, 'type' => $type, 'title' => $title, 'body' => $body.' [sample]', 'url' => url('/admin'), 'read_at' => $minutes > 100 ? now() : null]);
                $n->forceFill(['created_at' => now()->subMinutes($minutes), 'updated_at' => now()->subMinutes($minutes)])->save();
            }
        }

        // The automations stay as they are: sample customers only make the activity log and reports look lived in.
        $this->line(sprintf('  %d customers, %d bookings, %d orders, 16 chats, 7 tasks, 2 broadcasts', count($customers), count($made), Order::query()->count()));
    }
}
