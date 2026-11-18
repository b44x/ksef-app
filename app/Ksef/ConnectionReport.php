<?php

namespace App\Ksef;

final readonly class ConnectionReport
{
    /**
     * @param  list<ConnectionCheck>  $checks
     */
    public function __construct(public array $checks) {}

    /** True when nothing failed (warnings are allowed). */
    public function ok(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->outcome === CheckOutcome::Failed) {
                return false;
            }
        }

        return true;
    }
}
