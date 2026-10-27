<?php

namespace App\Bot;

/**
 * Checks a flow before it is published, and cleans it so only known keys are stored.
 * Errors block publishing. Warnings are advice (for example WhatsApp length limits).
 */
class FlowValidator
{
    public const TYPES = ['options', 'message', 'ask', 'save_lead', 'handover'];

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
            $errors[] = 'Choose at least one language.';
        }

        if ($nodes === []) {
            return ['errors' => array_merge($errors, ['The menu has no steps yet.']), 'warnings' => $warnings];
        }

        if (! $flowObj->hasNode($flowObj->start())) {
            $errors[] = 'The start step "'.$flowObj->start().'" does not exist.';
        }

        foreach ($nodes as $id => $node) {
            $where = 'Step "'.$id.'"';

            if (! preg_match('/^[a-z0-9_]{1,40}$/', (string) $id)) {
                $errors[] = $where.': the id may only use lower case letters, numbers and underscores (up to 40).';
            }

            $type = $node['type'] ?? '';
            if (! in_array($type, self::TYPES, true)) {
                $errors[] = $where.': unknown type "'.$type.'".';

                continue;
            }

            $text = $node['text'] ?? [];
            if (trim((string) ($text[$default] ?? '')) === '' && $type !== 'handover') {
                $errors[] = $where.': add the message text in '.strtoupper($default).'.';
            }
            foreach ($languages as $code) {
                if ($code !== $default && trim((string) ($text[$code] ?? '')) === '' && trim((string) ($text[$default] ?? '')) !== '') {
                    $warnings[] = $where.': no text in '.strtoupper($code).'. Customers who choose it will see '.strtoupper($default).'.';
                }
            }
            foreach ($text as $code => $value) {
                if (mb_strlen((string) $value) > $limits['text']) {
                    $warnings[] = $where.': the '.strtoupper((string) $code).' text is longer than '.$limits['text'].' characters, which WhatsApp may cut.';
                }
            }

            if (isset($node['goto']) && $node['goto'] !== '' && ! $flowObj->hasNode($node['goto'])) {
                $errors[] = $where.': "goes to" points at a step that does not exist ("'.$node['goto'].'").';
            }

            if ($type === 'options') {
                $options = $node['options'] ?? [];
                if ($options === []) {
                    $errors[] = $where.': add at least one option.';
                }
                if (count($options) > $limits['options_per_node']) {
                    $errors[] = $where.': more than '.$limits['options_per_node'].' options. WhatsApp lists show at most '.$limits['options_per_node'].'.';
                }

                $seen = [];
                foreach ($options as $i => $option) {
                    $optId = (string) ($option['id'] ?? '');
                    $label = (string) ($option['label'][$default] ?? '');
                    $which = $where.', option '.($i + 1);

                    if (! preg_match('/^[a-z0-9_]{1,40}$/', $optId)) {
                        $errors[] = $which.': the option id may only use lower case letters, numbers and underscores.';
                    } elseif (isset($seen[$optId])) {
                        $errors[] = $which.': the option id "'.$optId.'" is used twice in this step.';
                    }
                    $seen[$optId] = true;

                    if (trim($label) === '') {
                        $errors[] = $which.': add the button text in '.strtoupper($default).'.';
                    }
                    foreach ((array) ($option['label'] ?? []) as $code => $value) {
                        $max = count($options) <= 3 ? $limits['label_button'] : $limits['label_list'];
                        if (mb_strlen((string) $value) > $max) {
                            $warnings[] = $which.': the '.strtoupper((string) $code).' label is longer than '.$max.' characters, which WhatsApp will cut.';
                        }
                    }

                    $target = $option['goto'] ?? '';
                    $hasAction = ($option['action'] ?? '') === 'handover';
                    if ($target === '' && ! $hasAction) {
                        $errors[] = $which.': choose where this option goes, or make it hand over to staff.';
                    } elseif ($target !== '' && ! $flowObj->hasNode($target)) {
                        $errors[] = $which.': "goes to" points at a step that does not exist ("'.$target.'").';
                    }
                }
            }

            if ($type === 'ask') {
                if (! in_array($node['field'] ?? '', self::FIELDS, true)) {
                    $errors[] = $where.': choose what this question collects (name, phone, ...).';
                }
                if (($node['goto'] ?? '') === '') {
                    $errors[] = $where.': choose the next step.';
                }
            }

            if ($type === 'save_lead' && ! in_array($node['score'] ?? 'warm', ['hot', 'warm', 'cold'], true)) {
                $errors[] = $where.': the lead score must be hot, warm or cold.';
            }
        }

        foreach ($this->unreachable($flowObj) as $id) {
            $warnings[] = 'Step "'.$id.'" cannot be reached from the start.';
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
