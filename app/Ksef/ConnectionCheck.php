<?php

namespace App\Ksef;

/** One finding of the connection check: what was looked at, how it went, and what to do about it. */
final readonly class ConnectionCheck
{
    /**
     * @param  list<string>  $details
     */
    public function __construct(
        public string $name,
        public CheckOutcome $outcome,
        public string $message,
        public array $details = [],
    ) {}
}
