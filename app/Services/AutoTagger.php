<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Tag;
use App\Support\CurrentBusiness;
use Throwable;

/**
 * Tags a chat by what the customer wrote, with plain word matching (no AI, no outside service).
 * Tags are created on first use, and a customer who asks about prices or booking is at least "warm".
 */
class AutoTagger
{
    /** @var array<string, array{color: string, en: list<string>, zh: list<string>, lead?: bool}> */
    public const TOPICS = [
        'Price question' => ['color' => '#2563eb', 'lead' => true,
            'en' => ['price', 'prices', 'pricing', 'cost', 'costs', 'how much', 'fee', 'fees', 'rate', 'rates', 'discount', 'promo', 'promotion', 'package', 'packages', 'harga', 'berapa'],
            'zh' => ['多少钱', '价格', '价钱', '收费', '费用', '优惠', '套餐', '折扣']],
        'Booking help' => ['color' => '#0f766e', 'lead' => true,
            'en' => ['book', 'booking', 'appointment', 'reserve', 'reservation', 'available', 'availability', 'slot', 'tempah'],
            'zh' => ['预约', '预订', '订位', '有空', '约时间']],
        'Cancel or change' => ['color' => '#c2410c',
            'en' => ['cancel', 'cancellation', 'reschedule', 'postpone', 'batal'],
            'zh' => ['取消', '改期', '延期']],
        'Location' => ['color' => '#475569',
            'en' => ['where are you', 'address', 'location', 'parking', 'direction', 'directions', 'map', 'alamat', 'lokasi'],
            'zh' => ['地址', '在哪', '哪里', '停车', '怎么去']],
        'Payment' => ['color' => '#15803d',
            'en' => ['payment', 'pay', 'deposit', 'invoice', 'receipt', 'bank transfer', 'bayar'],
            'zh' => ['付款', '定金', '收据', '转账']],
        'Aftercare' => ['color' => '#7c3aed',
            'en' => ['swelling', 'swollen', 'redness', 'rash', 'itchy', 'pain', 'bleeding', 'side effect', 'side effects', 'infection'],
            'zh' => ['红肿', '肿', '疼', '痛', '痒', '过敏', '副作用', '出血']],
        'Complaint' => ['color' => '#b91c1c',
            'en' => ['complaint', 'complain', 'refund', 'terrible', 'awful', 'worst', 'disappointed', 'angry', 'unhappy', 'not happy', 'rude', 'scam', 'bad service', 'unacceptable'],
            'zh' => ['投诉', '退款', '差劲', '不满意', '太差', '生气', '失望', '骗']],
    ];

    /** @return list<string> the topic names found in the text */
    public function topics(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];

        foreach (self::TOPICS as $name => $rule) {
            $english = '/(?<![a-z0-9])('.implode('|', array_map(fn ($w) => preg_quote($w, '/'), $rule['en'])).')(?![a-z0-9])/u';
            $hit = preg_match($english, $text) === 1;

            foreach ($rule['zh'] as $w) {
                if (! $hit && str_contains($text, $w)) {
                    $hit = true;
                }
            }

            if ($hit) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /** Tags the chat from one customer message. Never throws: tagging must not break a chat. */
    public function tagMessage(Conversation $conversation, string $text): void
    {
        if ($conversation->channel === 'preview' || trim($text) === '') {
            return;
        }

        try {
            $business = $conversation->business;
            if (($business->settings['auto_tag'] ?? true) === false) {
                return;
            }

            $topics = $this->topics($text);
            if ($topics === []) {
                return;
            }

            app(CurrentBusiness::class)->runAs($business, function () use ($conversation, $topics, $business, $text) {
                $newComplaint = false;

                foreach ($topics as $name) {
                    $tag = Tag::query()->firstOrCreate(['type' => 'chat', 'name' => $name], ['color' => self::TOPICS[$name]['color']]);

                    if (! $conversation->tags()->where('tags.id', $tag->id)->exists()) {
                        $conversation->tags()->attach($tag->id);
                        $newComplaint = $newComplaint || $name === 'Complaint';
                    }
                }

                // Asking about price or booking shows interest: at least warm (never lowers hot).
                $customer = $conversation->customer;
                if ($customer !== null && in_array($customer->score, [null, '', 'cold'], true) && array_filter($topics, fn ($t) => ! empty(self::TOPICS[$t]['lead'])) !== []) {
                    $customer->forceFill(['score' => 'warm'])->save();
                }

                if ($newComplaint) {
                    app(Notifier::class)->notify($business, 'complaint', Notifier::chatName($conversation).' may be a complaint', $text,
                        route('inbox', ['c' => $conversation->id]), ['owner', 'manager']);
                }
            });
        } catch (Throwable $e) {
            try {
                report($e);
            } catch (Throwable) {
                // the log itself may be locked; tagging must never break a chat
            }
        }
    }
}
