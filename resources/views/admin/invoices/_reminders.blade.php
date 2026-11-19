{{--
    The Reminders line on the office invoice page (spec 5.8, LED-03, D17).
    Shown on an overdue invoice, or one whose reminders are paused, when the plan has billing_full.
    $reminders = ['max' => int, 'canChange' => bool] or null (plan without billing_full).
--}}
@php
    $showReminders = $reminders && ($invoice->status === 'overdue' || $invoice->reminders_paused_at);
    $tz = auth()->user()->tenant?->timezone ?: config('app.timezone');
@endphp

@if ($showReminders)
<div class="panel" id="reminders-line">
  <div class="panel-h">Reminders</div>
  <div class="panel-b" style="font-size:13.5px">
    <div>
      {{ $invoice->reminder_count }} of {{ $reminders['max'] }} reminders sent.@if ($invoice->last_reminder_at) Last on {{ $invoice->last_reminder_at->copy()->setTimezone($tz)->format('d M Y') }}.@endif
    </div>

    @if ($invoice->reminders_paused_at)
      <div style="color:var(--muted);margin-top:6px">Reminders paused: {{ $invoice->reminders_paused_reason }}</div>
      @if ($reminders['canChange'])
        <form method="POST" action="{{ route('admin.invoices.reminders.resume', $invoice) }}" style="margin-top:10px">
          @csrf
          <button type="submit" class="btn ghost">Resume reminders</button>
        </form>
      @endif
    @elseif ($reminders['canChange'])
      <form method="POST" action="{{ route('admin.invoices.reminders.pause', $invoice) }}" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
        @csrf
        <input type="text" name="reason" minlength="3" maxlength="255" required placeholder="Reason, for example a dispute"
               style="flex:1;min-width:220px;height:38px;padding:0 12px;border:1px solid var(--line);border-radius:10px">
        <button type="submit" class="btn ghost">Pause reminders</button>
      </form>
      @error('reason') <div style="color:var(--red);font-size:12px;margin-top:6px">{{ $message }}</div> @enderror
    @endif
  </div>
</div>
@endif
