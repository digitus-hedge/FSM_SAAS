@extends('layouts.admin')
@section('title', 'Approval rules')

@section('content')
@include('admin.partials._page')
@php($canEdit = \App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'workflow.manage'))

<div class="page-head">
  <div>
    <h1>Approval rules</h1>
    <p>How a {{ term('service request') }} of each category becomes Approved.@unless ($canEdit) You can look at the rules but not change them.@endunless</p>
  </div>
</div>

<div class="card">
  <table>
    <thead><tr><th>Category</th><th>Mode</th><th>Amount limit</th><th>Second approver</th><th></th></tr></thead>
    <tbody>
    @foreach ($categories as $c)
      @php($rule = $rules[$c->id] ?? null)
      <tr>
        <td style="font-weight:600">{{ $c->name }}
          <form id="rule-{{ $c->id }}" method="POST" action="{{ route('admin.approval-rules.update', $c) }}">@csrf</form></td>
        <td>
          <select name="mode" form="rule-{{ $c->id }}" class="sel" @disabled(!$canEdit)>
            @foreach ($allModes as $value => $label)
              <option value="{{ $value }}" @selected($c->approval_mode === $value) @disabled(!$allowed[$value] && $c->approval_mode !== $value)>
                {{ $label }}{{ $allowed[$value] ? '' : ' (Not in your plan)' }}
              </option>
            @endforeach
          </select>
          @if (! $allowed[$c->approval_mode])<div class="hint">Runs as manual until your plan includes this mode.</div>@endif
        </td>
        <td><input name="amount_limit_base" form="rule-{{ $c->id }}" type="number" step="0.001" min="0" class="inp" style="width:120px" value="{{ $rule?->amount_limit_base }}" @disabled(!$canEdit)></td>
        <td>
          <select name="second_approver_user_id" form="rule-{{ $c->id }}" class="sel" @disabled(!$canEdit)>
            <option value="">Any Finance user</option>
            @foreach ($finance as $u)<option value="{{ $u->id }}" @selected($rule?->second_approver_user_id == $u->id)>{{ $u->name }}</option>@endforeach
          </select>
        </td>
        <td style="text-align:right">@if ($canEdit)<button form="rule-{{ $c->id }}" class="btn btn-ghost">Save</button>@endif</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endsection
