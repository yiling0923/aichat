<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Tag;

/**
 * Reads a CSV of customers (columns: name, phone, email, language, tags, notes; the first row names the columns).
 * Imported people are NOT marketing contacts unless the owner confirms that they agreed to be contacted.
 */
class CustomerImporter
{
    private const MAX_ROWS = 2000;

    /** @return array{new: int, updated: int, skipped: int, error: ?string} */
    public function import(string $csv, bool $agreed): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        // Excel in some countries separates with semicolons.
        $first = strtok($csv, "\n") ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        $columns = array_map(fn ($h) => strtolower(trim((string) $h)), (array) $header);

        if (! in_array('phone', $columns, true) && ! in_array('name', $columns, true)) {
            return ['new' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'The first row must name the columns, for example: name, phone, email, language, tags, notes.'];
        }

        $new = $updated = $skipped = $rows = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if (++$rows > self::MAX_ROWS) {
                break;
            }

            $r = [];
            foreach ($columns as $i => $name) {
                $r[$name] = trim((string) ($row[$i] ?? ''));
            }

            $phone = preg_replace('/\D+/', '', $r['phone'] ?? '') ?: null;
            $email = filter_var($r['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
            $name = mb_substr($r['name'] ?? '', 0, 100) ?: null;

            if ($phone === null && $email === null && $name === null) {
                $skipped++;

                continue;
            }
            if ($phone !== null && (strlen($phone) < 7 || strlen($phone) > 15)) {
                $skipped++;

                continue;
            }

            $customer = $phone !== null ? Customer::query()->where('phone', $phone)->first() : ($email !== null ? Customer::query()->where('email', $email)->first() : null);
            $isNew = $customer === null;
            $customer ??= new Customer;

            $language = strtolower($r['language'] ?? '');
            $language = in_array($language, (array) config('aichat.languages'), true) ? $language : (str_starts_with($language, 'zh') || str_starts_with($language, 'chi') ? 'zh' : null);

            $customer->fill([
                'name' => $customer->name ?: $name,
                'phone' => $customer->phone ?: $phone,
                'email' => $customer->email ?: $email,
                'language' => $customer->language ?: $language,
                'notes' => $customer->notes ?: (($r['notes'] ?? '') !== '' ? mb_substr($r['notes'], 0, 1000) : null),
            ]);
            if ($agreed && $customer->consented_at === null && $customer->opted_out_at === null) {
                $customer->consented_at = now();
            }
            $customer->save();

            foreach (array_filter(array_map('trim', preg_split('/[;|]/', (string) ($r['tags'] ?? '')) ?: [])) as $tagName) {
                $tag = Tag::query()->where('type', 'customer')->whereRaw('lower(name) = ?', [mb_strtolower($tagName)])->first()
                    ?? Tag::query()->create(['name' => mb_substr($tagName, 0, 40), 'type' => 'customer', 'color' => '#64797a']);
                $customer->tags()->syncWithoutDetaching([$tag->id]);
            }

            $isNew ? $new++ : $updated++;
        }

        fclose($handle);

        return ['new' => $new, 'updated' => $updated, 'skipped' => $skipped, 'error' => null];
    }
}
