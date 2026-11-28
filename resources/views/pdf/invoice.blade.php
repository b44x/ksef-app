@php
    use App\Pdf\TemplateSettings as T;
    $accent = $s['accent'];
    $onAccent = T::contrast($accent);
    $soft = T::tint($accent, 0.92);
    $font = $fonts[$s['font']];
    $size = $sizes[$s['size']];
    $layout = $s['layout'];
    $show = $s['show'];
    $logo = $s['logo']['show'] && $i['logo'] ? $i['logo'] : null;
    $logoRight = $s['logo']['position'] === 'right';
    $header = $layout === 'modern';
    $netLabel = ($i['lines'][0]['price_kind'] ?? 'net') === 'gross' ? __('Gross price') : __('Net price');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ $i['number'] }}</title>
<style>
    @page { margin: 28px 34px 44px; }
    body { font-family: '{{ $font }}', sans-serif; font-size: {{ $size }}px; color: #1f2937; }
    h1 { font-size: {{ $size + 8 }}px; margin: 0 0 2px; color: {{ $header ? $onAccent : $accent }}; }
    .muted { color: {{ $header ? $onAccent : '#6b7280' }}; }
    .grey { color: #6b7280; }
    .right { text-align: right; }
    table { border-collapse: collapse; }
    .head { width: 100%; margin-bottom: 16px; }
    .head td { vertical-align: top; }
    @if ($header)
    .band { background: {{ $accent }}; color: {{ $onAccent }}; padding: 14px 16px; margin: -28px -34px 18px; }
    .band .dates, .band .dates b { color: {{ $onAccent }}; }
    @endif
    .logo img { height: {{ $s['logo']['height'] }}px; }
    .parties { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 16px; }
    .party { width: 50%; vertical-align: top; padding: 8px 10px;
        @if ($layout === 'classic') border: 1px solid #d1d5db; @elseif ($layout === 'modern') background: {{ $soft }}; @else border-top: 1px solid {{ $accent }}; @endif }
    .party .label { font-size: {{ $size - 1 }}px; text-transform: uppercase; letter-spacing: .5px; color: {{ $layout === 'minimal' ? $accent : '#6b7280' }}; margin-bottom: 3px; }
    .party .name { font-size: {{ $size + 1.5 }}px; font-weight: bold; margin-bottom: 2px; }
    table.lines { width: 100%; margin-bottom: 12px; }
    table.lines th { font-size: {{ $size - 1 }}px; padding: 5px 4px; text-align: right;
        @if ($layout === 'minimal') color: {{ $accent }}; border-bottom: 1.5px solid {{ $accent }}; @else background: {{ $accent }}; color: {{ $onAccent }}; @endif }
    table.lines th.l, table.lines td.l { text-align: left; }
    table.lines td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; text-align: right; }
    @if ($s['stripes']) table.lines tr.odd td { background: {{ $soft }}; } @endif
    .summary { width: 48%; float: right; margin-bottom: 12px; }
    .summary th { text-align: right; font-size: {{ $size - 1 }}px; padding: 3px 4px; background: {{ $layout === 'minimal' ? '#ffffff' : '#f3f4f6' }}; }
    .summary td { text-align: right; padding: 3px 4px; border-bottom: 1px solid #e5e7eb; }
    .summary tr.total td { font-weight: bold; font-size: {{ $size + 1.5 }}px; border-top: 2px solid {{ $accent }}; }
    .clear { clear: both; }
    .box { padding: 8px 10px; margin-bottom: 8px;
        @if ($layout === 'classic') border: 1px solid #d1d5db; @elseif ($layout === 'modern') background: {{ $soft }}; @else border-left: 2px solid {{ $accent }}; @endif }
    .box b { font-size: {{ $size - 1 }}px; text-transform: uppercase; color: #6b7280; display: block; margin-bottom: 2px; }
    .qr { width: 100%; margin-top: 10px; }
    .qr td { text-align: center; vertical-align: top; width: 50%; font-size: {{ $size - 1 }}px; }
    .qr img { width: 100px; height: 100px; }
    .sign { width: 100%; margin-top: 36px; }
    .sign td { width: 40%; text-align: center; border-top: 1px solid #9ca3af; padding-top: 3px; font-size: {{ $size - 1 }}px; color: #6b7280; }
    .sign td.gap { width: 20%; border: 0; }
    .foot { position: fixed; bottom: -28px; left: 0; right: 0; font-size: {{ $size - 1.5 }}px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
@if ($header)<div class="band">@endif
<table class="head">
    <tr>
        @if ($logo && ! $logoRight)<td class="logo" style="width:1%; padding-right:14px"><img src="{{ $logo }}" alt=""></td>@endif
        <td>
            <h1>{{ __($i['title']) }} {{ $i['number'] }}</h1>
            @if ($i['ksef_number'])
                <div class="muted">{{ __('KSeF number') }}: {{ $i['ksef_number'] }}</div>
            @elseif ($i['offline'])
                <div class="muted">{{ __('Issued offline') }}</div>
            @endif
        </td>
        <td class="right dates">
            <div>{{ __('Issue date') }}: <b>{{ $i['issue_date'] }}</b></div>
            @if ($i['sale_date'])<div>{{ __('Sale date') }}: <b>{{ $i['sale_date'] }}</b></div>@endif
            @if ($i['due_date'])<div>{{ __('Due date') }}: <b>{{ $i['due_date'] }}</b></div>@endif
        </td>
        @if ($logo && $logoRight)<td class="logo right" style="width:1%; padding-left:14px"><img src="{{ $logo }}" alt=""></td>@endif
    </tr>
</table>
@if ($header)</div>@endif

@if (! empty($i['correction']))
    <div class="box"><b>{{ __('Correction') }}</b>{{ __('Corrects invoice :number of :date', ['number' => $i['correction']['of_number'], 'date' => $i['correction']['of_date']]) }}@if ($i['correction']['reason']) &mdash; {{ $i['correction']['reason'] }}@endif</div>
@endif

<table class="parties">
    <tr>
        @foreach ([['Seller', $i['seller'], 'seller_email'], ['Buyer', $i['buyer'], 'buyer_email']] as [$label, $party, $mail])
            <td class="party">
                <div class="label">{{ __($label) }}</div>
                <div class="name">{{ $party['name'] }}</div>
                @if ($party['tax_id'])<div>{{ __('NIP') }}: {{ $party['tax_id'] }}</div>@endif
                @if ($party['address'])<div>{{ $party['address'] }}</div>@endif
                @if ($party['email'] && $show[$mail])<div class="grey">{{ $party['email'] }}</div>@endif
            </td>
        @endforeach
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th class="l" style="width:4%">{{ __('No.') }}</th>
            <th class="l">{{ __('Item or service') }}</th>
            @if ($show['unit'])<th>{{ __('Unit') }}</th>@endif
            <th>{{ __('Qty') }}</th>
            <th>{{ $netLabel }}</th>
            @if ($show['vat_column'])<th>{{ __('VAT') }}</th>@endif
            <th>{{ __('Net value') }}</th>
        </tr>
    </thead>
    <tbody>
    @php $group = null; @endphp
    @foreach ($i['lines'] as $line)
        @if (($line['state'] ?? 'current') !== 'current' && ($line['state'] ?? null) !== $group)
            @php $group = $line['state']; @endphp
            <tr><td colspan="7" class="l" style="background: {{ $soft }}; font-weight: bold; padding: 4px">{{ $group === 'before' ? __('Before the correction') : __('After the correction') }}</td></tr>
        @endif
        <tr class="{{ $loop->odd ? 'odd' : 'even' }}">
            <td class="l">{{ $line['n'] }}</td>
            <td class="l">{{ $line['name'] }}</td>
            @if ($show['unit'])<td>{{ $line['unit'] }}</td>@endif
            <td>{{ $line['quantity'] }}</td>
            <td>{{ $line['unit_price'] }}</td>
            @if ($show['vat_column'])<td>{{ $line['vat_rate'] }}</td>@endif
            <td>{{ $line['net'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="summary">
    <tr><th>{{ __('Rate') }}</th><th>{{ __('Net total') }}</th><th>{{ __('VAT total') }}</th></tr>
    @foreach ($i['summary'] as $row)
        <tr><td>{{ $row['label'] }}</td><td>{{ $row['net'] }}</td><td>{{ $row['vat'] ?? '—' }}</td></tr>
    @endforeach
    <tr class="total"><td colspan="2">{{ __('To pay') }} ({{ $i['currency'] }})</td><td>{{ $i['total']['gross'] }}</td></tr>
</table>
<div class="clear"></div>

@if (! empty($i['exchange']))
    <div class="box"><b>{{ __('Exchange rate') }}</b>{{ __('NBP average rate :rate PLN/:currency of :date', ['rate' => $i['exchange']['rate'], 'currency' => $i['currency'], 'date' => $i['exchange']['date'] ?? '—']) }}@if ($i['exchange']['vat_pln']) &mdash; {{ __('VAT in PLN') }}: {{ $i['exchange']['vat_pln'] }}@endif</div>
@endif

@if ($words && $show['words'])
    <div class="box"><b>{{ __('In words') }}</b>{{ $words }}</div>
@endif

@if ($i['payment'] && $show['payment'])
    <div class="box">
        <b>{{ __('Payment') }}</b>
        @if ($i['payment']['paid'])<div>{{ __('Paid') }}</div>@endif
        @if ($i['payment']['method'])<div>{{ __('Payment method') }}: {{ __($i['payment']['method']) }}</div>@endif
        @if ($i['payment']['due_date'])<div>{{ __('Due date') }}: {{ $i['payment']['due_date'] }}</div>@endif
        @if ($show['bank_account'])
            @foreach ($i['payment']['accounts'] as $account)<div>{{ __('Bank account') }}: {{ $account }}</div>@endforeach
        @endif
    </div>
@endif

@if ($i['notes'] && $show['notes'])
    <div class="box"><b>{{ __('Notes') }}</b>{!! nl2br(e($i['notes'])) !!}</div>
@endif

@if ($qr && $show['qr'])
    <table class="qr">
        <tr>
            <td><img src="{{ $qr['verification']['src'] }}" alt=""><br>{{ $qr['verification']['caption'] }}</td>
            @if ($qr['issuer'])
                <td><img src="{{ $qr['issuer']['src'] }}" alt=""><br>{{ $qr['issuer']['caption'] }}</td>
            @endif
        </tr>
    </table>
@endif

@if ($show['signatures'])
    <table class="sign"><tr><td>{{ __('Issued by') }}</td><td class="gap"></td><td>{{ __('Received by') }}</td></tr></table>
@endif

<div class="foot">
    @if (! empty($i['proforma'])){{ __('This is a proforma invoice. It is not a VAT invoice.') }}@elseif ($show['footer'] && $s['footer_text'] !== ''){{ $s['footer_text'] }}@else{{ __('Invoice from the National e-Invoice System (KSeF)') }}@endif
</div>
</body>
</html>
