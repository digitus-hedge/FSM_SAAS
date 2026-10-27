@extends('layouts.admin')
@section('title', 'Expenses to review')

@section('content')
<style>
  .page-h1{font-family:'Figtree';font-size:28px;font-weight:800;margin-bottom:6px}
  .page-sub{color:var(--muted);font-size:14px;margin-bottom:22px;max-width:720px}

  .tab-strip{display:flex;gap:0;border-bottom:1px solid var(--line);margin-bottom:22px}
  .tab-strip a{padding:12px 20px;color:var(--muted);text-decoration:none;font-weight:600;border-bottom:2px solid transparent;position:relative;top:1px}
  .tab-strip a.active{color:var(--ink);border-bottom-color:var(--ink)}
  .tab-strip a .n{color:var(--muted);font-weight:400;margin-left:4px}
  .tab-strip a.active .n{color:var(--ink)}

  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  .table-wrap{overflow-x:auto}

  table.exp{width:100%;border-collapse:collapse;font-size:13.5px;min-width:1100px}
  table.exp th{text-align:left;padding:14px 16px;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line);white-space:nowrap}
  table.exp td{padding:16px;border-bottom:1px solid #F2F1F6;vertical-align:top;color:var(--ink)}
  table.exp tr:last-child td{border-bottom:0}
  table.exp td.sr{font-family:'Figtree';font-weight:800;white-space:nowrap}
  table.exp td.total{font-family:'Figtree';font-weight:700;white-space:nowrap}
  table.exp td.qty{white-space:nowrap;color:var(--muted)}

  .chip{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700}
  .chip-vendor{background:#EDEAF9;color:#5B3BB8;margin-top:4px}
  .chip-waiting{background:#FFF4CC;color:#8A5A00}
  .chip-approved{background:#DDF7F1;color:#00866F}
  .chip-rejected{background:#FDECE7;color:#C2381B}

  .reject-note{color:#C2381B;font-size:11.5px;margin-top:4px;word-break:break-word}
  .approve-note{color:var(--muted);font-size:11.5px;margin-top:4px;word-break:break-word}

  .receipt-link{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 12px;
    border:1px solid var(--line);border-radius:999px;
    color:var(--ink);text-decoration:none;
    font-size:12.5px;font-family:inherit;font-weight:600;
    background:#fff;cursor:pointer;
    max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  }
  .receipt-link:hover{background:var(--soft)}
  .receipt-none{color:var(--subtle)}

  .bill-check{width:16px;height:16px;accent-color:var(--ink)}

  .act{display:flex;gap:6px;justify-content:flex-end;flex-wrap:nowrap}
  .act form{display:inline;margin:0}
  .act button{padding:6px 14px;border-radius:8px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap}
  .act button:hover{background:var(--soft)}
  .act .btn-approve:hover{background:#DDF7F1;color:var(--green);border-color:var(--green)}
  .act .btn-reject:hover{background:#FDECE7;color:var(--red);border-color:var(--red)}

  .empty{padding:60px 20px;text-align:center;color:var(--muted)}
  .empty .big{font-family:'Figtree';font-weight:700;font-size:16px;color:var(--ink);margin-bottom:4px}
</style>

<h1 class="page-h1">Expenses to review</h1>
<p class="page-sub">Approve or reject what technicians logged. Only approved billable expenses of a billable request can go on an invoice.</p>

<div class="tab-strip">
  <a href="{{ route('admin.expenses-review.index', ['tab' => 'waiting']) }}"  class="{{ $tab==='waiting'  ? 'active' : '' }}">Waiting  <span class="n">{{ $counts['waiting'] }}</span></a>
  <a href="{{ route('admin.expenses-review.index', ['tab' => 'approved']) }}" class="{{ $tab==='approved' ? 'active' : '' }}">Approved <span class="n">{{ $counts['approved'] }}</span></a>
  <a href="{{ route('admin.expenses-review.index', ['tab' => 'rejected']) }}" class="{{ $tab==='rejected' ? 'active' : '' }}">Rejected <span class="n">{{ $counts['rejected'] }}</span></a>
  <a href="{{ route('admin.expenses-review.index', ['tab' => 'all']) }}"      class="{{ $tab==='all'      ? 'active' : '' }}">All      <span class="n">{{ $counts['all'] }}</span></a>
</div>

<div class="card">
  @if ($items->isEmpty())
    <div class="empty">
      <div class="big">Nothing here.</div>
      <div>No expenses in this tab.</div>
    </div>
  @else
    <div class="table-wrap">
      <table class="exp">
        <thead>
          <tr>
            <th>Request</th>
            <th>Technician</th>
            <th>Category</th>
            <th>Description</th>
            <th>Quantity x rate</th>
            <th>Total</th>
            <th>Receipt</th>
            <th>Billable</th>
            <th>Status</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($items as $exp)
            <tr>
              <td class="sr">{{ $exp->serviceRequest?->sr_number ?? ('SR-'.$exp->service_request_id) }}</td>
              <td>{{ $exp->loggedBy?->name ?? '—' }}</td>
              <td>
                {{ $exp->category?->name ?? '—' }}
                @if (\Schema::hasColumn('expense_categories', 'is_vendor') && ($exp->category?->is_vendor ?? false))
                  <div><span class="chip chip-vendor">Vendor</span></div>
                @endif
              </td>
              <td>{{ $exp->description ?: '—' }}</td>
              <td class="qty">{{ rtrim(rtrim(number_format($exp->quantity, 2), '0'), '.') }} x {{ number_format($exp->unit_rate, 2) }}</td>
              <td class="total">AED {{ number_format($exp->total_amount, 2) }}</td>

              {{-- RECEIPT --}}
              <td>
                @if ($exp->receipt && $exp->receipt->storage_path)
                  <button type="button" class="receipt-link"
                          onclick="showReceipt(
                            {{ json_encode($exp->receipt->original_name ?: 'receipt') }},
                            {{ json_encode(asset('storage/'.$exp->receipt->storage_path)) }},
                            {{ json_encode(($exp->description ? 'Photo of the receipt for '.$exp->description : 'Receipt photo')) }}
                          )">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    {{ $exp->receipt->original_name ?: 'receipt' }}
                  </button>
                @else
                  <span class="receipt-none">None</span>
                @endif
              </td>

              {{-- BILLABLE --}}
              <td>
                <input type="checkbox" class="bill-check" {{ $exp->is_billable_to_client ? 'checked' : '' }} disabled>
              </td>

              {{-- STATUS --}}
              <td>
                @if ($exp->approval_status === 'approved')
                  <span class="chip chip-approved">Approved</span>
                  @if ($exp->approve_note ?? false)
                    <div class="approve-note">{{ $exp->approve_note }}</div>
                  @endif
                @elseif ($exp->approval_status === 'rejected')
                  <span class="chip chip-rejected">Rejected</span>
                  @if ($exp->rejected_reason)
                    <div class="reject-note">{{ $exp->rejected_reason }}</div>
                  @endif
                @else
                  <span class="chip chip-waiting">Waiting</span>
                @endif
              </td>

              {{-- ACTIONS --}}
              <td style="text-align:right">
                <div class="act">

                  @if ($exp->approval_status !== 'approved')
                    <button type="button" class="btn-approve"
                            onclick="approveExpense({{ $exp->id }})">Approve</button>
                    <form id="approve-form-{{ $exp->id }}" method="POST"
                          action="{{ route('admin.expenses-review.approve', $exp) }}"
                          style="display:none">
                      @csrf
                      <input type="hidden" name="note" id="approve-note-{{ $exp->id }}">
                    </form>
                  @endif

                  @if ($exp->approval_status !== 'rejected')
                    <button type="button" class="btn-reject"
                            onclick="rejectExpense({{ $exp->id }})">Reject</button>
                    <form id="reject-form-{{ $exp->id }}" method="POST"
                          action="{{ route('admin.expenses-review.reject', $exp) }}"
                          style="display:none">
                      @csrf
                      <input type="hidden" name="reason" id="reason-{{ $exp->id }}">
                    </form>
                  @endif

                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<script>
/* ===== APPROVE MODAL — optional note ===== */
function approveExpense(id) {
  Swal.fire({
    icon: 'question',
    title: 'Approve this expense?',
    input: 'textarea',
    inputLabel: 'Reason (optional)',
    inputPlaceholder: 'Any note for the record — e.g., "Verified against vendor invoice."',
    inputAttributes: { 'aria-label': 'Approval note' },
    showCancelButton: true,
    confirmButtonText: 'Approve',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-green' }
    // No validator — note is optional
  }).then((r) => {
    if (r.isConfirmed) {
      document.getElementById('approve-note-' + id).value = r.value || '';
      document.getElementById('approve-form-' + id).submit();
    }
  });
}

/* ===== REJECT MODAL — required reason ===== */
function rejectExpense(id) {
  Swal.fire({
    icon: 'warning',
    title: 'Reject this expense?',
    input: 'textarea',
    inputLabel: 'Reason',
    inputPlaceholder: 'Why is this being rejected?',
    inputAttributes: { 'aria-label': 'Rejection reason' },
    showCancelButton: true,
    confirmButtonText: 'Reject',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-red' },
    inputValidator: (v) => (!v || !v.trim()) ? 'Please give a reason.' : null,
  }).then((r) => {
    if (r.isConfirmed) {
      document.getElementById('reason-' + id).value = r.value;
      document.getElementById('reject-form-' + id).submit();
    }
  });
}

/* ===== RECEIPT PREVIEW MODAL ===== */
function showReceipt(filename, imageUrl, description) {
  Swal.fire({
    title: 'Receipt',
    html: `
      <div style="text-align:left;background:var(--soft);border-radius:12px;padding:14px;margin-top:6px">
        <div style="font-family:'Figtree';font-weight:800;font-size:14px;color:var(--ink);text-align:center;margin-bottom:2px">
          ${filename}
        </div>
        <div style="color:var(--muted);font-size:12px;text-align:center;margin-bottom:10px">
          ${description}
        </div>
        <div style="text-align:center">
          <img src="${imageUrl}"
               alt="${filename}"
               style="max-width:100%;max-height:220px;border-radius:8px;border:1px solid var(--line);background:#fff;object-fit:contain"
               onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
          <div style="display:none;color:var(--red);padding:20px;background:#fff;border-radius:8px;font-size:13px">
            Could not load the receipt image.
          </div>
        </div>
      </div>
    `,
    showCloseButton: true,
    showConfirmButton: true,
    confirmButtonText: 'Close',
    width: 460,
    padding: '18px',
    customClass: {
      confirmButton: 'swal2-cancel'
    }
  });
}
</script>

@endsection