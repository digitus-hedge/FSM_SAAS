@extends('layouts.admin')
@section('title', $mode === 'create' ? 'New Client' : 'Edit Client')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:10px 18px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:#F0B700}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;max-width:780px;margin:0 auto}
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
  .field{display:flex;flex-direction:column;gap:6px}
  .field-wide{grid-column:span 2}
  .field label{font-size:12px;font-weight:600;color:var(--ink);letter-spacing:.01em}
  .req{color:var(--red)}
  .hint{font-size:11px;color:var(--muted);margin-top:2px}
  .err{font-size:11px;color:var(--red);margin-top:2px;font-weight:500;display:flex;align-items:center;gap:4px}
  .err::before{content:'⚠';font-size:12px}

  .input, textarea.input{font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:10px;border:1px solid var(--line);background:#FCFBF8;color:var(--ink);outline:none;transition:border .15s,background .15s,box-shadow .15s;width:100%}
  .input:focus, textarea.input:focus{border-color:var(--yellow);background:#fff;box-shadow:0 0 0 3px rgba(255,198,26,.15)}
  .input.has-error, textarea.input.has-error{border-color:var(--red);background:#fff;box-shadow:0 0 0 3px rgba(194,56,27,.08)}
  .input.has-error:focus, textarea.input.has-error:focus{box-shadow:0 0 0 3px rgba(194,56,27,.18)}
  textarea.input{resize:vertical;min-height:60px;font-family:'DM Sans'}
  .input.upper{text-transform:uppercase;letter-spacing:.03em;font-family:'DM Sans'}

  .check{display:flex;align-items:center;gap:10px;padding:10px 0}
  .check input[type="checkbox"]{appearance:none;-webkit-appearance:none;width:18px;height:18px;border:1.5px solid var(--line);border-radius:5px;background:#fff;cursor:pointer;position:relative;flex-shrink:0;transition:all .12s}
  .check input[type="checkbox"]:checked{background:var(--yellow);border-color:var(--yellow)}
  .check input[type="checkbox"]:checked::after{content:'';position:absolute;left:5px;top:1px;width:5px;height:10px;border:solid var(--ink);border-width:0 2px 2px 0;transform:rotate(45deg)}
  .check label{font-size:13px;color:var(--ink);cursor:pointer;font-weight:400}

  .form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--line)}

  /* Contacts section */
  .contacts-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:24px;max-width:780px;margin:20px auto 0}
  .contacts-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
  .contacts-head h2{font-family:'Figtree';font-size:18px;font-weight:700}
  .contacts-head p{color:var(--muted);font-size:12px;margin-top:2px}

  .contact-list{display:flex;flex-direction:column;gap:8px}
  .contact-row{display:flex;align-items:center;gap:14px;padding:12px;border:1px solid var(--line);border-radius:10px;background:#FCFBF8}
  .contact-row.is-primary{border-color:var(--yellow);background:#FFFCF2}
  .contact-ini{width:34px;height:34px;border-radius:50%;background:var(--yellow-soft);color:var(--amber);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .contact-info{flex:1;min-width:0}
  .contact-name{font-weight:600;font-size:13px;display:flex;align-items:center;gap:6px}
  .contact-meta{font-size:11px;color:var(--muted);margin-top:1px}
  .contact-badge{font-size:9px;font-weight:700;background:var(--yellow);color:var(--ink);padding:2px 6px;border-radius:4px;letter-spacing:.03em}
  .contact-actions{display:flex;gap:12px;align-items:center}
  .link-action{background:none;border:0;padding:0;cursor:pointer;font-family:inherit;font-size:12px;font-weight:600;text-decoration:none}
  .link-edit{color:var(--ink)}
  .link-edit:hover{color:var(--amber)}
  .link-remove{color:var(--red)}

  .empty-contacts{padding:30px 20px;text-align:center;color:var(--muted);font-size:13px;border:1px dashed var(--line);border-radius:10px}
  .empty-contacts strong{color:var(--ink)}

  /* SweetAlert2 — match brand */
  .swal2-popup{font-family:'DM Sans',system-ui,sans-serif!important;border-radius:16px!important;padding:28px!important}
  .swal2-title{font-family:'Figtree'!important;font-size:20px!important;font-weight:700!important;color:var(--ink)!important}
  .swal2-html-container{font-size:13px!important;color:var(--muted)!important;margin-top:6px!important}
  .swal2-confirm.swal2-styled{background:var(--yellow)!important;color:var(--ink)!important;font-family:'DM Sans'!important;font-weight:600!important;font-size:13px!important;border-radius:999px!important;padding:10px 20px!important;box-shadow:none!important}
  .swal2-confirm.swal2-styled:hover{background:#F0B700!important}
  .swal2-cancel.swal2-styled{background:#fff!important;color:var(--ink)!important;border:1px solid var(--line)!important;font-family:'DM Sans'!important;font-weight:600!important;font-size:13px!important;border-radius:999px!important;padding:10px 20px!important}
  .swal2-icon.swal2-error{border-color:var(--red)!important;color:var(--red)!important}
  .swal2-icon.swal2-success{border-color:var(--green)!important;color:var(--green)!important}
  .swal2-icon.swal2-warning{border-color:var(--yellow)!important;color:var(--amber)!important}
  .swal2-icon.swal2-error [class^='swal2-x-mark-line']{background-color:var(--red)!important}
  .swal2-icon.swal2-success [class^='swal2-success-line']{background-color:var(--green)!important}
  .swal2-toast{font-family:'DM Sans'!important;border-radius:12px!important;box-shadow:0 8px 24px rgba(0,0,0,.08)!important;border:1px solid var(--line)!important}
  .swal2-toast .swal2-title{font-size:13px!important}
  .swal-btn-red.swal2-confirm.swal2-styled{background:var(--red)!important;color:#fff!important}
  .swal-btn-red.swal2-confirm.swal2-styled:hover{background:#A52E15!important}

  /* Contact modal fields */
  .swal-form{display:flex;flex-direction:column;gap:12px;text-align:left}
  .swal-form label{display:block;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:4px}
  .swal-form input[type="text"], .swal-form input[type="email"]{font-family:'DM Sans';font-size:13px;padding:9px 12px;border-radius:8px;border:1px solid var(--line);background:#FCFBF8;color:var(--ink);width:100%;outline:none;transition:border .15s}
  .swal-form input[type="text"]:focus, .swal-form input[type="email"]:focus{border-color:var(--yellow);background:#fff}
  .swal-form .check-row{display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;padding:6px 0}
</style>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New client' : 'Edit '.$client->name }}</h1>
  <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost">← Back</a>
</div>

<form method="POST"
      action="{{ $mode === 'create' ? route('admin.clients.store') : route('admin.clients.update', $client) }}"
      class="form-card"
      novalidate>
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="form-grid">

    {{-- CLIENT CODE --}}
    <div class="field">
      <label for="client_code">Client code <span class="req">*</span></label>
      <input id="client_code" name="client_code"
             value="{{ old('client_code', $client->client_code) }}"
             maxlength="40" required
             pattern="[A-Za-z0-9_\-]+"
             class="input upper @error('client_code') has-error @enderror" />
      @error('client_code')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Unique per tenant. Letters, digits, dashes only. Example: ACME001</span>
      @enderror
    </div>

    {{-- NAME --}}
    <div class="field">
      <label for="name">Name <span class="req">*</span></label>
      <input id="name" name="name"
             value="{{ old('name', $client->name) }}"
             maxlength="180" required minlength="2"
             class="input @error('name') has-error @enderror" />
      @error('name')
        <span class="err">{{ $message }}</span>
      @enderror
    </div>

    {{-- CONTACT PERSON --}}
    <div class="field">
      <label for="contact_person">Primary contact person</label>
      <input id="contact_person" name="contact_person"
             value="{{ old('contact_person', $client->contact_person) }}"
             maxlength="150"
             class="input @error('contact_person') has-error @enderror" />
      @error('contact_person')
        <span class="err">{{ $message }}</span>
      @enderror
    </div>

    {{-- EMAIL --}}
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email"
             value="{{ old('email', $client->email) }}"
             maxlength="190"
             class="input @error('email') has-error @enderror" />
      @error('email')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">For quote and invoice delivery.</span>
      @enderror
    </div>

    {{-- PHONE --}}
    <div class="field">
      <label for="phone">Phone</label>
      <input id="phone" name="phone"
             value="{{ old('phone', $client->phone) }}"
             maxlength="40"
             pattern="[0-9+\-\s()]*"
             class="input @error('phone') has-error @enderror" />
      @error('phone')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Digits, +, -, spaces. Include country code.</span>
      @enderror
    </div>

    {{-- TAX ID --}}
    <div class="field">
      <label for="tax_id">Tax ID (GSTIN / VAT)</label>
      <input id="tax_id" name="tax_id"
             value="{{ old('tax_id', $client->tax_id) }}"
             maxlength="60"
             class="input @error('tax_id') has-error @enderror" />
      @error('tax_id')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Appears on invoices.</span>
      @enderror
    </div>

    {{-- PAYMENT TERMS --}}
    <div class="field">
      <label for="payment_terms_days">Payment terms (days) <span class="req">*</span></label>
      <input type="number" id="payment_terms_days" name="payment_terms_days"
             min="0" max="365" step="1" required
             value="{{ old('payment_terms_days', $client->payment_terms_days ?? 30) }}"
             class="input @error('payment_terms_days') has-error @enderror" />
      @error('payment_terms_days')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Default 30 — net days until invoice due.</span>
      @enderror
    </div>

    {{-- ASSET VISIBILITY CHECKBOX --}}
    <div class="field" style="justify-content:flex-end">
      <div class="check">
        <input type="hidden" name="asset_visibility_enabled" value="0">
        <input type="checkbox" name="asset_visibility_enabled" id="asset_visibility_enabled" value="1"
               @checked(old('asset_visibility_enabled', $client->asset_visibility_enabled)) />
        <label for="asset_visibility_enabled">Allow this client to view their assets in the portal</label>
      </div>
      @error('asset_visibility_enabled')
        <span class="err">{{ $message }}</span>
      @enderror
    </div>

    {{-- BILLING ADDRESS --}}
    <div class="field field-wide">
      <label for="billing_address">Billing address</label>
      <textarea id="billing_address" name="billing_address" rows="2" maxlength="1000"
                class="input @error('billing_address') has-error @enderror">{{ old('billing_address', $client->billing_address) }}</textarea>
      @error('billing_address')
        <span class="err">{{ $message }}</span>
      @enderror
    </div>

    {{-- NOTES --}}
    <div class="field field-wide">
      <label for="notes">Internal notes</label>
      <textarea id="notes" name="notes" rows="3" maxlength="2000"
                class="input @error('notes') has-error @enderror">{{ old('notes', $client->notes) }}</textarea>
      @error('notes')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Visible only to your team. Not shared with the client.</span>
      @enderror
    </div>

    {{-- ACTIVE --}}
    <div class="field">
      <div class="check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               @checked(old('is_active', $client->exists ? $client->is_active : true)) />
        <label for="is_active">Active</label>
      </div>
      @error('is_active')
        <span class="err">{{ $message }}</span>
      @enderror
    </div>

  </div>

  <div class="form-actions">
    <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      {{ $mode === 'create' ? 'Create client' : 'Save changes' }}
    </button>
  </div>
</form>

{{-- CONTACTS section — only on edit, since contacts need a client_id --}}
@if ($mode === 'edit')
<div class="contacts-card">
  <div class="contacts-head">
    <div>
      <h2>Contacts</h2>
      <p>People at {{ $client->name }} we communicate with.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openContactModal()">+ Add contact</button>
  </div>

  <div class="contact-list" id="contact-list">
    @forelse ($client->contacts as $contact)
      <div class="contact-row {{ $contact->is_primary ? 'is-primary' : '' }}" data-id="{{ $contact->id }}">
        <div class="contact-ini">{{ strtoupper(substr($contact->name, 0, 2)) }}</div>
        <div class="contact-info">
          <div class="contact-name">
            {{ $contact->name }}
            @if ($contact->is_primary)
              <span class="contact-badge">PRIMARY</span>
            @endif
          </div>
          <div class="contact-meta">
            {{ $contact->email ?? '—' }}
            @if ($contact->phone)  · {{ $contact->phone }} @endif
            @if ($contact->whatsapp_number) · WA {{ $contact->whatsapp_number }} @endif
          </div>
        </div>
        <div class="contact-actions">
          <button type="button" class="link-action link-edit"
                  onclick='openContactModal(@json($contact))'>Edit</button>
          <button type="button" class="link-action link-remove"
                  onclick='removeContact({{ $contact->id }}, @json($contact->name))'>Remove</button>
        </div>
      </div>
    @empty
      <div class="empty-contacts">
        No contacts yet. Click <strong>+ Add contact</strong> to add the first one.
      </div>
    @endforelse
  </div>
</div>
@endif

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

  @if ($errors->any())
    Swal.fire({
      icon: 'error',
      title: 'Please check your form',
      html: @json(
          $errors->count() . ' ' . Str::plural('field', $errors->count())
          . ' need' . ($errors->count() === 1 ? 's' : '') . ' attention. '
          . 'Scroll down to see what to fix.'
      ),
      confirmButtonText: 'Got it'
    }).then(() => {
      const firstBad = document.querySelector('.input.has-error');
      if (firstBad) {
        firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstBad.focus({ preventScroll: true });
      }
    });
  @endif

  @if (session('success'))
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: @json(session('success')),
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true
    });
  @endif

});

/* ========================================================================
   CONTACTS — add / edit / remove via SweetAlert2 modal + fetch API
   ======================================================================== */
@if ($mode === 'edit')
const CONTACT_URLS = {
  store: @json(route('admin.clients.contacts.store', $client)),
  base:  @json(url('/admin/clients/'.$client->id.'/contacts')),
};
const CSRF_TOKEN = @json(csrf_token());

function openContactModal(contact = null) {
  const isEdit = !!contact;
  const esc = (s) => (s ?? '').toString()
      .replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

  Swal.fire({
    title: isEdit ? 'Edit contact' : 'Add contact',
    html: `
      <div class="swal-form">
        <div>
          <label>Name *</label>
          <input id="c-name" type="text" maxlength="150" value="${esc(contact?.name)}">
        </div>
        <div>
          <label>Email</label>
          <input id="c-email" type="email" maxlength="190" value="${esc(contact?.email)}">
        </div>
        <div>
          <label>Phone</label>
          <input id="c-phone" type="text" maxlength="40" value="${esc(contact?.phone)}">
        </div>
        <div>
          <label>WhatsApp number</label>
          <input id="c-wa" type="text" maxlength="40" value="${esc(contact?.whatsapp_number)}">
        </div>
        <label class="check-row">
          <input type="checkbox" id="c-primary" ${contact?.is_primary ? 'checked' : ''}>
          Primary contact for this client
        </label>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: isEdit ? 'Save changes' : 'Add contact',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusConfirm: false,
    didOpen: () => document.getElementById('c-name')?.focus(),
    preConfirm: () => {
      const name = document.getElementById('c-name').value.trim();
      if (!name) {
        Swal.showValidationMessage('Name is required');
        return false;
      }
      return {
        name,
        email:            document.getElementById('c-email').value.trim(),
        phone:            document.getElementById('c-phone').value.trim(),
        whatsapp_number:  document.getElementById('c-wa').value.trim(),
        is_primary:       document.getElementById('c-primary').checked ? 1 : 0,
      };
    }
  }).then(async (result) => {
    if (!result.isConfirmed) return;

    const url    = isEdit ? `${CONTACT_URLS.base}/${contact.id}` : CONTACT_URLS.store;
    const method = isEdit ? 'PUT' : 'POST';

    try {
      const res = await fetch(url, {
        method,
        headers: {
          'Content-Type':     'application/json',
          'X-CSRF-TOKEN':     CSRF_TOKEN,
          'Accept':           'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(result.value),
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        const msg = err.errors
          ? Object.values(err.errors).flat().join('\n')
          : (err.message || 'Save failed.');
        return Swal.fire({ icon: 'error', title: 'Could not save', text: msg });
      }

      const data = await res.json();
      Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: data.message, showConfirmButton: false, timer: 2500,
      });
      setTimeout(() => window.location.reload(), 400);

    } catch (e) {
      Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
    }
  });
}

function removeContact(id, name) {
  Swal.fire({
    icon: 'warning',
    title: `Remove ${name}?`,
    text: 'This contact will stop receiving notifications for this client.',
    showCancelButton: true,
    confirmButtonText: 'Yes, remove',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusCancel: true,
    customClass: { confirmButton: 'swal-btn-red' },
  }).then(async (result) => {
    if (!result.isConfirmed) return;

    try {
      const res = await fetch(`${CONTACT_URLS.base}/${id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': CSRF_TOKEN,
          'Accept':       'application/json',
        },
      });
      if (!res.ok) throw new Error('Delete failed');
      const data = await res.json();
      Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: data.message, showConfirmButton: false, timer: 2500,
      });
      setTimeout(() => window.location.reload(), 400);
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'Could not remove', text: e.message });
    }
  });
}
@endif
</script>
@endsection