<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ $i['number'] }}</title>
<style>
    @page { margin: 28px 34px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; }
    h1 { font-size: 17px; margin: 0 0 2px; color: #0a7d65; }
    .muted { color: #6b7280; }
    .head { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .head td { vertical-align: top; }
    .right { text-align: right; }
    .parties { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 16px; }
    .party { width: 50%; vertical-align: top; padding: 8px 10px; border: 1px solid #d1d5db; }
    .party .label { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 3px; }
    .party .name { font-size: 10.5px; font-weight: bold; margin-bottom: 2px; }
    table.lines { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.lines th { background: #0a9a7b; color: #fff; font-size: 8px; padding: 5px 4px; text-align: right; }
    table.lines th.l, table.lines td.l { text-align: left; }
    table.lines td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; text-align: right; }
    .summary { width: 48%; border-collapse: collapse; float: right; margin-bottom: 12px; }
    .summary th { text-align: right; font-size: 8px; padding: 3px 4px; background: #f3f4f6; }
    .summary td { text-align: right; padding: 3px 4px; border-bottom: 1px solid #e5e7eb; }
    .summary tr.total td { font-weight: bold; font-size: 10.5px; border-top: 2px solid #0a9a7b; }
    .clear { clear: both; }
    .box { border: 1px solid #d1d5db; padding: 8px 10px; margin-bottom: 8px; }
    .box b { font-size: 8px; text-transform: uppercase; color: #6b7280; display: block; margin-bottom: 2px; }
    .qr { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .qr td { text-align: center; vertical-align: top; width: 50%; font-size: 8px; }
    .qr img { width: 100px; height: 100px; }
    .foot { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 7.5px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<table class="head">
    <tr>
        <td>
            <h1>{{ __($i['title']) }} {{ $i['number'] }}</h1>
            @if ($i['ksef_number'])
                <div class="muted">{{ __('KSeF number') }}: {{ $i['ksef_number'] }}</div>
            @elseif ($i['offline'])
                <div class="muted">{{ __('Issued offline') }}</div>
            @endif
        </td>
        <td class="right">
            <div>{{ __('Issue date') }}: <b>{{ $i['issue_date'] }}</b></div>
            @if ($i['sale_date'])<div>{{ __('Sale date') }}: <b>{{ $i['sale_date'] }}</b></div>@endif
            @if ($i['due_date'])<div>{{ __('Due date') }}: <b>{{ $i['due_date'] }}</b></div>@endif
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        @foreach ([['Seller', $i['seller']], ['Buyer', $i['buyer']]] as [$label, $party])
            <td class="party">
                <div class="label">{{ __($label) }}</div>
                <div class="name">{{ $party['name'] }}</div>
                @if ($party['tax_id'])<div>{{ __('NIP') }}: {{ $party['tax_id'] }}</div>@endif
                @if ($party['address'])<div>{{ $party['address'] }}</div>@endif
                @if ($party['email'])<div class="muted">{{ $party['email'] }}</div>@endif
            </td>
        @endforeach
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th class="l" style="width:4%">{{ __('No.') }}</th>
            <th class="l">{{ __('Item or service') }}</th>
            <th>{{ __('Unit') }}</th>
            <th>{{ __('Qty') }}</th>
            <th>{{ ($i['lines'][0]['price_kind'] ?? 'net') === 'gross' ? __('Gross price') : __('Net price') }}</th>
            <th>{{ __('VAT') }}</th>
            <th>{{ __('Net value') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($i['lines'] as $line)
        <tr>
            <td class="l">{{ $line['n'] }}</td>
            <td class="l">{{ $line['name'] }}</td>
            <td>{{ $line['unit'] }}</td>
            <td>{{ $line['quantity'] }}</td>
            <td>{{ $line['unit_price'] }}</td>
            <td>{{ $line['vat_rate'] }}</td>
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

@if ($words)
    <div class="box"><b>{{ __('In words') }}</b>{{ $words }}</div>
@endif

@if ($i['payment'])
    <div class="box">
        <b>{{ __('Payment') }}</b>
        @if ($i['payment']['paid'])<div>{{ __('Paid') }}</div>@endif
        @if ($i['payment']['method'])<div>{{ __('Payment method') }}: {{ __($i['payment']['method']) }}</div>@endif
        @if ($i['payment']['due_date'])<div>{{ __('Due date') }}: {{ $i['payment']['due_date'] }}</div>@endif
        @foreach ($i['payment']['accounts'] as $account)<div>{{ __('Bank account') }}: {{ $account }}</div>@endforeach
    </div>
@endif

@if ($i['notes'])
    <div class="box"><b>{{ __('Notes') }}</b>{!! nl2br(e($i['notes'])) !!}</div>
@endif

@if ($qr)
    <table class="qr">
        <tr>
            <td><img src="{{ $qr['verification']['src'] }}" alt=""><br>{{ $qr['verification']['caption'] }}</td>
            @if ($qr['issuer'])
                <td><img src="{{ $qr['issuer']['src'] }}" alt=""><br>{{ $qr['issuer']['caption'] }}</td>
            @endif
        </tr>
    </table>
@endif

<div class="foot">{{ __('Invoice from the National e-Invoice System (KSeF)') }}</div>
</body>
</html>
