<?php

namespace App\Support;

/** The languages of the staff screens, and what the page scripts need to translate themselves. */
class Lang
{
    /** @var array<string, string> code => the name shown on the switch */
    public const LOCALES = ['en' => 'English', 'zh' => '中文'];

    public static function valid(?string $locale): string
    {
        return array_key_exists((string) $locale, self::LOCALES) ? (string) $locale : 'en';
    }

    /** Words the page scripts show (buttons, messages). Empty for English, because the scripts already contain English. @return array<string, string> */
    public static function scripts(string $locale): array
    {
        $file = lang_path($locale.'.js.json');

        return $locale !== 'en' && is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }
}
