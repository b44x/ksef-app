<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body style="font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1f2937; font-size: 15px; line-height: 1.5;">
<h2 style="color: #0a7d65; margin: 0 0 12px;">{{ $company->name }}</h2>

@if ($digest === null)
    <p>{{ __('This is a test message. If you can read it, the summaries will arrive at this address.') }}</p>
@else
    @if ($digest['offline'])
        <h3>{{ __('Offline invoices waiting for delivery to KSeF') }}</h3>
        <ul>
            @foreach ($digest['offline'] as $row)
                <li>
                    <b>{{ $row['number'] }}</b>:
                    @if ($row['deadline'] === null)
                        {{ __('the deadline is not known yet') }}
                    @elseif ($row['overdue'])
                        <span style="color: #b91c1c;">{{ __('overdue since :date', ['date' => $row['deadline']]) }}</span>
                    @else
                        {{ __('deliver by :date', ['date' => $row['deadline']]) }}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($digest['rejected'])
        <h3>{{ __('Invoices rejected by KSeF') }}</h3>
        <ul>
            @foreach ($digest['rejected'] as $row)
                <li><b>{{ $row['number'] }}</b>@if ($row['error']): {{ $row['error'] }}@endif</li>
            @endforeach
        </ul>
    @endif

    @if ($digest['purchases'])
        <h3>{{ __('New purchase invoices') }}</h3>
        <ul>
            @foreach ($digest['purchases'] as $row)
                <li><b>{{ $row['number'] }}</b>, {{ $row['seller'] }}: {{ $row['gross'] }} {{ $row['currency'] }}</li>
            @endforeach
        </ul>
    @endif
@endif

<p style="color: #6b7280; font-size: 13px; margin-top: 24px;">{{ __('Sent by your KSeF application. You can change what it sends in Company settings.') }}</p>
</body>
</html>
