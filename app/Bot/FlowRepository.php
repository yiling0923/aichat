<?php

namespace App\Bot;

use App\Models\BotFlow;
use App\Models\Business;
use App\Support\CurrentBusiness;

/**
 * Loads a business's menu. A business that has none yet gets the sample menu.
 */
class FlowRepository
{
    public function __construct(private CurrentBusiness $current) {}

    public function forBusiness(Business $business): BotFlow
    {
        return $this->current->runAs($business, function () use ($business): BotFlow {
            $flow = BotFlow::query()->first();

            if ($flow === null) {
                $default = DefaultFlow::make();
                $flow = new BotFlow(['draft' => $default, 'published' => $default, 'published_at' => now()]);
                $flow->forceFill(['business_id' => $business->id])->save();
            }

            return $flow;
        });
    }

    public function published(Business $business): Flow
    {
        return new Flow($this->forBusiness($business)->published);
    }

    public function draft(Business $business): Flow
    {
        return new Flow($this->forBusiness($business)->draft);
    }
}
