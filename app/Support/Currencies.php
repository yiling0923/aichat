<?php

namespace App\Support;

/**
 * The currencies a business can show with its prices (and its reports, orders and chat answers).
 * The saved value is the short text printed before a price, such as "RM" or "S$"; anyone can also type their own.
 */
class Currencies
{
    /** @var array<string, array<string, string>> region => [what is printed => name] */
    public const GROUPS = [
        'Asia-Pacific' => [
            'RM' => 'Malaysian ringgit', 'S$' => 'Singapore dollar', 'HK$' => 'Hong Kong dollar', 'NT$' => 'Taiwan dollar', 'MOP$' => 'Macau pataca',
            'CN¥' => 'Chinese yuan', 'JP¥' => 'Japanese yen', '¥' => 'Yuan or yen', '₩' => 'Korean won', '฿' => 'Thai baht', 'Rp' => 'Indonesian rupiah',
            '₱' => 'Philippine peso', '₫' => 'Vietnamese dong', '₹' => 'Indian rupee', 'Rs' => 'Rupee (Sri Lanka, Pakistan, Nepal)', 'A$' => 'Australian dollar', 'NZ$' => 'New Zealand dollar',
        ],
        'Americas' => ['$' => 'Dollar', 'US$' => 'US dollar', 'C$' => 'Canadian dollar', 'MX$' => 'Mexican peso', 'R$' => 'Brazilian real'],
        'Europe' => ['€' => 'Euro', '£' => 'Pound', 'CHF' => 'Swiss franc', 'kr' => 'Krona or krone', 'zł' => 'Polish zloty', '₺' => 'Turkish lira'],
        'Middle East and Africa' => ['AED' => 'UAE dirham', 'SAR' => 'Saudi riyal', '₪' => 'Israeli shekel', 'R' => 'South African rand', '₦' => 'Nigerian naira', 'KSh' => 'Kenyan shilling'],
    ];

    /** @return array<string, string> every listed currency: printed text => name */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function isListed(string $value): bool
    {
        return array_key_exists($value, self::all());
    }

    /** A typed-in currency is kept only when it is short and made of letters, currency signs, digits, a dot or a plain space. */
    public static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && preg_match('/^[\p{L}\p{Sc}\p{N}\. ]{1,6}$/u', $value) ? $value : null;
    }
}
