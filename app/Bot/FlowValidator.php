<?php

namespace App\Bot;

/**
 * Checks a flow before it is published, and cleans it so only known keys are stored.
 * Errors block publishing. Warnings are advice (for example WhatsApp length limits).
 */
class FlowValidator
{
    public const TYPES = ['options', 'message', 'ask', 'save_lead', 'handover', 'booking', 'my_booking'];

    public const FIELDS = ['name', 'phone', 'preferred_time', 'interest', 'note'];

    /**
     * Keep only the keys the bot understands and cut very long strings.
     *
     * @param  array<string, mixed>  $flow
     * @return array<string, mixed>
     */
    public function clean(array $flow): array
    {
        $languages = array_values(array_unique(array_filter(
            (array) ($flow['languages'] ?? []),
            fn ($l) => is_string($l) && in_array($l, config('aichat.languages'), true),
        )));

        $clean = [
            'start' => $this->str($flow['start'] ?? '', 60),
            'languages' => $languages,
            'nodes' => [],
        ];

        foreach ((array) ($flow['nodes'] ?? []) as $id => $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = (string) $id;
            $item = ['type' => $this->str($node['type'] ?? '', 20), 'text' => $this->map($node['text'] ?? [])];

            foreach (['goto', 'field', 'validate', 'score', 'reason'] as $key) {
                if (isset($node[$key]) && is_string($node[$key]) && $node[$key] !== '') {
                    $item[$key] = $this->str($node[$key], 200);
                }
            }

            if (isset($node['notify'])) {
                $item['notify'] = (bool) $node['notify'];
            }

            if (isset($node['link']) && is_array($node['link'])) {
                $item['link'] = [
                    'url' => $this->str($node['link']['url'] ?? '', 500),
                    'label' => $this->map($node['link']['label'] ?? []),
                ];
            }

            if (isset($node['options']) && is_array($node['options'])) {
                $item['options'] = [];
                foreach ($node['options'] as $option) {
                    if (is_array($option)) {
                        $item['options'][] = $this->cleanOption($option);
                    }
                }
            }

            $clean['nodes'][$id] = $item;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $flow
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validate(array $flow): array
    {
        $errors = [];
        $warnings = [];
        $flowObj = new Flow($flow);
        $languages = $flowObj->languages();
        $default = $flowObj->defaultLanguage();
        $nodes = $flowObj->nodes();
        $limits = config('aichat.limits');

        if (($flow['languages'] ?? []) === []) {
            $errors[] = __('Choose at least one language.');
        }

        if ($nodes === []) {
            return ['errors' => array_merge($errors, ['The menu has no steps yet.']), 'warnings' => $warnings];
        }

        if (! $flowObj->hasNode($flowObj->start())) {
            $errors[] = __('The start step ":id" does not exist.', ['id' => $flowObj->start()]);
        }

        foreach ($nodes as $id => $node) {
            $where = __('Step ":id"', ['id' => $id]);

            if (! preg_match('/^[a-z0-9_]{1,40}$/', (string) $id)) {
                $errors[] = $where.': '.__('the id may only use lower case letters, numbers and underscores (up to 40).');
            }

            $type = $node['type'] ?? '';
            if (! in_array($type, self::TYPES, true)) {
                $errors[] = $where.': '.__('unknown type ":type".', ['type' => $type]);

                continue;
            }

            $text = $node['text'] ?? [];
            if (trim((string) ($text[$default] ?? '')) === '' && ! in_array($type, ['handover', 'booking', 'my_booking'], true)) {
                $errors[] = $where.': '.__('add the message text in :lang.', ['lang' => strtoupper($default)]);
            }
            foreach ($languages as $code) {
                if ($code !== $default && trim((string) ($text[$code] ?? '')) === '' && trim((string) ($text[$default] ?? '')) !== '') {
                    $warnings[] = $where.': '.__('no text in :lang. Customers who choose it will see :default.', ['lang' => strtoupper($code), 'default' => strtoupper($default)]);
                }
            }
            foreach ($text as $code => $value) {
                if (mb_strlen((string) $value) > $limits['text']) {
                    $warnings[] = $where.': '.__('the :lang text is longer than :n characters, which WhatsApp may cut.', ['lang' => strtoupper((string) $code), 'n' => $limits['text']]);
                }
            }

            if (isset($node['goto']) && $node['goto'] !== '' && ! $flowObj->hasNode($node['goto'])) {
                $errors[] = $where.': '.__('"goes to" points at a step that does not exist (":id").', ['id' => $node['goto']]);
            }

            if ($type === 'options') {
                $options = $node['options'] ?? [];
                if ($options === []) {
                    $errors[] = $where.': '.__('add at least one option.');
                }
                if (count($options) > $limits['options_per_node']) {
                    $errors[] = $where.': '.__('more than :n options. WhatsApp lists show at most :n.', ['n' => $limits['options_per_node']]);
                }

                $seen = [];
                foreach ($options as $i => $option) {
                    $optId = (string) ($option['id'] ?? '');
                    $label = (string) ($option['label'][$default] ?? '');
                    $which = $where.', '.__('option :n', ['n' => $i + 1]);

                    if (! preg_match('/^[a-z0-9_]{1,40}$/', $optId)) {
                        $errors[] = $which.': '.__('the option id may only use lower case letters, numbers and underscores.');
                    } elseif (isset($seen[$optId])) {
                        $errors[] = $which.': '.__('the option id ":id" is used twice in this step.', ['id' => $optId]);
                    }
                    $seen[$optId] = true;

                    if (trim($label) === '') {
                        $errors[] = $which.': '.__('add the button text in :lang.', ['lang' => strtoupper($default)]);
                    }
                    foreach ((array) ($option['label'] ?? []) as $code => $value) {
                        $max = count($options) <= 3 ? $limits['label_button'] : $limits['label_list'];
                        if (mb_strlen((string) $value) > $max) {
                            $warnings[] = $which.': '.__('the :lang label is longer than :n characters, which WhatsApp will cut.', ['lang' => strtoupper((string) $code), 'n' => $max]);
                        }
                    }

                    $target = $option['goto'] ?? '';
                    $hasAction = ($option['action'] ?? '') === 'handover';
                    if ($target === '' && ! $hasAction) {
                        $errors[] = $which.': '.__('choose where this option goes, or make it hand over to staff.');
                    } elseif ($target !== '' && ! $flowObj->hasNode($target)) {
                        $errors[] = $which.': '.__('"goes to" points at a step that does not exist (":id").', ['id' => $target]);
                    }
                }
            }

            if ($type === 'ask') {
                if (! in_array($node['field'] ?? '', self::FIELDS, true)) {
                    $errors[] = $where.': '.__('choose what this question collects (name, phone, ...).');
                }
                if (($node['goto'] ?? '') === '') {
                    $errors[] = $where.': '.__('choose the next step.');
                }
            }

            if ($type === 'save_lead' && ! in_array($node['score'] ?? 'warm', ['hot', 'warm', 'cold'], true)) {
                $errors[] = $where.': '.__('the lead score must be hot, warm or cold.');
            }
        }

        foreach ($this->unreachable($flowObj) as $id) {
            $warnings[] = __('Step ":id" cannot be reached from the start.', ['id' => $id]);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** @return list<string> */
    private function unreachable(Flow $flow): array
    {
        $seen = [];
        $queue = [$flow->start()];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id]) || ! $flow->hasNode($id)) {
                continue;
            }
            $seen[$id] = true;
            $node = $flow->node($id);

            if (! empty($node['goto'])) {
                $queue[] = $node['goto'];
            } elseif (in_array($node['type'] ?? '', ['message', 'save_lead'], true)) {
                $queue[] = $flow->mainNodeId();
            }
            foreach ($node['options'] ?? [] as $option) {
                if (! empty($option['goto'])) {
                    $queue[] = $option['goto'];
                }
            }
        }

        return array_values(array_diff(array_keys($flow->nodes()), array_keys($seen)));
    }

