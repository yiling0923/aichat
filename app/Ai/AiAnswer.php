<?php

namespace App\Ai;

/** What the AI decided: the words for the customer, and what it understood. */
class AiAnswer
{
    public function __construct(
        public string $text,
        public string $intent,       // faq, booking, complaint, handover, other
        public string $mood,         // calm, happy, confused, angry
        public bool $wantsHuman,
    ) {}
}
