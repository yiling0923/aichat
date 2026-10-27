<?php

namespace App\Bot;

/**
 * The starter menu every new business gets. The content is a made-up sample
 * clinic in English and Chinese. The owner edits it in the menu builder.
 */
class DefaultFlow
{
    /** @return array<string, mixed> */
    public static function make(): array
    {
        $t = fn (string $en, string $zh): array => ['en' => $en, 'zh' => $zh];

        $option = fn (string $id, array $label, array $extra = []): array => array_merge(['id' => $id, 'label' => $label], $extra);

        return [
            'start' => 'language',
            'languages' => ['en', 'zh'],
            'nodes' => [
                'language' => [
                    'type' => 'options',
                    'text' => $t('Hi! Welcome to {business}. Please choose your language.', '您好！欢迎来到{business}。请选择语言。'),
                    'options' => [
                        $option('lang_en', $t('English', 'English'), ['language' => 'en', 'goto' => 'main', 'keywords' => ['english', 'en']]),
                        $option('lang_zh', $t('中文', '中文'), ['language' => 'zh', 'goto' => 'main', 'keywords' => ['chinese', '中文', '华语', '华文']]),
                    ],
                ],
                'main' => [
                    'type' => 'options',
                    'text' => $t('How can I help you today?', '请问需要什么帮助？'),
                    'options' => [
                        $option('prices', $t('Prices', '价格'), ['goto' => 'prices', 'keywords' => ['price', 'prices', 'cost', 'how much', 'fee', '价格', '多少钱', '收费']]),
                        $option('book', $t('Book an appointment', '预约'), ['goto' => 'book', 'keywords' => ['book', 'booking', 'appointment', 'reserve', '预约', '订位', '预订']]),
                        $option('my_booking', $t('My booking', '我的预约'), ['goto' => 'my_booking', 'keywords' => ['my booking', 'my appointment', 'cancel booking', 'cancel appointment', 'cancel', 'reschedule', '我的预约', '取消预约', '取消']]),
                        $option('hours', $t('Opening hours', '营业时间'), ['goto' => 'hours', 'keywords' => ['hours', 'open', 'opening', 'close', 'closing', '营业', '几点']]),
                        $option('location', $t('Location', '地址'), ['goto' => 'location', 'keywords' => ['location', 'address', 'where', 'parking', 'map', '地址', '在哪', '停车']]),
                        $option('promo', $t('Promotions', '优惠'), ['goto' => 'promo', 'keywords' => ['promo', 'promotion', 'promotions', 'discount', 'offer', '优惠', '折扣', '促销']]),
                        $option('staff', $t('Talk to staff', '联系员工'), ['action' => 'handover', 'reason' => 'Customer chose Talk to staff']),
                    ],
                ],
                'prices' => [
                    'type' => 'message',
                    'text' => $t(
                        "Our prices (sample):\n- Hydrating Facial: RM 188 (60 min)\n- Pigmentation Laser: from RM 450 (45 min)\n- HIFU Skin Tightening: from RM 980 (90 min)\n- Acne Treatment: RM 220 (60 min)\n- Consultation: free (30 min)",
                        "我们的价格（示例）：\n- 补水护理：RM 188（60 分钟）\n- 色斑激光：RM 450 起（45 分钟）\n- HIFU 紧致：RM 980 起（90 分钟）\n- 祛痘护理：RM 220（60 分钟）\n- 咨询：免费（30 分钟）",
                    ),
                    'goto' => 'main',
                ],
                'hours' => [
                    'type' => 'message',
                    'text' => $t(
                        "We are open:\nMon to Fri 10:00 - 19:00\nSaturday 10:00 - 17:00\nSunday: closed",
                        "营业时间：\n周一至周五 10:00 - 19:00\n周六 10:00 - 17:00\n周日休息",
                    ),
                    'goto' => 'main',
                ],
                'location' => [
                    'type' => 'message',
                    'text' => $t(
                        "We are at Level 2, Plaza Example, Jalan Sample, Kuala Lumpur (sample address). Free parking for 2 hours in the basement.",
                        "我们在吉隆坡 Jalan Sample, Plaza Example 2 楼（示例地址）。地下停车场可免费停车 2 小时。",
                    ),
                    'link' => ['url' => 'https://maps.google.com', 'label' => $t('Open map', '打开地图')],
                    'goto' => 'main',
                ],
                'promo' => [
                    'type' => 'message',
                    'text' => $t(
                        "Current promotion (sample): first-time customers get 10% off any facial this month.",
                        "当前优惠（示例）：新客户本月任何面部护理享 9 折。",
                    ),
                    'goto' => 'main',
                ],
                'book' => [
                    'type' => 'booking',
                    'goto' => 'main',
                ],
                'my_booking' => [
                    'type' => 'my_booking',
                    'goto' => 'main',
                ],
            ],
        ];
    }
}
