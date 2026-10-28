<?php

namespace App\Ai;

use App\Bot\FlowRepository;
use App\Models\Business;
use App\Models\KnowledgeEntry;
use App\Models\Service;
use App\Support\CurrentBusiness;

/**
 * Everything the AI is allowed to say, collected from the business's own data: services and prices,
 * opening hours, the information texts in the menu, and the extra answers the owner added.
 */
class Knowledge
{
    private const DAYS = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];

    public function __construct(private CurrentBusiness $current, private FlowRepository $flows) {}

    public function build(Business $business): string
    {
        return $this->current->runAs($business, function () use ($business): string {
            $currency = (string) ($business->settings['currency'] ?? 'RM');
            $lines = ['BUSINESS: '.$business->name, 'TIME ZONE: '.$business->timezone];

            $hours = [];
            foreach (self::DAYS as $key => $label) {
                $h = $business->settings['hours'][$key] ?? null;
                $hours[] = $label.' '.(is_array($h) && count($h) === 2 ? $h[0].'-'.$h[1] : 'closed');
            }
            $lines[] = 'HOURS: '.implode('; ', $hours);

            foreach (Service::query()->where('active', true)->orderBy('sort')->orderBy('id')->get() as $s) {
                $name = collect((array) $s->name)->filter()->implode(' / ');
                $price = $s->price !== null && (float) $s->price > 0 ? ' | '.$currency.' '.rtrim(rtrim(number_format((float) $s->price, 2, '.', ''), '0'), '.') : ' | price: ask the team';
                $lines[] = 'SERVICE: '.$name.' | '.$s->duration_min.' min'.$price;
            }

            // The information screens of the published menu (prices, hours, location, promotions ...).
            foreach ($this->flows->published($business)->nodes() as $id => $node) {
                if (($node['type'] ?? '') !== 'message' || ! isset($node['text'])) {
                    continue;
                }
                $text = collect((array) $node['text'])->filter()->map(fn ($t) => trim(str_replace("\n", '; ', (string) $t)))->implode(' || ');
                $lines[] = 'INFO ('.$id.'): '.$text;
            }

            foreach (KnowledgeEntry::query()->where('active', true)->orderBy('id')->get() as $k) {
                $lines[] = 'FAQ: '.$k->title.' => '.collect((array) $k->answer)->filter()->implode(' || ');
            }

            return implode("\n", $lines);
        });
    }

    /** The owner's own instructions for tone (short). */
    public function instructions(Business $business): string
    {
        return trim((string) ($business->settings['ai']['instructions'] ?? ''));
    }
}
