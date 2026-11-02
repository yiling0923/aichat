<?php

namespace App\Support;

/** A table heading that sorts the list when clicked (the server does the sorting, so it covers every page). */
class SortLink
{
    public static function th(string $key, string $label, string $sort, string $dir, string $class = ''): string
    {
        $on = $sort === $key;
        $next = $on && $dir === 'asc' ? 'desc' : 'asc';
        $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]);
        $state = $on ? ($dir === 'asc' ? 'asc' : 'desc') : '';

        return '<th class="sortable '.($on ? 'sorted '.$state : '').' '.$class.'" aria-sort="'.($on ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none').'"><a href="'.e($url).'">'.e($label).'<span class="arrows" aria-hidden="true"></span></a></th>';
    }

    /** @param list<string> $allowed */
    public static function pick(?string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? (string) $value : $default;
    }
}
