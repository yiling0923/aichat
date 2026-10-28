<?php

namespace App\Marketing;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Customer;
use App\Services\N8nNotifier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Opt-in broadcasts. Only customers who agreed to be contacted and never sent STOP can receive one.
 */
class Broadcasts
{
    public const LIMIT = 500;

    public function __construct(private Outreach $outreach, private N8nNotifier $n8n) {}

    /** @return Builder<Customer> */
    public function audience(Broadcast $broadcast): Builder
    {
        $a = (array) $broadcast->audience;

        return Customer::query()
            ->whereNotNull('consented_at')->whereNull('opted_out_at')
            ->when(! empty($a['tag_id']), fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', (int) $a['tag_id'])))
            ->when(! empty($a['score']), fn ($q) => $q->where('score', $a['score']))
            ->when(! empty($a['language']), fn ($q) => $q->where('language', $a['language']));
    }

    public function send(Broadcast $broadcast): Broadcast
    {
        if ($broadcast->status === 'sent') {
            return $broadcast;
        }

        // Claim it first so a double click cannot send twice.
        $claimed = Broadcast::query()->whereKey($broadcast->id)->where('status', 'draft')->update(['status' => 'sent', 'sent_at' => now()]);

        if ($claimed === 0) {
            return $broadcast->refresh();
        }

        $counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        $business = $broadcast->business;

        foreach ($this->audience($broadcast)->orderBy('id')->limit(self::LIMIT)->get() as $customer) {
            $result = $this->outreach->send(
                $customer, (array) $broadcast->message,
                ['name' => $customer->name, 'business' => $business->name],
                [], $broadcast->template_name, [(string) $customer->name],
            );

            BroadcastRecipient::create(['broadcast_id' => $broadcast->id, 'customer_id' => $customer->id, 'status' => $result['status'], 'reason' => $result['reason']]);
            $counts[$result['status']]++;
        }

        $broadcast->update(['sent_count' => $counts['sent'], 'skipped_count' => $counts['skipped'], 'failed_count' => $counts['failed']]);

        $this->n8n->send('broadcast.sent', ['business' => $business->slug, 'broadcast_id' => $broadcast->id, 'name' => $broadcast->name] + $counts);

        return $broadcast->refresh();
    }
}
