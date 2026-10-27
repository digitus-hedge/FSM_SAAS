@extends('layouts.admin')
@section('title','Requests')

@section('content')
<style>
    .page-h1 {
        font-family: 'Figtree';
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 6px
    }

    .page-sub {
        color: var(--muted);
        font-size: 14px;
        margin-bottom: 16px
    }

    .banner {
        background: #DDF7F1;
        border: 1px solid #B9EADD;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 22px;
        font-size: 13.5px;
        display: flex;
        gap: 10px;
        align-items: center
    }

    .tab-strip {
        display: flex;
        border-bottom: 1px solid var(--line);
        margin-bottom: 22px
    }

    .tab-strip a {
        padding: 12px 20px;
        color: var(--muted);
        text-decoration: none;
        font-weight: 600;
        border-bottom: 2px solid transparent;
        position: relative;
        top: 1px
    }

    .tab-strip a.active {
        color: var(--ink);
        border-bottom-color: var(--ink)
    }

    .card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        overflow: hidden
    }

    table.rr {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px
    }

    table.rr th {
        text-align: left;
        padding: 14px 16px;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .06em;
        border-bottom: 1px solid var(--line)
    }

    table.rr td {
        padding: 18px 16px;
        border-bottom: 1px solid #F2F1F6;
        vertical-align: top
    }

    table.rr tr:last-child td {
        border-bottom: 0
    }

    .rr-num {
        font-family: 'Figtree';
        font-weight: 800;
        font-size: 14.5px
    }

    .rr-title {
        font-family: 'Figtree';
        font-weight: 700;
        font-size: 14.5px
    }

    .rr-site {
        color: var(--muted);
        font-size: 12.5px;
        margin-top: 3px
    }

    .pill {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 700
    }

    .pill-pending {
        background: #E8E6EF;
        color: #5A5869
    }

    .pill-progress {
        background: #EDE9FE;
        color: #5B3BB8
    }

    .pill-review {
        background: #FFF4CC;
        color: #8A5A00
    }

    .pill-completed {
        background: #DDF7F1;
        color: #00866F
    }

    .pill-awaiting {
        background: #FFF0D1;
        color: #8A5A00
    }

    .pill-quoted {
        background: #EDE9FE;
        color: #5B3BB8
    }

    .pill-na {
        background: #F0EFF5;
        color: #777
    }

    .btn-primary {
        background: #2F5BEA;
        color: #fff;
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        display: inline-block;
        font-size: 13.5px
    }

    .btn-primary:hover {
        background: #2548c0
    }
</style>

<h1 class="page-h1">Requests</h1>
<p class="page-sub">A stand-in for the request list. It is here so that billing has requests to work on.</p>
<div class="banner">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
        <line x1="12" y1="9" x2="12" y2="13" />
        <line x1="12" y1="17" x2="12.01" y2="17" />
    </svg>
    The request pages belong to the Service request module. Only the billing parts are real here.
</div>

<div class="tab-strip">
    <a href="{{ route('admin.requests-review.index',['tab'=>'waiting']) }}" class="{{ $tab==='waiting'?'active':'' }}">Waiting for quote <span style="color:var(--muted);font-weight:400">{{ $counts['waiting'] }}</span></a>
    <a href="{{ route('admin.requests-review.index',['tab'=>'invoice']) }}" class="{{ $tab==='invoice'?'active':'' }}">Awaiting invoice <span style="color:var(--muted);font-weight:400">{{ $counts['invoice'] }}</span></a>
    <a href="{{ route('admin.requests-review.index',['tab'=>'all']) }}" class="{{ $tab==='all'?'active':'' }}">All <span style="color:var(--muted);font-weight:400">{{ $counts['all'] }}</span></a>
</div>

