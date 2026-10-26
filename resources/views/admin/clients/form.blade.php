@extends('layouts.admin')

@php
    $client = $client ?? new \App\Models\Client();
    $mode   = $mode   ?? ($client->exists ? 'edit' : 'create');
@endphp

@section('title', $mode === 'create' ? 'New Client' : $client->name)
@section('hide_layout_errors', true)

@section('content')
<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline;text-underline-offset:3px}
  .crumb a:hover{color:#2F5BEA}

  .page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px}
  .page-head h1{font-family:'Figtree',sans-serif;font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .meta-row{display:flex;gap:8px;align-items:center;margin-top:12px;margin-bottom:24px}
  .code-pill{display:inline-block;font-family:'DM Sans';font-size:12.5px;font-weight:600;color:#2F5BEA;background:#E6ECFE;padding:5px 11px;border-radius:7px}
  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px}
  .status-ok{background:#E6F7EC;color:#1D7A3A}
  .status-off{background:#F2F1F6;color:#5F5D6E}

  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:'DM Sans';font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s}
  .btn-primary{background:#2F5BEA;color:#fff}
  .btn-primary:hover{background:#2749C8}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .btn-ghost:hover{border-color:#0F0F17}
  .btn-danger-out{background:#fff;color:#C7372F;border-color:#F0C7C7}
  .btn-danger-out:hover{background:#FFF5F5;border-color:#C7372F}

  /* Tabs */
  .tabs{display:flex;gap:32px;border-bottom:1px solid #EEEDF3;margin-bottom:24px}
  .tab-btn{background:none;border:0;padding:14px 0 16px;font-family:'Figtree';font-size:16px;font-weight:600;color:#5F5D6E;cursor:pointer;position:relative;transition:color .12s}
  .tab-btn:hover{color:#0F0F17}
  .tab-btn.active{color:#0F0F17}
  .tab-btn.active::after{content:'';position:absolute;left:0;right:0;bottom:-1px;height:2.5px;background:#2F5BEA;border-radius:2px}
  .tab-count{font-size:12.5px;color:#9A98A7;font-weight:400;margin-left:4px}

  .tabpane{display:none}
  .tabpane.active{display:block}

  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:24px;margin-bottom:16px}

  .fld{margin-bottom:20px;display:flex;flex-direction:column}
  .fld:last-child{margin-bottom:0}
  .fld label{font-weight:600;font-size:14px;margin-bottom:8px;color:#0F0F17}
  .req{color:#C7372F}
  .inp,.sel,.txt{width:100%;font-family:'DM Sans';font-size:14px;padding:12px 14px;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;outline:none;transition:border .15s, box-shadow .15s;box-sizing:border-box}
  .inp:focus,.sel:focus,.txt:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .inp.err,.txt.err{border-color:#C7372F;background:#FFF7F7}
  .txt{min-height:88px;resize:vertical;font-family:inherit}
  .hint{font-size:12.5px;color:#5F5D6E;margin-top:6px}
  .errtxt{font-size:12.5px;color:#C7372F;margin-top:6px;font-weight:600}

  .two{display:grid;grid-template-columns:1fr 1fr;gap:20px}
  @media (max-width:720px){.two{grid-template-columns:1fr}}

  .switch-row{display:flex;align-items:flex-start;gap:12px;padding:14px;background:#FAFAFC;border:1px solid #EEEDF3;border-radius:10px;cursor:pointer;margin-bottom:10px}
  .switch-row input[type=checkbox]{appearance:none;width:18px;height:18px;border:1.5px solid #D1CFDA;border-radius:5px;cursor:pointer;position:relative;flex-shrink:0;margin-top:1px;background:#fff}
  .switch-row input:checked{background:#2F5BEA;border-color:#2F5BEA}
  .switch-row input:checked::after{content:'';position:absolute;left:5px;top:1px;width:5px;height:10px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
  .switch-row .st{font-size:13.5px;font-weight:600;color:#0F0F17}
  .switch-row .sd{font-size:12.5px;color:#5F5D6E;margin-top:2px}

  .formact{display:flex;gap:10px;justify-content:flex-end;padding-top:20px;margin-top:8px;border-top:1px solid #EEEDF3}

  /* List rows (contacts/sites/users) */
  .section-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;gap:16px;flex-wrap:wrap}
  .section-head h3{font-family:'Figtree';font-size:17px;font-weight:700;margin:0;color:#0F0F17}
  .section-head p{font-size:12.5px;color:#5F5D6E;margin:4px 0 0}

  .list-row{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid #EEEDF3;border-radius:10px;margin-bottom:8px;background:#fff}
  .list-row.primary{border-color:#2F5BEA;background:#F5F7FF}
  .list-ini{width:36px;height:36px;border-radius:9px;background:#E6ECFE;color:#2F5BEA;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .list-main{flex:1;min-width:0}
  .list-ttl{font-weight:700;font-size:14px;color:#0F0F17;display:flex;align-items:center;gap:8px}
  .list-sub{font-size:12.5px;color:#5F5D6E;margin-top:2px}
  .list-badge{font-size:9px;font-weight:800;background:#2F5BEA;color:#fff;padding:2px 7px;border-radius:4px;letter-spacing:.04em}
  .list-acts{display:flex;gap:14px}
  .list-acts button,.list-acts a{background:0;border:0;padding:0;cursor:pointer;font-size:12.5px;font-weight:700;font-family:inherit;text-decoration:none}
  .act-edit{color:#0F0F17}
  .act-edit:hover{color:#2F5BEA}
  .act-del{color:#C7372F}

  .empty-list{padding:48px 20px;text-align:center;color:#5F5D6E;font-size:13.5px;border:1px dashed #D1CFDA;border-radius:10px}
  .empty-list strong{color:#0F0F17}

  .swal-form{display:flex;flex-direction:column;gap:12px;text-align:left}
  .swal-form label{font-size:12.5px;font-weight:600;color:#0F0F17;margin-bottom:4px;display:block}
  .swal-form input[type="text"],.swal-form input[type="email"]{font-family:'DM Sans';font-size:14px;padding:10px 13px;border-radius:9px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;width:100%;outline:none;box-sizing:border-box}
  .swal-form input:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .swal-form .check-row{display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;padding:6px 0}
</style>



<style>
  /* Add these to the existing <style> block */
  .btn-mini{padding:6px 12px;font-size:12.5px;font-weight:700;border-radius:8px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;cursor:pointer;font-family:'DM Sans';white-space:nowrap}
  .btn-mini:hover{border-color:#0F0F17}
  .btn-mini-danger{padding:6px 12px;font-size:12.5px;font-weight:700;border-radius:8px;border:1px solid #F0C7C7;background:#fff;color:#C7372F;cursor:pointer;font-family:'DM Sans';white-space:nowrap}
  .btn-mini-danger:hover{background:#FFF5F5}
  .chip-sm{display:inline-flex;align-items:center;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;margin-top:4px}
  .chip-ok-sm{background:#E6F7EC;color:#1D7A3A}
  .chip-warn{background:#FEF3C7;color:#92400E}

  .tab-table{width:100%;border-collapse:collapse;min-width:800px;font-size:14px}
  .tab-table thead th{text-align:left;padding:14px 20px;font-weight:600;font-size:13px;color:#5F5D6E;border-bottom:1px solid #EEEDF3;background:#FAFAFC}
  .tab-table tbody td{padding:16px 20px;border-bottom:1px solid #F2F1F6;color:#0F0F17;vertical-align:middle}
  .tab-table tbody tr:last-child td{border-bottom:0}
  .tab-table tbody tr.clickable{cursor:pointer}
  .tab-table tbody tr.clickable:hover{background:#FAFAFC}
  .table-head-bar{display:flex;justify-content:flex-end;padding:16px 20px;border-bottom:1px solid #EEEDF3;background:#fff}
</style>

<div class="crumb">
  <a href="{{ route('admin.clients.index') }}">Clients</a> / {{ $mode === 'create' ? 'New client' : $client->name }}
</div>

<div class="page-head">
  <div style="flex:1">
    <h1>{{ $mode === 'create' ? 'New client' : $client->name }}</h1>
    @if ($mode === 'edit')
      <div class="meta-row">
        <span class="code-pill">{{ $client->client_code }}</span>
        @if ($client->is_active)
          <span class="status status-ok">Active</span>
        @else
          <span class="status status-off">Suspended</span>
        @endif
      </div>
    @endif
  </div>
  @if ($mode === 'edit')
    @if ($client->is_active)
      <form method="POST" action="{{ route('admin.clients.suspend', $client) }}"
            class="needs-confirm" style="margin:0"
            data-confirm-title="Deactivate {{ $client->name }}?"
            data-confirm-text="They will stop appearing in new service requests."
            data-confirm-ok="Yes, deactivate" data-confirm-ok-color="red">
        @csrf
        <button class="btn btn-danger-out" type="submit">Deactivate</button>
      </form>
    @else
      <form method="POST" action="{{ route('admin.clients.reactivate', $client) }}"
            class="needs-confirm" style="margin:0"
            data-confirm-title="Reactivate {{ $client->name }}?"
            data-confirm-ok="Yes, reactivate" data-confirm-ok-color="green">
        @csrf
        <button class="btn btn-ghost" type="submit">Reactivate</button>
      </form>
    @endif
  @endif
</div>

@if ($mode === 'edit')
  <div class="tabs">
    <button type="button" class="tab-btn active" data-tab="details">Details</button>
    <button type="button" class="tab-btn" data-tab="contacts">
      Contacts <span class="tab-count">({{ $client->contacts->count() }})</span>
    </button>
    <button type="button" class="tab-btn" data-tab="sites">
      Sites <span class="tab-count">({{ $client->sites_count ?? $client->sites->count() }})</span>
    </button>
    <button type="button" class="tab-btn" data-tab="users">Client users</button>
  </div>
@endif

{{-- ========== DETAILS TAB ========== --}}
<div class="tabpane active" id="pane-details">
  <form method="POST"
        action="{{ $mode === 'create' ? route('admin.clients.store') : route('admin.clients.update', $client) }}"
        novalidate>
    @csrf
    @if ($mode === 'edit') @method('PUT') @endif

    <div class="card">
      <div class="fld">
        <label for="client_code">Code</label>
        <input id="client_code" name="client_code" value="{{ old('client_code', $client->client_code) }}"
               maxlength="40" pattern="[A-Za-z0-9_\-]+" class="inp @error('client_code') err @enderror"
               style="text-transform:uppercase;letter-spacing:.04em">
        @error('client_code')<div class="errtxt">{{ $message }}</div>@else
          <div class="hint">Leave empty to use the next code. 1 to 40 letters, digits or hyphens.</div>
        @enderror
      </div>

      <div class="fld">
        <label for="name">Name <span class="req">*</span></label>
        <input id="name" name="name" value="{{ old('name', $client->name) }}" maxlength="180" required
               class="inp @error('name') err @enderror">
        @error('name')<div class="errtxt">{{ $message }}</div>@enderror
      </div>

      <div class="fld">
        <label for="contact_person">Contact person</label>
        <input id="contact_person" name="contact_person" value="{{ old('contact_person', $client->contact_person) }}"
               maxlength="150" class="inp @error('contact_person') err @enderror">
      </div>

      <div class="two">
        <div class="fld">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="{{ old('email', $client->email) }}"
                 maxlength="190" class="inp @error('email') err @enderror">
          @error('email')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="phone">Phone</label>
          <input id="phone" name="phone" value="{{ old('phone', $client->phone) }}"
                 maxlength="40" class="inp @error('phone') err @enderror">
        </div>
      </div>

      <div class="two">
        <div class="fld">
          <label for="tax_id">Tax ID (GSTIN / VAT)</label>
          <input id="tax_id" name="tax_id" value="{{ old('tax_id', $client->tax_id) }}"
                 maxlength="60" class="inp @error('tax_id') err @enderror">
        </div>
        <div class="fld">
          <label for="payment_terms_days">Payment terms (days) <span class="req">*</span></label>
          <input type="number" id="payment_terms_days" name="payment_terms_days" min="0" max="365" required
                 value="{{ old('payment_terms_days', $client->payment_terms_days ?? 30) }}"
                 class="inp @error('payment_terms_days') err @enderror">
        </div>
      </div>

      <div class="fld">
        <label for="billing_address">Billing address</label>
        <textarea id="billing_address" name="billing_address" rows="2" maxlength="1000"
                  class="txt @error('billing_address') err @enderror">{{ old('billing_address', $client->billing_address) }}</textarea>
      </div>

      <div class="fld">
        <label for="notes">Internal notes</label>
        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                  class="txt @error('notes') err @enderror">{{ old('notes', $client->notes) }}</textarea>
        <div class="hint">Visible only to your team.</div>
      </div>

      <!-- <label class="switch-row" for="asset_visibility_enabled">
        <input type="hidden" name="asset_visibility_enabled" value="0">
        <input type="checkbox" name="asset_visibility_enabled" id="asset_visibility_enabled" value="1"
               @checked(old('asset_visibility_enabled', $client->asset_visibility_enabled))>
        <div>
          <div class="st">Allow this client to view their assets in the portal</div>
          <div class="sd">When enabled, client-portal users see the equipment list for this client.</div>
        </div>
      </label> -->

      <!-- <label class="switch-row" for="is_active">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               @checked(old('is_active', $client->exists ? $client->is_active : true))>
        <div>
          <div class="st">Active</div>
          <div class="sd">Inactive clients are hidden from the service-request dropdown.</div>
        </div>
      </label> -->

      <div class="formact">
        <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">
          {{ $mode === 'create' ? 'Create client' : 'Save changes' }}
        </button>
      </div>
    </div>
  </form>
</div>

@if ($mode === 'edit')
{{-- ========== CONTACTS TAB ========== --}}




{{-- ========== CONTACTS TAB ========== --}}
<div class="tabpane" id="pane-contacts">

  @php
    // Warning: at least one active contact must have email OR WhatsApp
    $reachableCount = $client->contacts
      ->where('is_active', '!=', false)
      ->filter(fn ($c) => !empty($c->email) || !empty($c->whatsapp_number))
      ->count();
  @endphp

  @if ($client->contacts->count() && $reachableCount === 0)
    <div style="display:flex;align-items:flex-start;gap:10px;padding:14px 18px;background:#FFF7E6;border:1px solid #F5D6A3;border-radius:12px;margin-bottom:16px;font-size:13.5px;color:#8A5A00">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      <div><strong>No contact can receive messages for this client.</strong> Add a contact with an email or a WhatsApp number.</div>
    </div>
  @endif

  <div class="card" style="padding:0">
    <div class="table-head-bar">
      <button type="button" class="btn btn-primary" onclick="openContactModal()">+ Add contact</button>
    </div>
    <div class="tblwrap">
      <table class="tab-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>WhatsApp</th>
            <th>Sites</th>
            <th>Primary</th>
            <th>Status</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($client->contacts as $contact)
            @php
            $isActive  = $contact->is_active ?? true;
    $hasOptOut = $contact->whatsapp_opt_out_at ?? false;
    $hasConsent= !$hasOptOut && ($contact->whatsapp_opt_in_at ?? false);
            @endphp
            <tr style="{{ $isActive ? '' : 'opacity:.6' }}">
              <td style="font-weight:700">{{ $contact->name }}</td>
              <td style="color:#5F5D6E">{{ $contact->email ?? '' }}</td>
              <td style="color:#5F5D6E">{{ $contact->phone ?? '' }}</td>
              <td style="color:#5F5D6E">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                  <span>{{ $contact->whatsapp_number ?? '' }}</span>
                  @if ($hasOptOut)
  <span class="chip-sm" style="background:#FDE8E8;color:#B33636">
    Opted out on {{ \Carbon\Carbon::parse($contact->whatsapp_opt_out_at)->format('j M Y') }}
  </span>
@elseif ($hasConsent)
  <span class="chip-sm chip-ok-sm">
    Agreed on {{ \Carbon\Carbon::parse($contact->whatsapp_opt_in_at)->format('j M Y') }}
  </span>
@endif
                </div>
              </td>
              <td style="color:#5F5D6E">
                {{ ($contact->site_access_mode ?? 'all') === 'all'
                   ? 'All sites'
                   : (($contact->sites_count ?? ($contact->sites?->count() ?? 0)).' sites') }}
              </td>
             {{-- Primary column — just the pill, no button --}}
<td>
  @if ($contact->is_primary)
    <span class="code-pill">Primary</span>
  @endif
</td>

{{-- Status column (unchanged) --}}
<td>
  @if ($isActive)
    <span class="status status-ok">Active</span>
  @else
    <span class="status status-off">Inactive</span>
  @endif
</td>

{{-- Actions — Edit | Make primary (if not primary) | Deactivate/Reactivate --}}
<td>
  <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:nowrap">
    <button type="button" class="btn-mini" onclick='openContactModal(@json($contact))'>Edit</button>

    @if (!$contact->is_primary && $isActive)
      <button type="button" class="btn-mini"
        onclick='makePrimary({{ $contact->id }}, @json($contact->name))'>Make primary</button>
    @endif

    @if ($isActive)
      <button type="button" class="btn-mini-danger"
              onclick='setContactActive({{ $contact->id }}, false, @json($contact->name))'>Deactivate</button>
    @else
      <button type="button" class="btn-mini"
              onclick='setContactActive({{ $contact->id }}, true, @json($contact->name))'>Reactivate</button>
    @endif
  </div>
</td>
            </tr>
          @empty
            <tr><td colspan="8" class="empty"><h4>No contacts yet</h4><p>Click + Add contact above.</p></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div style="color:#5F5D6E;font-size:12.5px;margin-top:16px">Design prototype. Names and numbers are examples.</div>
</div>


{{-- ========== SITES TAB ========== --}}
<div class="tabpane" id="pane-sites">
  <div class="card" style="padding:0">
    <div class="table-head-bar">
      <a href="{{ route('admin.sites.create', ['client_id' => $client->id]) }}" class="btn btn-primary">+ Add site</a>
    </div>
    <div class="tblwrap">
      <table class="tab-table">
        <thead>
          <tr><th>Code</th><th>Name</th><th>Location</th><th>Status</th></tr>
        </thead>
        <tbody>
          @forelse ($client->sites as $site)
          <tr class="clickable" onclick="window.location='{{ route('admin.sites.edit', $site) }}'">
            <td><span class="code-pill">{{ $site->site_code ?? 'ST-'.str_pad($site->id, 4, '0', STR_PAD_LEFT) }}</span></td>
            <td style="font-weight:700">{{ $site->name }}</td>
            <td>
              @if ($site->gps_lat && $site->gps_lng)
                <span class="status status-ok">Saved</span>
              @else
                <span class="status chip-warn">Not set</span>
              @endif
            </td>
            <td>
              @if ($site->is_active ?? true)
                <span class="status status-ok">Active</span>
              @else
                <span class="status status-off">Inactive</span>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="4" class="empty"><h4>No sites yet</h4><p>Click + Add site above.</p></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ========== CLIENT USERS TAB ========== --}}
<div class="tabpane" id="pane-users">
  <div class="card">
    <div class="section-head">
      <div>
        <h3>Client users</h3>
        <p>People at {{ $client->name }} with portal access.</p>
      </div>
      <button type="button" class="btn btn-primary" disabled style="opacity:.5;cursor:not-allowed">+ Invite user</button>
    </div>
    <div class="empty-list">Client-portal users will appear here once the portal module is enabled.</div>
  </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(x => x.classList.remove('active'));
      document.querySelectorAll('.tabpane').forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      document.getElementById('pane-'+b.dataset.tab).classList.add('active');
      history.replaceState(null, '', '#'+b.dataset.tab);
    });
  });
  const h = location.hash.replace('#','');
  if (h) {
    const btn = document.querySelector(`.tab-btn[data-tab="${h}"]`);
    if (btn) btn.click();
  }

  @if ($errors->any())
    Swal.fire({icon:'error',title:'Please check your form',text:'Scroll down to see what to fix.'});
  @endif
  @if (session('success'))
    Swal.fire({toast:true,position:'top-end',icon:'success',title:@json(session('success')),showConfirmButton:false,timer:3000});
  @endif
});

@if ($mode === 'edit')
const CONTACT_URLS = {
  store: @json(route('admin.clients.contacts.store', $client)),
  base:  @json(url('/admin/clients/'.$client->id.'/contacts')),
};
const CSRF = @json(csrf_token());

function openContactModal(contact=null){
  const edit = !!contact;
  const esc  = s => (s??'').toString().replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

  // Build site options from the client's sites (passed from Blade below)
  const siteList = @json($client->sites->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->values());
  const selectedSites = contact?.sites?.map(s=>s.id) ?? [];
  const siteMode = contact?.site_access_mode ?? 'all';

  const sitesHtml = siteList.length === 0
    ? `<div style="font-size:12px;color:#5F5D6E;padding:8px;background:#FAFAFC;border-radius:8px">No sites added for this client yet.</div>`
    : `
      <label><strong>Sites</strong></label>
      <div style="display:flex;gap:8px;margin-bottom:8px">
        <label style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid #E5E4EC;border-radius:10px;cursor:pointer" id="opt-all">
          <input type="radio" name="site_mode" value="all" ${siteMode==='all'?'checked':''}>
          <span style="font-weight:600;font-size:13px">All sites</span>
        </label>
        <label style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid #E5E4EC;border-radius:10px;cursor:pointer" id="opt-sel">
          <input type="radio" name="site_mode" value="selected" ${siteMode==='selected'?'checked':''}>
          <span style="font-weight:600;font-size:13px">Selected sites</span>
        </label>
      </div>
      <div id="site-chips" style="display:${siteMode==='selected'?'flex':'none'};flex-wrap:wrap;gap:8px">
        ${siteList.map(s=>`
          <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #E5E4EC;border-radius:10px;cursor:pointer;font-size:13px">
            <input type="checkbox" value="${s.id}" ${selectedSites.includes(s.id)?'checked':''}>
            ${esc(s.name)}
          </label>`).join('')}
      </div>`;

  Swal.fire({
    title: edit?'Edit contact':'Add contact',
    width: 640,
    html: `<div class="swal-form">
      <div><label>Name *</label><input id="c-name" type="text"  maxlength="150" value="${esc(contact?.name)}"></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div><label>Email</label><input id="c-email" type="email" maxlength="190" value="${esc(contact?.email)}"></div>
        <div><label>Phone</label><input id="c-phone" type="text" maxlength="40" placeholder="+971501234567" value="${esc(contact?.phone)}"></div>
      </div>

      <div><label>WhatsApp number</label><input id="c-wa" type="text" maxlength="40" placeholder="+971501234567" value="${esc(contact?.whatsapp_number)}"></div>

      <label class="check-row"><input type="checkbox" id="c-wa-same"> <strong>Same as phone</strong></label>

      <label class="check-row" style="align-items:flex-start">
      <input type="checkbox" id="c-wa-consent" ${contact?.whatsapp_opt_in_at && !contact?.whatsapp_opt_out_at ?'checked':''} style="margin-top:3px">
        <div>
          <strong>This person agreed to receive WhatsApp messages</strong>
          <div style="font-size:12px;color:#5F5D6E;margin-top:2px">Records today's date and who ticked it.</div>
        </div>
      </label>

      ${sitesHtml}

      <label class="check-row" style="margin-top:4px"><input type="checkbox" id="c-prim" ${contact?.is_primary?'checked':''}> <strong>Primary contact</strong></label>
    </div>`,
    showCancelButton:true, confirmButtonText:'Save', cancelButtonText:'Cancel',
    reverseButtons:true, focusConfirm:false,
    didOpen: () => {
      // Same-as-phone sync
      document.getElementById('c-wa-same').addEventListener('change', e => {
        if (e.target.checked) document.getElementById('c-wa').value = document.getElementById('c-phone').value;
      });
      // Toggle site chips when "Selected sites" chosen
      document.querySelectorAll('input[name=site_mode]').forEach(r => {
        r.addEventListener('change', () => {
          document.getElementById('site-chips').style.display = r.value === 'selected' && r.checked ? 'flex' : (document.querySelector('input[name=site_mode]:checked')?.value === 'selected' ? 'flex' : 'none');
        });
      });
    },
    preConfirm:()=>{
      const n = document.getElementById('c-name').value.trim();
      if(!n){ Swal.showValidationMessage('Name required'); return false; }
      const siteMode = document.querySelector('input[name=site_mode]:checked')?.value || 'all';
      const siteIds = siteMode === 'selected'
        ? [...document.querySelectorAll('#site-chips input:checked')].map(i => parseInt(i.value))
        : [];
      return {
        name: n,
        email: document.getElementById('c-email').value.trim(),
        phone: document.getElementById('c-phone').value.trim(),
        whatsapp_number: document.getElementById('c-wa').value.trim(),
        whatsapp_consent: document.getElementById('c-wa-consent').checked ? 1 : 0,
        site_access_mode: siteMode,
        site_ids: siteIds,
        is_primary: document.getElementById('c-prim').checked ? 1 : 0,
      };
    }
  }).then(async r => {
    if (!r.isConfirmed) return;
    const url = edit ? `${CONTACT_URLS.base}/${contact.id}` : CONTACT_URLS.store;
    const res = await fetch(url, {
      method: edit ? 'PUT' : 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify(r.value),
    });
    if (!res.ok) { const e = await res.json().catch(()=>({})); return Swal.fire({icon:'error',title:'Could not save',text:e.message||'Error'}); }
    Swal.fire({toast:true,position:'top-end',icon:'success',title:'Saved',showConfirmButton:false,timer:2000});
    setTimeout(()=>location.reload(), 300);
  });
}

function setContactActive(id, makeActive, name) {
  const action = makeActive ? 'Reactivate' : 'Deactivate';
  const text   = makeActive
    ? `${name} will start receiving messages for this client again.`
    : `${name} will stop receiving messages for this client. You can reactivate anytime.`;

  Swal.fire({
    icon: makeActive ? 'question' : 'warning',
    title: `${action} ${name}?`,
    text, showCancelButton: true,
    confirmButtonText: `Yes, ${action.toLowerCase()}`,
    reverseButtons: true
  }).then(async r => {
    if (!r.isConfirmed) return;
    const res = await fetch(`${CONTACT_URLS.base}/${id}/active`, {
      method: 'PATCH',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify({ is_active: makeActive ? 1 : 0 }),
    });
    if (!res.ok) return Swal.fire({icon:'error',title:`Could not ${action.toLowerCase()}`});
    Swal.fire({toast:true,position:'top-end',icon:'success',title:`${action}d`,showConfirmButton:false,timer:1800});
    setTimeout(() => location.reload(), 300);
  });
}

function makePrimary(id, name) {
  Swal.fire({
    icon: 'question',
    title: `Make ${name} primary?`,
    text: 'They will become the primary contact for this client.',
    showCancelButton: true, confirmButtonText: 'Yes, make primary', reverseButtons: true
  }).then(async r => {
    if (!r.isConfirmed) return;
    const res = await fetch(`${CONTACT_URLS.base}/${id}/make-primary`, {
      method: 'POST',
      headers: {'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    if (!res.ok) return Swal.fire({icon:'error',title:'Could not update'});
    Swal.fire({toast:true,position:'top-end',icon:'success',title:'Primary updated',showConfirmButton:false,timer:1800});
    setTimeout(() => location.reload(), 300);
  });
}
@endif
</script>
@endsection