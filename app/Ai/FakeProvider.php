<?php

namespace App\Ai;

/**
 * A pretend AI that follows simple rules and quotes the business knowledge it is given.
 * It lets you try the whole AI mode for free, offline. It is not intelligent.
 */
class FakeProvider implements AiProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function complete(string $system, array $messages, array $schema, int $maxTokens): array
    {
        $last = '';
        foreach (array_reverse($messages) as $m) {
            if ($m['role'] === 'user') {
                $last = (string) $m['content'];
                break;
            }
        }
        $lower = mb_strtolower($last);

        if (isset($schema['properties']['services'])) {
            $json = ['services' => \App\Setup\SetupDrafter::readLines($last), 'faqs' => []];

            return $this->wrap($json, $system, $last);
        }

        if (isset($schema['properties']['translation'])) {
            // The pretend AI marks its work so tests and demos can see it (placeholders are kept).
            $json = ['translation' => '[translated] '.$last];

            return $this->wrap($json, $system, $last);
        }

        if (isset($schema['properties']['summary'])) {
            $json = ['summary' => 'Customer chatted with the business. (Pretend summary from the fake AI.) Last message: '.mb_substr($last, 0, 120)];

            return $this->wrap($json, $system, $last);
        }

        $has = fn (array $words) => (bool) array_filter($words, fn ($w) => str_contains($lower, $w));
        $lines = fn (string $prefix) => array_values(array_map(fn ($l) => trim(substr($l, strlen($prefix))), array_filter(explode("\n", $system), fn ($l) => str_starts_with($l, $prefix))));

        if (str_contains($last, 'FAKE_BAD_NUMBERS')) {
            $json = ['reply' => 'Today only the facial is RM 9999 with 99% off!', 'intent' => 'faq', 'mood' => 'happy', 'wants_human' => false];
        } elseif ($has(['angry', 'terrible', 'worst', 'refund', 'complaint', 'scam', 'rude', '投诉', '退款', '生气'])) {
            $json = ['reply' => 'I am very sorry about this. I am passing you to our team right now.', 'intent' => 'complaint', 'mood' => 'angry', 'wants_human' => true];
        } elseif ($has(['human', 'real person', 'staff', 'agent', '真人', '人工'])) {
            $json = ['reply' => 'Of course, I will connect you with our team.', 'intent' => 'handover', 'mood' => 'calm', 'wants_human' => true];
        } elseif ($has(['book', 'appointment', 'reserve', 'schedule', 'slot', 'come in', 'visit', '预约'])) {
            $json = ['reply' => 'I can help you book. Tap Book now and choose a service and a time.', 'intent' => 'booking', 'mood' => 'calm', 'wants_human' => false];
        } elseif ($has(['price', 'pricing', 'rates', 'charge', 'cost', 'how much', 'fee', '多少钱', '价格'])) {
            $services = $lines('SERVICE: ');
            $json = ['reply' => $services === [] ? 'Please ask our team for prices.' : 'Here are our services: '.implode('; ', array_slice($services, 0, 5)).'.', 'intent' => 'faq', 'mood' => 'calm', 'wants_human' => $services === []];
        } elseif ($has(['hours', 'open', 'close', 'when are you', 'what time', 'timing', '营业'])) {
            $hours = $lines('HOURS: ');
            $json = ['reply' => $hours === [] ? 'Please ask our team for opening hours.' : 'Our opening hours: '.$hours[0].'.', 'intent' => 'faq', 'mood' => 'calm', 'wants_human' => $hours === []];
        } elseif ($has(['thank', '谢谢'])) {
            $json = ['reply' => 'You are welcome!', 'intent' => 'other', 'mood' => 'happy', 'wants_human' => false];
        } else {
            $json = ['reply' => 'I can help with prices, booking, opening hours and location. What would you like to know?', 'intent' => 'other', 'mood' => 'confused', 'wants_human' => false];
        }

        return $this->wrap($json, $system, $last);
    }

    /** @param array<string, mixed> $json */
    private function wrap(array $json, string $system, string $last): array
    {
        return ['text' => json_encode($json, JSON_UNESCAPED_UNICODE), 'input_tokens' => (int) ceil(mb_strlen($system.$last) / 4), 'output_tokens' => (int) ceil(mb_strlen(json_encode($json, JSON_UNESCAPED_UNICODE)) / 4), 'model' => 'fake'];
    }
}