<div class="card">
    @if ($items->isEmpty())
    <div style="padding:60px 20px;text-align:center;color:var(--muted)">Nothing here.</div>
    @else
    <table class="rr">
        <thead>
            <tr>
                <th>Number</th>
                <th>Title</th>
                <th>Status</th>
                <th>Coverage</th>
                <th>Billing</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $sr)
            @php
            $statusMap = [
            'pending'=>['pill-pending','Pending'],
            'in_progress'=>['pill-progress','In Progress'],
            'pending_review'=>['pill-review','Pending Review'],
            'completed'=>['pill-completed','Completed'],
            'quoted'=>['pill-quoted','Quoted'],
            'awaiting_invoice'=>['pill-awaiting','Awaiting Invoice'],
            ];
            $st = $statusMap[$sr->status] ?? ['pill-pending', ucfirst($sr->status)];
            @endphp
            <tr>
                <td class="rr-num">{{ $sr->sr_number }}</td>
                <td>
                    <div class="rr-title">{{ $sr->title }}</div>
                    <div class="rr-site">{{ $sr->client?->name }}, {{ $sr->site?->name }}</div>
                </td>
                <td><span class="pill {{ $st[0] }}">{{ $st[1] }}</span></td>
                <td style="color:var(--muted)">Out of coverage</td>
                <td>
                    @if ($sr->billing_status === 'awaiting_invoice')
                    <span class="pill pill-awaiting">Pending invoice</span>
                    @elseif ($sr->billing_status === 'quoted')
                    <span class="pill pill-quoted">Quoted</span>
                    @else
                    <span class="pill pill-na">Not applicable</span>
                    @endif
                </td>
              <td style="text-align:right">
    @php
    $draft = $sr->quotes()->where('status','draft')->latest('id')->first();
    $latestQuote = $sr->quotes()->latest('id')->first();
    @endphp

    {{-- WAITING FOR QUOTE tab: show Make quote / Open draft only --}}
   {{-- WAITING FOR QUOTE tab: Make quote / Open draft --}}
@if ($tab === 'waiting')
    @if ($draft)
        <a href="{{ $draft->source === 'builder' ? route('admin.quotes.edit', $draft) : route('admin.quotes.show', $draft) }}" class="btn-primary">Open quote</a>
    @elseif (in_array($sr->billing_status, ['not_applicable','pending_quote']))
        <button type="button" class="btn-primary" onclick="openMakeQuote({{ $sr->id }})">Make quote</button>
    @endif

    {{-- AWAITING INVOICE tab: Make invoice link only --}}
    @elseif ($tab === 'invoice')
        {{-- no button for now — invoice module not yet built --}}

    {{-- ALL tab: show "View quote" if there's a sent quote, nothing else --}}
    @elseif ($tab === 'all')
        @if ($latestQuote && in_array($latestQuote->status, ['sent','accepted','rejected']))
            <a href="{{ route('admin.quotes.show', $latestQuote) }}" class="btn-primary" style="background:#fff;color:#2F5BEA;border:1px solid #2F5BEA">View quote</a>
        @elseif ($draft)
            <a href="{{ $draft->source === 'builder' ? route('admin.quotes.edit', $draft) : route('admin.quotes.show', $draft) }}" class="btn-primary" style="background:#fff;color:#2F5BEA;border:1px solid #2F5BEA">Open draft</a>
        @endif
    @endif
</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

<script>
    function openMakeQuote(srId) {
        Swal.fire({
            title: 'Make a quote',
            html: `
      <div style="text-align:left">
        <p style="color:var(--muted);margin-bottom:16px;font-size:13.5px">How do you want to make it?</p>
        <label style="display:flex;gap:12px;padding:14px 2px;cursor:pointer">
          <input type="radio" name="mq" value="build" checked style="margin-top:3px;accent-color:#2F5BEA">
          <div>
            <div style="font-weight:800;font-size:14.5px">Build the quote</div>
            <div style="color:var(--muted);font-size:12.5px;margin-top:2px">Add lines, tax and a discount. A branded PDF is made when you send it.</div>
          </div>
        </label>
        <label style="display:flex;gap:12px;padding:14px 2px;cursor:pointer">
          <input type="radio" name="mq" value="upload" style="margin-top:3px;accent-color:#2F5BEA">
          <div>
            <div style="font-weight:800;font-size:14.5px">Upload a PDF</div>
            <div style="color:var(--muted);font-size:12.5px;margin-top:2px">Use a quote you already made. You enter the total.</div>
          </div>
        </label>
      </div>
    `,
            showCancelButton: true,
            confirmButtonText: 'Continue',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            customClass: {
                confirmButton: 'swal-btn-primary-blue'
            },
            preConfirm: () => document.querySelector('input[name="mq"]:checked').value
        }).then(r => {
            if (r.isConfirmed) {
                const url = r.value === 'build' ?
                    @json(url('/admin/quotes/build')) + '/' + srId :
                    @json(url('/admin/quotes/upload')) + '/' + srId;
                window.location = url;
            }
        });
    }
</script>

<style>
    .swal-btn-primary-blue {
        background: #2F5BEA !important;
        color: #fff !important
    }
</style>
@endsection