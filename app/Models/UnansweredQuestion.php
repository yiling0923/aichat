<?php

namespace App\Models;

use App\Bot\Text;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class UnansweredQuestion extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['text', 'normalized', 'language', 'channel', 'times', 'last_asked_at'];

    protected function casts(): array
    {
        return ['last_asked_at' => 'datetime'];
    }

    /** Remember (or count again) a typed question the bot could not answer. */
    public static function note(string $text, ?string $language, ?string $channel): void
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $normalized = Text::normalize($text);

        if (mb_strlen($normalized) < 3 || mb_strlen($text) > 200 || ctype_digit(str_replace(' ', '', $normalized))) {
            return;
        }

        $row = static::query()->where('normalized', $normalized)->first();

        if ($row !== null) {
            $row->forceFill(['times' => $row->times + 1, 'last_asked_at' => now()])->save();

            return;
        }

        static::query()->create(['text' => $text, 'normalized' => $normalized, 'language' => $language, 'channel' => $channel, 'times' => 1, 'last_asked_at' => now()]);
    }
}
