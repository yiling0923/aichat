<?php

namespace Tests;

use App\Models\Business;
use App\Models\Conversation;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a conversation (with optional visitor and bot messages) inside a business.
     */
    protected function conversationIn(Business $business, ?string $firstMessage = null): Conversation
    {
        return app(CurrentBusiness::class)->runAs($business, function () use ($firstMessage) {
            $conversation = Conversation::startFor('web');

            if ($firstMessage !== null) {
                $conversation->addMessage('in', 'customer', $firstMessage);
            }

            return $conversation;
        });
    }
}
