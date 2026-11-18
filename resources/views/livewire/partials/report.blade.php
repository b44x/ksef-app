@if ($report !== null)
    <div class="card" wire:key="report">
        <h2>Connection check</h2>
        <div class="banner {{ $reportOk ? 'ok' : 'bad' }}">
            {{ $reportOk ? 'Everything needed works.' : 'Something needs attention before you can send invoices.' }}
        </div>
        <ul class="checks">
            @foreach ($report as $check)
                <li>
                    <span class="badge {{ $check['outcome'] }}">{{ $check['outcome'] }}</span>
                    <strong>{{ $check['name'] }}</strong>
                    <span>{{ $check['message'] }}</span>
                    @foreach ($check['details'] as $detail)
                        <small>{{ $detail }}</small>
                    @endforeach
                </li>
            @endforeach
        </ul>
    </div>
@endif
