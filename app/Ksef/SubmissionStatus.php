<?php

namespace App\Ksef;

/** KSeF's verdict on a submitted invoice at one moment. */
final readonly class SubmissionStatus
{
    /**
     * @param  list<string>  $details  the reasons, when rejected
     */
    public function __construct(
        public SubmissionState $state,
        public ?string $ksefNumber = null,
        public int $code = 0,
        public string $description = '',
        public array $details = [],
    ) {}
}
