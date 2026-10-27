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

        $treatment = fn (string $id, string $en, string $zh): array => $option($id, $t($en, $zh), [
            'goto' => 'book_name',
            'set' => ['interest' => $en],
            'keywords' => [mb_strtolower($en)],
        ]);

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
                    'type' => 'options',
                    'text' => $t('Which treatment would you like?', '您想预约哪个疗程？'),
                    'options' => [
                        $treatment('facial', 'Hydrating Facial', '补水护理'),
                        $treatment('laser', 'Pigmentation Laser', '色斑激光'),
                        $treatment('hifu', 'HIFU Skin Tightening', 'HIFU 紧致'),
                        $treatment('acne', 'Acne Treatment', '祛痘护理'),
                        $treatment('consult', 'Consultation', '咨询'),
                    ],
                ],
                'book_name' => [
                    'type' => 'ask',
                    'field' => 'name',
                    'text' => $t('Great choice. What is your name?', '好的。请问您怎么称呼？'),
                    'goto' => 'book_phone',
                ],
                'book_phone' => [
                    'type' => 'ask',
                    'field' => 'phone',
                    'validate' => 'phone',
                    'text' => $t(
                        'Thanks, {name}. What is your phone number? By sharing it you agree that we may contact you about your enquiry.',
                        '谢谢，{name}。请问您的电话号码是多少？提供号码即表示您同意我们就此咨询联系您。',
                    ),
                    'goto' => 'book_time',
                ],
                'book_time' => [
                    'type' => 'ask',
                    'field' => 'preferred_time',
                    'text' => $t('When would you like to come? For example: Saturday afternoon.', '您希望什么时候来？例如：星期六下午。'),
                    'goto' => 'book_done',
                ],
                'book_done' => [
                    'type' => 'save_lead',
                    'score' => 'warm',
                    'notify' => true,
                    'text' => $t(
                        'Thank you, {name}! We have your request for {interest} ({preferred_time}). Our team will contact you on {phone} to confirm the time.',
                        '谢谢您，{name}！我们已收到您的预约请求：{interest}（{preferred_time}）。我们的团队会通过 {phone} 联系您确认时间。',
                    ),
                    'goto' => 'main',
                ],
            ],
        ];
    }
}
