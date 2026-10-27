<?php

namespace App\Support;

use App\Models\Business;
use Closure;

/**
 * Holds the business the current request or job works for.
 * Every business-owned model is limited to this business, and fails closed
 * (returns nothing) when none is set.
 */
class CurrentBusiness
{
    private ?Business $business = null;

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }

    /**
     * Run a callback as a given business, then restore the previous one.
     */
    public function runAs(Business $business, Closure $callback): mixed
    {
        $previous = $this->business;
        $this->business = $business;

        try {
            return $callback();
        } finally {
            $this->business = $previous;
        }
    }
}
