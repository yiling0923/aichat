<?php

namespace App\Bot;

/**
 * A menu bot flow: the questions and answers a business built.
 * Plain data (stored as JSON) with a few readers on top.
 */
class Flow
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function start(): string
    {
        return (string) ($this->data['start'] ?? 'main');
    }

    /** @return list<string> */
    public function languages(): array
    {
        $languages = array_values(array_filter((array) ($this->data['languages'] ?? []), 'is_string'));

        return $languages !== [] ? $languages : config('aichat.languages', ['en']);
    }

    public function defaultLanguage(): string
    {
        return $this->languages()[0];
    }

    /** @return array<string, array<string, mixed>> */
    public function nodes(): array
    {
        return (array) ($this->data['nodes'] ?? []);
    }

    /** @return array<string, mixed>|null */
    public function node(string $id): ?array
    {
        $node = $this->nodes()[$id] ?? null;

        return is_array($node) ? $node : null;
    }

    public function hasNode(string $id): bool
    {
        return $this->node($id) !== null;
    }

    public function mainNodeId(): string
    {
        return $this->hasNode('main') ? 'main' : $this->start();
    }

    /**
     * Pick the text for a language, falling back to the default language,
     * then to any language that has text.
     *
     * @param  array<string, string>|string|null  $map
     */
    public function localize(array|string|null $map, ?string $language): string
    {
        if (is_string($map)) {
            return $map;
        }

        if (! is_array($map)) {
            return '';
        }

        foreach ([$language, $this->defaultLanguage()] as $code) {
            if ($code !== null && isset($map[$code]) && trim((string) $map[$code]) !== '') {
                return (string) $map[$code];
            }
        }

        foreach ($map as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }
}
