<?php

namespace App\Ksef;

use DateTimeImmutable;

/** KSeF's availability at a moment, with the announcements that explain it. */
final readonly class Availability
{
    /**
     * @param  list<array{title: string, text: string, start: string, end: string|null}>  $notices  as the Ministry worded them
     */
    public function __construct(
        public AvailabilityState $state,
        public array $notices = [],
        public ?DateTimeImmutable $checkedAt = null,
    ) {}
}
