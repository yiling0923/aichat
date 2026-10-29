<?php

namespace App\Marketing;

use App\Models\Automation;

/**
 * The ready-made automations. Each business gets them all, switched off, and edits the wording.
 */
class AutomationDefaults
{
    public const KEYS = ['survey', 'review_request', 'rebooking', 'no_show', 'hot_lead'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'survey' => [
                'title' => 'Satisfaction survey',
                'help' => 'Asks for a 1 to 5 rating a few hours after the visit. A low score (1 or 2) creates a task and alerts the team.',
                'delay_hours' => 2,
                'message' => [
                    'en' => 'Hi {name}, thank you for visiting {business}! How was your {service}? Please rate your visit.',
                    'zh' => '您好 {name}，感谢您光临{business}！您对这次的{service}满意吗？请为本次体验评分。',
                ],
                'settings' => [],
            ],
            'review_request' => [
                'title' => 'Review request',
                'help' => 'After a good rating (4 or 5) sends your review link. Add the link (for example your Google review page).',
                'delay_hours' => 0,
                'message' => [
                    'en' => 'Thank you, {name}! If you have a minute, a review helps us a lot: {review_url}',
                    'zh' => '谢谢您，{name}！如果方便，请给我们留个好评，对我们帮助很大：{review_url}',
                ],
                'settings' => ['review_url' => '', 'min_rating' => 4],
            ],
            'rebooking' => [
                'title' => 'Rebooking reminder',
                'help' => 'Sends a nudge to customers whose last visit was some days ago and who have nothing booked.',
                'delay_hours' => 0,
                'message' => [
                    'en' => 'Hi {name}, it has been a while since your {service}. Ready for your next visit?',
                    'zh' => '您好 {name}，距离您上次的{service}已经有一段时间了。要不要预约下一次呢？',
                ],
                'settings' => ['rebook_days' => 30],
            ],
            'no_show' => [
                'title' => 'No-show recovery',
                'help' => 'When you mark a booking as No-show, offers the customer a new time a few hours later.',
                'delay_hours' => 2,
                'message' => [
                    'en' => 'Hi {name}, we missed you at your {service} appointment. Would you like to book a new time?',
                    'zh' => '您好 {name}，您预约的{service}我们没能等到您。要不要重新约个时间？',
                ],
                'settings' => [],
            ],
            'hot_lead' => [
                'title' => 'Follow-up for people ready to book',
                'help' => 'Follows up customers marked "Ready to book" who never booked, after the delay.',
                'delay_hours' => 24,
                'message' => [
                    'en' => 'Hi {name}, thanks for your interest in {service}. Shall we find you a time?',
                    'zh' => '您好 {name}，感谢您对{service}的关注。要不要帮您安排一个时间？',
                ],
                'settings' => [],
            ],
        ];
    }

    /** Make sure the current business has every automation (switched off). */
    public static function ensure(): void
    {
        $have = Automation::query()->pluck('key')->all();

        foreach (self::all() as $key => $def) {
            if (! in_array($key, $have, true)) {
                Automation::create(['key' => $key, 'enabled' => false, 'delay_hours' => $def['delay_hours'], 'message' => $def['message'], 'settings' => $def['settings']]);
            }
        }
    }
}
