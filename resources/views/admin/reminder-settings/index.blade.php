@extends('layouts.admin')
@section('title', 'Overdue reminders')

@section('content')
<style>
  .rs-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;max-width:560px}
  .rs-field{margin-bottom:20px}
  .rs-field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}
  .rs-in{height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;width:100%}
  .rs-hint{color:var(--muted);font-size:12px}
  .rs-err{color:var(--red);font-size:12px}
</style>

<h1 style="font-family:var(--font-display);font-size:28px;font-weight:700;margin-bottom:4px">Overdue reminders</h1>
<p style="color:var(--muted);margin-bottom:24px">When clients are reminded about invoices that are past their due date.</p>

<form method="POST" action="{{ route('admin.reminder-settings.update') }}" class="rs-card">
  @csrf @method('PUT')

  <div class="rs-field">
    <label style="display:flex;gap:10px;align-items:center">
      <input type="checkbox" name="overdue_reminders_enabled" value="1" @checked(old('overdue_reminders_enabled', $setting->overdue_reminders_enabled))>
      Send overdue reminders
    </label>
  </div>

  <div class="rs-field">
    <label for="first">First reminder</label>
    <input id="first" class="rs-in" type="number" name="overdue_first_after_days" min="0" max="30" value="{{ old('overdue_first_after_days', $setting->overdue_first_after_days) }}">
    <span class="rs-hint">days after the due date (0 to 30)</span>
    @error('overdue_first_after_days') <div class="rs-err">{{ $message }}</div> @enderror
  </div>

  <div class="rs-field">
    <label for="gap">Then every</label>
    <input id="gap" class="rs-in" type="number" name="overdue_repeat_days" min="1" max="30" value="{{ old('overdue_repeat_days', $setting->overdue_repeat_days) }}">
    <span class="rs-hint">days (1 to 30)</span>
    @error('overdue_repeat_days') <div class="rs-err">{{ $message }}</div> @enderror
  </div>

  <div class="rs-field">
    <label for="max">At most</label>
    <input id="max" class="rs-in" type="number" name="overdue_max_reminders" min="1" max="10" value="{{ old('overdue_max_reminders', $setting->overdue_max_reminders) }}">
    <span class="rs-hint">reminders for each invoice (1 to 10)</span>
    @error('overdue_max_reminders') <div class="rs-err">{{ $message }}</div> @enderror
  </div>

  <p class="rs-hint" style="margin-bottom:20px">
    Reminders go to the client contacts the invoice was sent to, and to every active Finance user.
    Clients get WhatsApp when your WhatsApp is live and the contact has agreed to it, otherwise email. Finance gets email.
    A change applies from the next day's run, which happens at 09:00 in your company's time zone.
  </p>

  <button type="submit" style="padding:11px 22px;border-radius:999px;border:0;background:var(--yellow);font-weight:700;cursor:pointer">Save</button>
</form>
@endsection
