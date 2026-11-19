@extends('layouts.admin')
@section('title', $template->name)

@section('content')
@include('admin.partials._page')
<style>
  .sec-box{border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:14px;background:#fff}
  .fld{border:1px solid var(--line);border-radius:10px;padding:12px;margin:10px 0;background:#FCFBF8}
  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px}
  .mini{font-size:12px;padding:5px 10px}
</style>

<div class="page-head">
  <div>
    <h1>{{ $template->name }}</h1>
    <p>
      @if ($draft) Draft, version {{ $draft->version_no }}. @elseif ($published) Version {{ $published->version_no }} is published and cannot be changed. @endif
      @if ($published && $draft) Version {{ $published->version_no }} stays live until you publish this draft. @endif
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="{{ route('admin.checklists.index') }}" class="btn btn-ghost">Back</a>
    @if ($readonly)
      <form method="POST" action="{{ route('admin.checklists.edit-draft', $template) }}">@csrf<button class="btn btn-primary">Edit as new draft</button></form>
    @endif
  </div>
</div>

<form method="POST" id="builder-form" action="{{ route('admin.checklists.draft.save', $template) }}">
  @csrf
  <input type="hidden" name="schema" id="schema-input">
  <div id="builder"></div>

  @unless ($readonly)
    <div class="row" style="margin-top:12px">
      <button type="button" class="btn btn-ghost" id="add-section">Add section</button>
      <button type="submit" class="btn btn-ghost">Save draft</button>
      <button type="submit" class="btn btn-primary" formaction="{{ route('admin.checklists.publish', $template) }}" formmethod="POST" id="publish-btn">Publish</button>
    </div>
  @endunless
</form>
@unless ($readonly)
<form method="POST" action="{{ route('admin.checklists.draft.discard', $template) }}" style="margin-top:8px" onsubmit="return confirm('Discard this draft?')">@csrf<button class="btn btn-danger mini">Discard draft</button></form>
@endunless

<script>
(function () {
  var readonly = @json($readonly);
  var TYPES = @json($types), OPS = @json($ops);
  var LABELS = {text:'Text', number:'Number', dropdown:'Dropdown', checkbox:'Checkbox', yes_no:'Yes / No', date:'Date', photo:'Photo', signature:'Signature'};
  var schema = @json($schema);
  var root = document.getElementById('builder');
  var uid = 0;
  function key() { return 'f' + Date.now().toString(36) + (uid++); }

  function el(tag, attrs, kids) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { if (k === 'text') e.textContent = attrs[k]; else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2), attrs[k]); else e.setAttribute(k, attrs[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function input(val, cb, attrs) {
    var i = el('input', Object.assign({class: 'inp', value: val == null ? '' : val}, attrs || {}));
    if (readonly) i.disabled = true;
    i.addEventListener('input', function () { cb(i.value); });
    return i;
  }
  function check(label, val, cb) {
    var c = el('input', {type: 'checkbox'}); c.checked = !!val; c.disabled = readonly;
    c.addEventListener('change', function () { cb(c.checked); });
    return el('label', {}, [c, document.createTextNode(' ' + label)]);
  }
  function earlier(si, fi) {
    var out = [];
    schema.sections.forEach(function (s, a) { s.fields.forEach(function (f, b) { if (a < si || (a === si && b < fi)) out.push(f); }); });
    return out;
  }

  function render() {
    root.innerHTML = '';
    schema.sections.forEach(function (s, si) {
      var box = el('div', {class: 'sec-box'});
      var head = el('div', {class: 'row'});
      head.appendChild(input(s.title, function (v) { s.title = v; }, {placeholder: 'Section title', maxlength: 120}));
      if (!readonly) {
        head.appendChild(el('button', {type: 'button', class: 'btn btn-ghost mini', text: 'Add field', onclick: function () { s.fields.push({key: key(), type: 'text', label: '', required: false, options: []}); render(); }}));
        if (schema.sections.length > 1) head.appendChild(el('button', {type: 'button', class: 'btn btn-danger mini', text: 'Remove section', onclick: function () { schema.sections.splice(si, 1); render(); }}));
      }
      box.appendChild(head);
      s.fields.forEach(function (f, fi) {
        var d = el('div', {class: 'fld'});
        var r1 = el('div', {class: 'row'});
        r1.appendChild(input(f.label, function (v) { f.label = v; }, {placeholder: 'Label', maxlength: 120, style: 'min-width:240px'}));
        var sel = el('select', {class: 'sel'});
        TYPES.forEach(function (t) { var o = el('option', {value: t, text: LABELS[t]}); if (t === f.type) o.selected = true; sel.appendChild(o); });
        sel.disabled = readonly;
        sel.addEventListener('change', function () { f.type = sel.value; render(); });
        r1.appendChild(sel);
        r1.appendChild(check('Required', f.required, function (v) { f.required = v; }));
        r1.appendChild(check('Visible to client', f.visible_to_client, function (v) { f.visible_to_client = v; }));
        if (f.type === 'yes_no') r1.appendChild(check('A "No" fails the inspection', f.fails_on_no, function (v) { f.fails_on_no = v; }));
        if (!readonly) {
          r1.appendChild(el('button', {type: 'button', class: 'btn btn-ghost mini', text: 'Up', onclick: function () { if (fi > 0) { s.fields.splice(fi - 1, 0, s.fields.splice(fi, 1)[0]); render(); } }}));
          r1.appendChild(el('button', {type: 'button', class: 'btn btn-ghost mini', text: 'Down', onclick: function () { if (fi < s.fields.length - 1) { s.fields.splice(fi + 1, 0, s.fields.splice(fi, 1)[0]); render(); } }}));
          r1.appendChild(el('button', {type: 'button', class: 'btn btn-danger mini', text: 'Delete', onclick: function () { s.fields.splice(fi, 1); render(); }}));
        }
        d.appendChild(r1);
        var r2 = el('div', {class: 'row'});
        r2.appendChild(input(f.help, function (v) { f.help = v; }, {placeholder: 'Help text (optional)', maxlength: 300, style: 'min-width:320px'}));
        if (f.type === 'number') {
          r2.appendChild(input(f.unit, function (v) { f.unit = v; }, {placeholder: 'Unit', style: 'width:80px'}));
          r2.appendChild(input(f.min, function (v) { f.min = v; }, {placeholder: 'Min', type: 'number', step: 'any', style: 'width:90px'}));
          r2.appendChild(input(f.max, function (v) { f.max = v; }, {placeholder: 'Max', type: 'number', step: 'any', style: 'width:90px'}));
        }
        if (f.type === 'dropdown') {
          var ta = el('textarea', {class: 'txta', placeholder: 'One option on each line (2 to 20)', style: 'min-height:80px'});
          ta.value = (f.options || []).join('\n'); ta.disabled = readonly;
          ta.addEventListener('input', function () { f.options = ta.value.split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean); });
          r2.appendChild(ta);
        }
        d.appendChild(r2);
        var prev = earlier(si, fi);
        if (prev.length) {
          var r3 = el('div', {class: 'row'});
          var sf = el('select', {class: 'sel'}); sf.disabled = readonly;
          sf.appendChild(el('option', {value: '', text: 'Always show'}));
          prev.forEach(function (p) { var o = el('option', {value: p.key, text: 'Show if: ' + (p.label || p.key)}); if (f.show_if && f.show_if.field === p.key) o.selected = true; sf.appendChild(o); });
          var so = el('select', {class: 'sel'}); so.disabled = readonly;
          OPS.forEach(function (o) { var op = el('option', {value: o, text: o.replace('_', ' ')}); if (f.show_if && f.show_if.op === o) op.selected = true; so.appendChild(op); });
          var sv = input(f.show_if ? f.show_if.value : '', function (v) { if (f.show_if) f.show_if.value = v; }, {placeholder: 'Value', style: 'width:120px'});
          function sync() { f.show_if = sf.value ? {field: sf.value, op: so.value, value: sv.value} : null; }
          sf.addEventListener('change', sync); so.addEventListener('change', sync); sv.addEventListener('input', sync);
          r3.appendChild(sf); if (f.show_if) { r3.appendChild(so); r3.appendChild(sv); }
          sf.addEventListener('change', function () { render(); });
          d.appendChild(r3);
        }
        box.appendChild(d);
      });
      root.appendChild(box);
    });
  }

  if (!schema.sections || !schema.sections.length) schema = {sections: [{key: 's1', title: 'Checks', fields: []}]};
  render();
  var add = document.getElementById('add-section');
  if (add) add.addEventListener('click', function () { schema.sections.push({key: 's' + (schema.sections.length + 1) + Date.now().toString(36), title: '', fields: []}); render(); });
  document.getElementById('builder-form').addEventListener('submit', function () { document.getElementById('schema-input').value = JSON.stringify(schema); });
})();
</script>
@endsection
