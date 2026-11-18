<div>
    <div class="card">
        <h1>{{ $company?->name }}</h1>
        <p>NIP {{ $company?->nip }} · {{ $company?->environment->label() }} · {{ $company?->auth_method->label() }}</p>
        <button type="button" wire:click="check" wire:loading.attr="disabled">Check the connection</button>
        <span wire:loading wire:target="check" class="hint">Checking…</span>
    </div>

    @include('livewire.partials.report', ['report' => $report, 'reportOk' => $reportOk])

    <div class="card">
        <h2>Invoices</h2>
        <p class="hint">Issuing and receiving invoices comes next (see the roadmap).</p>
    </div>
</div>
