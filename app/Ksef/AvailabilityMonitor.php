<?php

namespace App\Ksef;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Keeps the last answer of the Ministry's availability service, so that a page never waits for the network: pages read
 * the stored answer, and a scheduled run (or, when none is stored, one right after the response) refreshes it.
 */
final class AvailabilityMonitor
{
    /** How long an answer is believed. Failures are announced for hours, so five minutes is plenty. */
    private const TTL_SECONDS = 300;

    /** When the service itself cannot be asked, try again sooner. */
    private const UNKNOWN_TTL_SECONDS = 60;

    public function __construct(private readonly KsefGateway $gateway) {}

    /** The stored answer for the company's environment, or null when there is none (yet). */
    public function current(Company $company): ?Availability
    {
        $stored = Cache::get($this->key($company));
        if (! is_array($stored)) {
            return null;
        }

        // Stored as plain data: the cache does not unserialise application classes.
        $state = AvailabilityState::tryFrom((string) ($stored['state'] ?? ''));

        return $state === null ? null : new Availability($state, $stored['notices'] ?? [], isset($stored['checked_at']) ? new \DateTimeImmutable($stored['checked_at']) : null);
    }

    /** Asks the availability service and stores what it says. Never throws: an unreachable service is "unknown". */
    public function refresh(Company $company): Availability
    {
        try {
            $availability = $this->gateway->availability($company);
            $ttl = self::TTL_SECONDS;
        } catch (Throwable) {
            $availability = new Availability(AvailabilityState::Unknown, [], new \DateTimeImmutable);
            $ttl = self::UNKNOWN_TTL_SECONDS;
        }

        Cache::put($this->key($company), [
            'state' => $availability->state->value,
            'notices' => $availability->notices,
            'checked_at' => ($availability->checkedAt ?? new \DateTimeImmutable)->format(DATE_ATOM),
        ], $ttl);

        return $availability;
    }

    /** Takes the lock for one refresh at a time: a burst of page loads starts one request to the Ministry, not many. */
    public function claimRefresh(Company $company): bool
    {
        return Cache::add($this->key($company).':refreshing', true, 30);
    }

    private function key(Company $company): string
    {
        // The answer depends on the environment, not on the company.
        return 'ksef-availability:'.$company->environment->value;
    }
}
