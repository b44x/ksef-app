<?php

namespace App\Http\Controllers;

use App\Ksef\ConnectionReport;
use App\Ksef\KsefGateway;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

class ConnectionCheckController extends Controller
{
    public function store(KsefGateway $gateway): RedirectResponse
    {
        $company = Company::query()->firstOrFail();

        return back()->with('report', self::present($gateway->check($company)));
    }

    /**
     * @return array{ok: bool, checks: list<array{name: string, outcome: string, message: string, details: list<string>}>}
     */
    public static function present(ConnectionReport $report): array
    {
        return [
            'ok' => $report->ok(),
            'checks' => array_map(static fn ($c): array => [
                'name' => $c->name,
                'outcome' => $c->outcome->value,
                'message' => $c->message,
                'details' => $c->details,
            ], $report->checks),
        ];
    }
}
