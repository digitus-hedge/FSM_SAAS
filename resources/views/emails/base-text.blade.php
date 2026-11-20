{{-- Plain-text version of emails.base --}}
{!! $m['company']['name'] !!}
{!! str_repeat('=', 40) !!}

{!! $m['heading'] !!}

@if (! empty($m['intro']))
{!! $m['intro'] !!}

@endif
@if (! empty($m['code']))
Your code: {!! $m['code'] !!}

@endif
@foreach (($m['rows'] ?? []) as $label => $value)
{!! $label !!}: {!! str_replace("\n", ' / ', (string) $value) !!}
@endforeach
@if (! empty($m['rows']))

@endif
@if (! empty($m['button']))
{!! $m['button']['label'] !!}: {!! $m['button']['url'] !!}

@endif
@if (! empty($m['note']))
{!! $m['note'] !!}

@endif
--
{!! $m['company']['name'] !!}
@if (! empty($m['company']['email']) || ! empty($m['company']['phone']))
{!! collect([$m['company']['email'] ?? null, $m['company']['phone'] ?? null])->filter()->implode(' | ') !!}
@endif
This is an automated message.
