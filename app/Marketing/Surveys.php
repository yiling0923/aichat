<?php

namespace App\Marketing;

use App\Bot\Strings;
use App\Bot\Text;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Conversation;
use App\Models\Survey;
use App\Models\Task;
use App\Services\N8nNotifier;

/**
 * Records a customer's 1 to 5 answer to the satisfaction survey, alerts the team about low scores,
 * and sends the review link after a good one.
 */
class Surveys
{
    public function __construct(private N8nNotifier $n8n) {}

    /**
     * @return list<\App\Models\Message>|null the bot's replies, or null when the input was not a rating
     */
    public function answer(Conversation $conversation, int $surveyId, string $text, ?string $optionId, string $language): ?array
    {
        $survey = Survey::query()->with(['booking', 'customer'])->find($surveyId);

        if ($survey === null || $survey->answered_at !== null) {
            return null;
        }

        $rating = $this->parse($text, $optionId);

        if ($rating === null) {
            return null;
        }

        $survey->update(['rating' => $rating, 'answered_at' => now()]);
        $customer = $survey->customer;
        $business = $conversation->business;

        $out = [$conversation->addMessage('out', 'bot', Strings::get('survey_thanks', $language))];

        $this->n8n->send('survey.answered', [
            'business' => $business->slug, 'survey_id' => $survey->id, 'rating' => $rating,
            'customer_name' => $customer?->name, 'customer_phone' => $customer?->phone, 'booking_id' => $survey->booking_id,
        ]);

        if ($rating <= 2) {
            $out[] = $conversation->addMessage('out', 'bot', Strings::get('survey_low', $language));
            Task::create([
                'title' => 'Low survey score ('.$rating.'/5): call '.($customer?->name ?: 'the customer'),
                'customer_id' => $customer?->id,
                'conversation_id' => $conversation->id,
            ]);
            app(\App\Services\Notifier::class)->notify($business, 'survey', 'Low rating ('.$rating.'/5) from '.($customer?->name ?: 'a customer'), 'A manager should follow up.',
                $customer ? route('customers.show', $customer->id) : route('tasks'), ['owner', 'manager']);
            $this->n8n->send('survey.low_score', [
                'business' => $business->slug, 'survey_id' => $survey->id, 'rating' => $rating, 'conversation_id' => $conversation->id,
                'customer_name' => $customer?->name, 'customer_phone' => $customer?->phone,
            ]);

            return $out;
        }

        $review = Automation::query()->where('key', 'review_request')->first();
        $url = trim((string) ($review?->settings['review_url'] ?? ''));

        if ($review?->enabled && $url !== '' && $rating >= (int) ($review->settings['min_rating'] ?? 4)) {
            $message = (array) $review->message;
            $body = Text::fill((string) ($message[$language] ?? $message['en'] ?? ''), [
                'name' => $customer?->name, 'review_url' => $url, 'business' => $business->name,
                'service' => $survey->booking?->service?->label($language),
            ]);
            $out[] = $conversation->addMessage('out', 'bot', $body);
            $survey->update(['review_sent_at' => now()]);

            AutomationLog::create([
                'automation_key' => 'review_request', 'customer_id' => $customer?->id, 'booking_id' => $survey->booking_id,
                'status' => 'sent', 'created_at' => now(),
            ]);
        }

        return $out;
    }

    private function parse(string $text, ?string $optionId): ?int
    {
        if ($optionId !== null && preg_match('/^rate_([1-5])$/', $optionId, $m)) {
            return (int) $m[1];
        }

        $text = trim($text);

        return preg_match('/^[1-5]$/', $text) ? (int) $text : null;
    }
}
