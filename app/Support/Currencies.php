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

    /** @var array<string, array<string, array{0: string, 1: string}>> region => [ISO code => [name, text printed before a price]] (for what the platform charges) */
    public const ISO = [
        'Asia-Pacific' => [
            'MYR' => ['Malaysian ringgit', 'RM'], 'SGD' => ['Singapore dollar', 'S$'], 'HKD' => ['Hong Kong dollar', 'HK$'], 'TWD' => ['Taiwan dollar', 'NT$'], 'MOP' => ['Macau pataca', 'MOP$'],
            'CNY' => ['Chinese yuan', 'CN¥'], 'JPY' => ['Japanese yen', 'JP¥'], 'KRW' => ['Korean won', '₩'], 'THB' => ['Thai baht', '฿'], 'IDR' => ['Indonesian rupiah', 'Rp'],
            'PHP' => ['Philippine peso', '₱'], 'VND' => ['Vietnamese dong', '₫'], 'INR' => ['Indian rupee', '₹'], 'LKR' => ['Sri Lankan rupee', 'Rs'], 'PKR' => ['Pakistani rupee', 'Rs'],
            'AUD' => ['Australian dollar', 'A$'], 'NZD' => ['New Zealand dollar', 'NZ$'],
        ],
        'Americas' => ['USD' => ['US dollar', '$'], 'CAD' => ['Canadian dollar', 'C$'], 'MXN' => ['Mexican peso', 'MX$'], 'BRL' => ['Brazilian real', 'R$']],
        'Europe' => ['EUR' => ['Euro', '€'], 'GBP' => ['Pound', '£'], 'CHF' => ['Swiss franc', 'CHF'], 'SEK' => ['Swedish krona', 'kr'], 'NOK' => ['Norwegian krone', 'kr'], 'DKK' => ['Danish krone', 'kr'], 'PLN' => ['Polish zloty', 'zł'], 'TRY' => ['Turkish lira', '₺']],
        'Middle East and Africa' => ['AED' => ['UAE dirham', 'AED'], 'SAR' => ['Saudi riyal', 'SAR'], 'ILS' => ['Israeli shekel', '₪'], 'ZAR' => ['South African rand', 'R'], 'NGN' => ['Nigerian naira', '₦'], 'KES' => ['Kenyan shilling', 'KSh']],
    ];

    /** @return array<string, array{0: string, 1: string}> */
    public static function isoAll(): array
    {
        return array_merge(...array_values(self::ISO));
    }

    /** The text customers see before a plan price for an ISO code: "RM" for MYR, or the code itself when there is no short sign. */
    public static function symbolFor(string $code): string
    {
        $code = strtoupper($code);

        return self::isoAll()[$code][1] ?? $code;
    }

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
