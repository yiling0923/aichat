<?php

namespace App\Bot;

/**
 * The fixed sentences the bot says by itself (not written by the business).
 * Add a language by adding a block here and the code in config/aichat.php.
 */
class Strings
{
    private const MAP = [
        'en' => [
            'sorry_choose' => 'Sorry, I did not understand that. Please choose one of the options below.',
            'sorry_ask' => 'Sorry, that does not look right. Please try again.',
            'invalid_phone' => 'That phone number does not look right. Please type the full number, for example 012-345 6789.',
            'back_to_menu' => 'Main menu',
            'handover' => 'Thanks. I am connecting you with our team. Someone will reply here shortly.',
            'handover_closed' => 'Thanks. Our team is not available right now. We will reply here as soon as we open.',
            'handover_holding' => 'Thank you for waiting. Our team has your message and will reply as soon as possible.',
            'opt_out' => 'You will not receive promotional messages from us. You can still message us any time.',
            'fallback_missing' => 'Sorry, something went wrong with this menu. I am connecting you with our team.',
        ],
        'zh' => [
            'sorry_choose' => '抱歉，我没有理解。请从下面的选项中选择。',
            'sorry_ask' => '抱歉，这个好像不对，请再试一次。',
            'invalid_phone' => '这个电话号码好像不对，请输入完整号码，例如 012-345 6789。',
            'back_to_menu' => '主菜单',
            'handover' => '谢谢。我正在为您转接我们的团队，稍后会有同事在这里回复您。',
            'handover_closed' => '谢谢。我们的团队目前不在线，开始营业后会尽快在这里回复您。',
            'handover_holding' => '感谢您的等待。我们的团队已收到您的消息，会尽快回复。',
            'opt_out' => '您将不会再收到我们的推广消息。您仍可以随时给我们发消息。',
            'fallback_missing' => '抱歉，这个菜单出了点问题。我正在为您转接我们的团队。',
        ],
    ];

    public static function get(string $key, ?string $language): string
    {
        $language = $language !== null && isset(self::MAP[$language]) ? $language : 'en';

        return self::MAP[$language][$key] ?? self::MAP['en'][$key] ?? $key;
    }
}
