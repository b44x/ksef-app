<?php

namespace App\Invoicing;

use App\Enums\OfflineMode;
use B4x\Ksef\Offline\OfflineDeadline;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/** When an offline invoice has to reach KSeF: Polish business days and holidays, as the SDK counts them. */
final class OfflineDeadlines
{
    public function __construct(private readonly OfflineDeadline $deadline = new OfflineDeadline) {}

    /**
     * The end of the last day for delivery. For Offline24 it counts from the issue date; for the others from the day
     * the failure or unavailability ended, which is not known until it has: then there is no deadline yet.
     */
    public function for(OfflineMode $mode, DateTimeInterface $issueDate, ?DateTimeInterface $anchor): ?Carbon
    {
        $from = $mode->needsAnchor() ? $anchor : $issueDate;
        if ($from === null) {
            return null;
        }

        // The SDK answers in Polish time; what is stored must be in the application's time zone, or it would read an hour off.
        return Carbon::instance($this->deadline->for($mode->sdk(), $from))->setTimezone((string) config('app.timezone'));
    }
}