    /** @param array<string, mixed> $option */
    private function cleanOption(array $option): array
    {
        $clean = [
            'id' => $this->str($option['id'] ?? '', 60),
            'label' => $this->map($option['label'] ?? []),
        ];

        foreach (['goto', 'action', 'language'] as $key) {
            if (isset($option[$key]) && is_string($option[$key]) && $option[$key] !== '') {
                $clean[$key] = $this->str($option[$key], 100);
            }
        }

        if (isset($option['reason']) && is_string($option['reason'])) {
            $clean['reason'] = $this->str($option['reason'], 200);
        }

        if (isset($option['keywords']) && is_array($option['keywords'])) {
            $clean['keywords'] = array_values(array_filter(array_map(fn ($k) => $this->str($k, 60), $option['keywords']), fn ($k) => $k !== ''));
        }

        if (isset($option['set']) && is_array($option['set'])) {
            $clean['set'] = [];
            foreach ($option['set'] as $key => $value) {
                if (in_array($key, self::FIELDS, true) && is_scalar($value)) {
                    $clean['set'][$key] = $this->str($value, 200);
                }
            }
        }

        return $clean;
    }

    /** @return array<string, string> */
    private function map(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $code => $text) {
            if (is_string($code) && in_array($code, config('aichat.languages'), true) && is_scalar($text)) {
                $out[$code] = $this->str($text, 2000);
            }
        }

        return $out;
    }

    private function str(mixed $value, int $max): string
    {
        return mb_substr(trim((string) (is_scalar($value) ? $value : '')), 0, $max);
    }
}
