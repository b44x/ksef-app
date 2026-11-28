<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body style="font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1f2937; font-size: 15px; line-height: 1.55;">
{!! nl2br(e($body)) !!}
<p style="color: #6b7280; font-size: 13px; margin-top: 28px;">{{ $company->name }}@if ($company->nip) · NIP {{ $company->nip }}@endif</p>
</body>
</html>
