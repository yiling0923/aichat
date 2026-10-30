<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Joins two records of the same person (for example the same customer on WhatsApp and on Messenger) into one.
 * Chats, bookings, orders, tasks, tags and history move to the kept record; blanks are filled from the other one.
 */
class CustomerMerger
{
    /** Tables that point at a customer and can simply be moved. */
    private const MOVE = ['conversations', 'bookings', 'automation_logs', 'surveys', 'orders', 'tasks'];

    private const LEVEL = ['cold' => 1, 'warm' => 2, 'hot' => 3];

    public function merge(Customer $keep, Customer $drop): Customer
    {
        if ($keep->is($drop) || $keep->business_id !== $drop->business_id) {
            throw new \InvalidArgumentException('Two different customers of the same business are needed.');
        }

        DB::transaction(function () use ($keep, $drop): void {
            foreach (self::MOVE as $table) {
                DB::table($table)->where('customer_id', $drop->id)->update(['customer_id' => $keep->id]);
            }

            // A broadcast can list a person only once.
            $has = DB::table('broadcast_recipients')->where('customer_id', $keep->id)->pluck('broadcast_id');
            DB::table('broadcast_recipients')->where('customer_id', $drop->id)->whereIn('broadcast_id', $has)->delete();
            DB::table('broadcast_recipients')->where('customer_id', $drop->id)->update(['customer_id' => $keep->id]);

            foreach (DB::table('customer_tag')->where('customer_id', $drop->id)->pluck('tag_id') as $tagId) {
                DB::table('customer_tag')->insertOrIgnore(['customer_id' => $keep->id, 'tag_id' => $tagId]);
            }
            DB::table('customer_tag')->where('customer_id', $drop->id)->delete();

            $keep->forceFill($this->combined($keep, $drop))->save();
            $drop->delete();
        });

        return $keep->fresh();
    }

    /** @return array<string, mixed> */
    private function combined(Customer $keep, Customer $drop): array
    {
        $notes = collect([$keep->notes, $drop->notes])->filter(fn ($n) => trim((string) $n) !== '')->unique()->implode("\n\n");
        $consented = collect([$keep->consented_at, $drop->consented_at])->filter()->min();
        // If either record opted out, the person stays opted out: the safe choice.
        $optedOut = collect([$keep->opted_out_at, $drop->opted_out_at])->filter()->max();
        $score = collect([$keep->score, $drop->score])->filter()->sortByDesc(fn ($s) => self::LEVEL[$s] ?? 0)->first();

        return [
            'name' => $keep->name ?: $drop->name,
            'phone' => $keep->phone ?: $drop->phone,
            'email' => $keep->email ?: $drop->email,
            'language' => $keep->language ?: $drop->language,
            'interest' => $keep->interest ?: $drop->interest,
            'details' => array_merge((array) $drop->details, (array) $keep->details),
            'notes' => $notes !== '' ? $notes : null,
            'consented_at' => $consented,
            'opted_out_at' => $optedOut,
            'score' => $score ?: $keep->score,
        ];
    }
}
