<div>
    <div class="card">
        <h1>Connect your company to KSeF</h1>
        <form wire:submit="save">
            <label for="name">Company name</label>
            <input id="name" type="text" wire:model="name" autocomplete="organization">
            @error('name') <div class="error">{{ $message }}</div> @enderror

            <label for="nip">NIP</label>
            <input id="nip" type="text" wire:model="nip" inputmode="numeric" maxlength="10">
            @error('nip') <div class="error">{{ $message }}</div> @enderror

            <label for="environment">Environment</label>
            <select id="environment" wire:model="environment">
                @foreach ($environments as $env)
                    <option value="{{ $env->value }}">{{ $env->label() }}</option>
                @endforeach
            </select>
            <div class="hint">Start on TEST: nothing there is real. Production sends real invoices.</div>
            @error('environment') <div class="error">{{ $message }}</div> @enderror

            <label for="authMethod">How does the company sign in?</label>
            <select id="authMethod" wire:model.live="authMethod">
                @foreach ($methods as $method)
                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                @endforeach
            </select>
            @error('authMethod') <div class="error">{{ $message }}</div> @enderror

            @if ($authMethod === 'token')
                <label for="token">KSeF token</label>
                <input id="token" type="password" wire:model="token" autocomplete="off" placeholder="{{ $hasSecrets ? 'Saved. Leave blank to keep it.' : '' }}">
                @error('token') <div class="error">{{ $message }}</div> @enderror
            @elseif ($authMethod === 'certificate')
                <label for="certificate">Certificate (PEM)</label>
                <textarea id="certificate" wire:model="certificate" spellcheck="false" placeholder="{{ $hasSecrets ? 'Saved. Leave blank to keep it.' : '-----BEGIN CERTIFICATE-----' }}"></textarea>
                @error('certificate') <div class="error">{{ $message }}</div> @enderror

                <label for="privateKey">Private key (PEM)</label>
                <textarea id="privateKey" wire:model="privateKey" spellcheck="false" placeholder="{{ $hasSecrets ? 'Saved. Leave blank to keep it.' : '-----BEGIN PRIVATE KEY-----' }}"></textarea>
                @error('privateKey') <div class="error">{{ $message }}</div> @enderror

                <label for="passphrase">Key passphrase (if the key is encrypted)</label>
                <input id="passphrase" type="password" wire:model="passphrase" autocomplete="off">
            @else
                <p class="hint">You sign each login request outside the app (a trusted profile or a signature app). Nothing secret is stored here.</p>
            @endif
            <div class="hint">Secrets are stored encrypted and are never shown again.</div>

            <button type="submit" wire:loading.attr="disabled">Save and check the connection</button>
        </form>
    </div>

    @include('livewire.partials.report', ['report' => $report, 'reportOk' => $reportOk])
</div>
