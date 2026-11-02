<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/** One setting of the whole service, such as the currency the plans are priced in. Not tied to a business. */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['key', 'value', 'updated_at'];

    protected function casts(): array
    {
        return ['value' => 'array', 'updated_at' => 'datetime'];
    }

    /** All settings, read once per request (kept on the app, so every request and every test starts fresh). */
    private static function loaded(): \ArrayObject
    {
        if (! app()->bound('platform.settings')) {
            try {
                $all = static::query()->get()->mapWithKeys(fn (self $s) => [$s->key => $s->value])->all();
            } catch (Throwable) {
                $all = [];      // the table is not there yet (before "php artisan migrate"): use the defaults
            }
            app()->instance('platform.settings', new \ArrayObject($all));
        }

        return app('platform.settings');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::loaded();

        return $all->offsetExists($key) ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        app()->forgetInstance('platform.settings');
    }
}
