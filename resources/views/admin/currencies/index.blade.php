@extends('layouts.admin')
@section('title', 'Currencies and rates')

@section('content')
@php
  $base  = strtoupper($tenant->base_currency);
  $today = now($tenant->timezone ?: config('app.timezone'))->toDateString();
@endphp
<style>
  .cr-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:20px}
  .cr-card table{width:100%;border-collapse:collapse;font-size:13px}
  .cr-card th{text-align:left;padding:12px 16px;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line)}
  .cr-card td{padding:14px 16px;border-top:1px solid var(--line);vertical-align:middle}
  .cr-card tbody tr:first-child td{border-top:0}
  .cr-btn{display:inline-flex;align-items:center;font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;cursor:pointer}
  .cr-btn-primary{background:var(--yellow);border-color:var(--yellow)}
  .cr-input{font-size:13px;padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;min-width:200px}
  .cr-muted{color:var(--muted);font-size:12px}
  .cr-err{color:var(--red);font-size:12px;margin-top:6px}
</style>

<h1 style="font-family:var(--font-display);font-size:26px;font-weight:700;margin-bottom:4px">Currencies and rates</h1>
<p style="color:var(--muted);margin-bottom:22px">Turn on the currencies you quote and invoice in. Rates are read once a day, and Finance can enter a rate for any date.</p>

<div class="cr-card" style="padding:16px 20px;display:flex;align-items:center;gap:12px">
  <strong style="font-size:18px">{{ $base }}</strong>
  <span class="cr-muted">Base currency, locked</span>
</div>

<div class="cr-card">
  @if ($active->isEmpty())
    <div style="padding:40px 20px;text-align:center;color:var(--muted)">Only {{ $base }} is turned on. Add a currency to quote and invoice in it.</div>
  @else
    <table>
      <thead><tr><th>Currency</th><th>Rate</th><th>Date</th><th>Source</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
      @foreach ($active as $c)
        <tr>
          <td><strong>{{ $c->currency_code }}</strong></td>
          <td>
            @if ($c->latest)
              1 {{ $c->currency_code }} = {{ rtrim(rtrim(number_format((float) $c->latest->rate, 8, '.', ''), '0'), '.') }} {{ $base }}
            @else
              <span class="cr-muted">No rate yet</span>
            @endif
          </td>
          <td>
            @if ($c->latest)
              @if ($c->latest->rate_date->toDateString() === $today)
                {{ $c->latest->rate_date->format('d M Y') }}
              @else
                Rate from {{ $c->latest->rate_date->format('d M Y') }}
              @endif
            @endif
          </td>
          <td>{{ $c->latest ? ($c->latest->source === 'manual' ? 'Entered by hand' : 'Provider') : '' }}</td>
          <td style="text-align:right;white-space:nowrap">
            <a class="cr-btn" href="{{ route('admin.currencies.history', $c->currency_code) }}">Rates</a>
            <form method="POST" action="{{ route('admin.currencies.destroy', $c->currency_code) }}" style="display:inline"
                  onsubmit="return confirm('Turn off {{ $c->currency_code }}? Its rate history is kept.')">
              @csrf @method('DELETE')
              <button class="cr-btn" type="submit" @disabled($c->in_use)
                      title="{{ $c->in_use ? $c->currency_code . ' is used on open documents and cannot be turned off.' : '' }}">Turn off</button>
            </form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>

<form method="POST" action="{{ route('admin.currencies.store') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
  @csrf
  <select name="currency_code" class="cr-input" required>
    <option value="">Add currency…</option>
    @foreach ($addable as $code)
      <option value="{{ $code }}" @selected(old('currency_code') === $code)>{{ $code }}</option>
    @endforeach
  </select>
  <button type="submit" class="cr-btn cr-btn-primary">Add currency</button>
</form>
@error('currency_code') <div class="cr-err">{{ $message }}</div> @enderror

@if ($showAttribution)
  <p class="cr-muted" style="margin-top:28px"><a href="{{ $attribution['url'] }}" target="_blank" rel="noopener" style="color:inherit">{{ $attribution['text'] }}</a></p>
@endif
@endsection
