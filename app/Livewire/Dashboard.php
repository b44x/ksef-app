<?php

namespace App\Livewire;

use App\Ksef\KsefGateway;
use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** @var list<array{name: string, outcome: string, message: string, details: list<string>}>|null */
    public ?array $report = null;

    public bool $reportOk = false;

    public function mount(): mixed
    {
        return Company::query()->exists() ? null : $this->redirectRoute('onboarding');
    }

    public function check(KsefGateway $gateway): void
    {
        $company = Company::query()->firstOrFail();
        $report = $gateway->check($company);
        $this->reportOk = $report->ok();
        $this->report = array_map(static fn ($c): array => [
            'name' => $c->name,
            'outcome' => $c->outcome->value,
            'message' => $c->message,
            'details' => $c->details,
        ], $report->checks);
    }

    public function render()
    {
        return view('livewire.dashboard', ['company' => Company::query()->first()]);
    }
}
