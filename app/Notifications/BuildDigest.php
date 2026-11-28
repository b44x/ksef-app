<?php

namespace App\Notifications;

use App\Enums\InvoiceStatus;
use App\Models\Company;

/**
 * What the daily summary tells a company: offline invoices that wait for delivery (every day, until they are
 * delivered), and what is new since the last summary: purchase invoices and invoices KSeF rejected.
 */
final class BuildDigest
{
    /**
     * @return array{offline: list<array{number: string, deadline: string|null, overdue: bool}>, purchases: list<array{number: string, seller: string, gross: string, currency: string}>, rejected: list<array{number: string, error: string}>}|null null when there is nothing to tell
     */
    public function for(Company $company): ?array
    {
        $since = $company->last_digest_at;

        $offline = ! $company->notify_offline ? [] : $company->invoices()
            ->where('status', InvoiceStatus::Issued)->whereNotNull('offline_mode')
            ->orderBy('offline_deadline')->get()
            ->map(fn ($i): array => [
                'number' => (string) $i->number,
                'deadline' => $i->offline_deadline?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'overdue' => $i->offline_deadline?->isPast() ?? false,
            ])->values()->all();

        $purchases = ! $company->notify_purchases ? [] : $company->receivedInvoices()
            ->when($since, fn ($q) => $q->where('received_at', '>', $since))
            ->orderByDesc('issue_date')->limit(50)->get()
            ->map(fn ($i): array => ['number' => (string) $i->number, 'seller' => $i->seller_name, 'gross' => $i->gross, 'currency' => $i->currency])->values()->all();

        $rejected = ! $company->notify_rejected ? [] : $company->invoices()
            ->where('status', InvoiceStatus::Rejected)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->get()
            ->map(fn ($i): array => ['number' => (string) $i->number, 'error' => (string) strtok((string) $i->ksef_error, "\n")])->values()->all();

        if ($offline === [] && $purchases === [] && $rejected === []) {
            return null;
        }

        return ['offline' => $offline, 'purchases' => $purchases, 'rejected' => $rejected];
    }
}
