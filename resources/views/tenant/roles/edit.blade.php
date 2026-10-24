@extends('layouts.admin')
@section('title', $role->name . ' — permissions')
@section('content')
<style>
.rhead{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px}
.rhead h1{font:700 26px 'Figtree';letter-spacing:-.4px;margin:0}
.rhead .meta{display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap}
.rcode{color:#5F5D6E;font-size:12px;font-family:'JetBrains Mono',Consolas,monospace;background:#F5F4FA;padding:4px 10px;border-radius:6px}
.badge-std{display:inline-flex;align-items:center;gap:5px;background:#FFF4CC;color:#8A5A00;font-weight:600;font-size:11px;padding:3px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:.06em}
.summary-chip{background:#fff;border:1px solid #E6E4EE;padding:6px 14px;border-radius:999px;font-size:12.5px;color:#5F5D6E}
.summary-chip b{color:#1B1A24;font-weight:700}
.banner{background:#E3EBF4;border:1px solid #2B5A8C;color:#1C3E63;padding:11px 16px;border-radius:12px;margin:16px 0 20px;font-size:13px}
.module{background:#fff;border:1px solid #E6E4EE;border-radius:16px;margin-bottom:14px;overflow:hidden}
.module summary{display:flex;align-items:center;gap:12px;padding:16px 20px;cursor:pointer;list-style:none;user-select:none}
.module summary::-webkit-details-marker{display:none}
.module summary:hover{background:#F5F4FA}
.chev{width:14px;height:14px;color:#5F5D6E;transition:transform .18s ease}
.module[open] > summary .chev{transform:rotate(90deg)}
.mod-title{font:700 15px 'Figtree';flex:1}
.mod-count{color:#5F5D6E;font-size:12px}.mod-count b{color:#1B1A24;font-weight:700}
.perms{border-top:1px solid #E6E4EE}
.perm{display:grid;grid-template-columns:1fr auto;gap:16px;padding:14px 20px;border-top:1px solid #E6E4EE;align-items:center}
.perm:first-child{border-top:none}
.perm.changed{background:#FFF4CC}
.perm-key{font-family:'JetBrains Mono',Consolas,monospace;font-size:12px;font-weight:600;color:#1B1A24;display:inline-flex;align-items:center;gap:7px}
.changed-dot{width:7px;height:7px;border-radius:50%;background:#FFC61A;box-shadow:0 0 0 2px rgba(255,198,26,.25)}
.perm-desc{color:#5F5D6E;font-size:12.5px;margin-top:3px;line-height:1.45}
.scope-tip{position:relative;display:inline-flex;align-items:center;gap:4px;color:#8B89A0;font-size:11px;margin-top:4px;cursor:help}
.scope-tip svg{width:11px;height:11px}
.scope-tip:hover{color:#5F5D6E}
.scope-tip .bubble{display:none;position:absolute;bottom:calc(100% + 4px);left:0;background:#1B1A24;color:#FBFAF6;padding:7px 10px;border-radius:7px;font-size:11.5px;line-height:1.4;max-width:320px;width:max-content;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.12);font-family:'DM Sans'}
.scope-tip:hover .bubble{display:block}
.levels{display:flex;gap:4px;background:#F5F4FA;padding:3px;border-radius:999px}
.level{padding:5px 11px;font-size:11.5px;font-weight:600;border:0;background:transparent;color:#5F5D6E;border-radius:999px;cursor:pointer;font-family:inherit;white-space:nowrap;transition:all .12s}
.level:hover:not(.active):not(.disabled){background:#fff;color:#1B1A24}
.level.active{background:#1B1A24;color:#FBFAF6}
.level.active[data-lvl="none"]{background:#E6E4EE;color:#5F5D6E}
.level.active[data-lvl="view_only"],.level.active[data-lvl="view_own"]{background:#2B5A8C;color:#fff}
.level.active[data-lvl="edit_own"]{background:#8A5A00;color:#fff}
.level.active[data-lvl="record_level"]{background:#CC6B2E;color:#fff}
.level.active[data-lvl="full"]{background:#00866F;color:#fff}
.level.active[data-lvl="grantable"]{background:#FFC61A;color:#1B1A24}
.level.disabled{color:#B8B6C7;cursor:not-allowed;text-decoration:line-through;opacity:.5}
#role-form{padding-bottom:80px}
.savebar{position:sticky;bottom:0;background:#fff;border-top:1px solid #E6E4EE;padding:14px 20px;margin:0 -44px -48px;padding:16px 44px calc(16px + env(safe-area-inset-bottom, 0px));box-shadow:0 -4px 16px rgba(0,0,0,.04);display:flex;align-items:center;gap:12px;flex-wrap:wrap;z-index:5}
.savebar .changes{color:#5F5D6E;font-size:13px;flex:1}
.savebar .changes b{color:#1B1A24;font-weight:700}
.btn-y{background:#FFC61A;color:#1B1A24;font-weight:700;font-size:13.5px;padding:11px 24px;border-radius:999px;border:0;cursor:pointer}
.btn-y:hover{background:#F0B700}
.btn-ghost{background:#fff;border:1px solid #E6E4EE;color:#1B1A24;font-weight:600;font-size:13px;padding:10px 18px;border-radius:999px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-ghost:hover{background:#F5F4FA}
@media (max-width:600px){.perm{grid-template-columns:1fr}.levels{overflow-x:auto}}
</style>

<div class="rhead">
    <div>
        <h1>{{ $role->name }}</h1>
        <div class="meta">
            <span class="rcode">{{ $role->code }}</span>
            @if ($role->is_standard)
                <span class="badge-std">● Standard role</span>
            @else
                <span class="badge-std" style="background:#F5F4FA;color:#5F5D6E">● Custom · based on {{ $role->based_on_code }}</span>
            @endif
            <span class="summary-chip" id="summary-chip">—</span>
        </div>
    </div>
</div>

<div class="banner">
    <b>{{ $affectedUsers }}</b> user{{ $affectedUsers === 1 ? '' : 's' }} will be affected by changes to this role.
</div>

<form method="POST" action="{{ route('tenant.roles.update', $role) }}" id="role-form">
    @csrf
    @method('PUT')

    @foreach ($permissions as $group => $groupPerms)
    <details class="module" open>
        <summary>
            <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="mod-title">{{ $group }}</span>
            <span class="mod-count" data-mod-count="{{ $group }}">—</span>
        </summary>
        <div class="perms">
            @foreach ($groupPerms as $p)
                @php
                    $current   = $currentLevels[$p->id] ?? 'none';
                    $default   = $defaultLevels[$p->id] ?? 'none';
                    $allowed   = $allowedLevels[$p->id] ?? ['none'];
                    $isChanged = $current !== $default;
                    $scope     = $scopeNotes[$p->id] ?? null;
                @endphp
                <div class="perm {{ $isChanged ? 'changed' : '' }}" data-perm-id="{{ $p->id }}" data-default="{{ $default }}" data-group="{{ $group }}">
                    <div>
                        <span class="perm-key">
                            @if ($isChanged)<span class="changed-dot" title="Changed from signed default ({{ $default }})"></span>@endif
                            {{ $p->key }}
                        </span>
                        <div class="perm-desc">{{ $p->description }}</div>
                        @if ($scope)
                            <div class="scope-tip">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                Scope
                                <span class="bubble">{{ $scope }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="levels">
                        <input type="hidden" name="levels[{{ $p->id }}]" value="{{ $current }}" class="level-input">
                        @foreach (\App\Services\Permissions\PermissionMatrix::LEVEL_ORDER as $lvl)
                            @php
                                $isAllowed = in_array($lvl, $allowed, true);
                            @endphp
                            <button type="button"
                                    class="level {{ $current === $lvl ? 'active' : '' }} {{ $isAllowed ? '' : 'disabled' }}"
                                    data-lvl="{{ $lvl }}"
                                    @if (! $isAllowed) disabled title="This level does not apply to {{ $p->key }}" @endif>
                                {{ $levelLabels[$lvl] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </details>
    @endforeach

    <div class="savebar">
        <div class="changes" id="changes-count">No unsaved changes</div>
        @if ($role->is_standard)
            <button form="reset-form" type="submit" class="btn-ghost">Reset to signed defaults</button>
        @endif
        <a href="{{ route('tenant.roles.index') }}" class="btn-ghost">Cancel</a>
        <button type="submit" class="btn-y">Save permissions</button>
    </div>
</form>

@if ($role->is_standard)
<form id="reset-form" method="POST" action="{{ route('tenant.roles.reset-defaults', $role) }}"
      class="needs-confirm" data-confirm-title="Reset {{ $role->name }}?"
      data-confirm-text="Permissions will be reloaded from the signed matrix. Your custom changes will be lost."
      data-confirm-ok="Reset to defaults" data-confirm-ok-color="amber">
    @csrf
</form>
@endif

<script>
(function () {
    const form = document.getElementById('role-form');

    function updateCounts() {
        let changed = 0, enabled = 0, total = 0;
        const perModule = {};

        document.querySelectorAll('.perm').forEach(row => {
            total++;
            const current = row.querySelector('.level-input').value;
            const def = row.dataset.default;
            const group = row.dataset.group;

            if (!perModule[group]) perModule[group] = { enabled: 0, total: 0 };
            perModule[group].total++;

            if (current !== 'none') { enabled++; perModule[group].enabled++; }
            if (current !== def) changed++;
        });

        document.getElementById('summary-chip').innerHTML =
            `<b>${enabled}</b> of <b>${total}</b> permissions enabled · <b>${changed}</b> changed from defaults`;

        document.querySelectorAll('[data-mod-count]').forEach(el => {
            const g = el.getAttribute('data-mod-count');
            const m = perModule[g] || { enabled: 0, total: 0 };
            el.innerHTML = `<b>${m.enabled}</b> of ${m.total} enabled`;
        });

        document.getElementById('changes-count').innerHTML = changed === 0
            ? 'No unsaved changes'
            : `<b>${changed} unsaved change${changed === 1 ? '' : 's'}</b>`;
    }

    document.querySelectorAll('.levels').forEach(group => {
        group.addEventListener('click', e => {
            const btn = e.target.closest('.level');
            if (!btn || btn.classList.contains('disabled')) return;
            const row = btn.closest('.perm');
            const input = row.querySelector('.level-input');
            const def = row.dataset.default;

            group.querySelectorAll('.level').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            input.value = btn.dataset.lvl;

            // Changed indicator
            const dotEl = row.querySelector('.changed-dot');
            if (input.value !== def) {
                row.classList.add('changed');
                if (!dotEl) {
                    const span = document.createElement('span');
                    span.className = 'changed-dot';
                    row.querySelector('.perm-key').prepend(span);
                }
            } else {
                row.classList.remove('changed');
                dotEl?.remove();
            }

            updateCounts();
        });
    });

    updateCounts();
})();
</script>
@endsection