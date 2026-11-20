<?php

namespace App\Services\Forms;

use App\Models\Asset;
use App\Models\FieldEventLog;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAsset;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The checklist on a job (FRM-04, FRM-05, D4, D8, D10, D11). A job binds to a published version when it is created,
 * the technician saves answers (offline-safe), and the proof lock reads what is missing.
 */
class Checklists
{
    public const EVENT = 'checklist_saved';

    /** The depot checklists: [version column, payload column, result column] (Multi-Trade-Vendors-Depot spec D15). */
    public const SLOTS = [
        'intake' => ['intake_form_version_id', 'intake_payload', 'intake_result'],
        'return' => ['return_form_version_id', 'return_payload', 'return_result'],
    ];

    /** The published version of an active template of the company, or null. */
    public function publishedVersionOf(?int $templateId, int $tenantId): ?FormTemplateVersion
    {
        if (! $templateId || ! app(FormTemplates::class)->enabled(Tenant::find($tenantId))) {
            return null;
        }
        $template = FormTemplate::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'active')->find($templateId);

        return $template ? FormTemplateVersion::withoutGlobalScopes()->where('form_template_id', $template->id)->where('status', 'published')->first() : null;
    }

    /** The published version a new job takes: the schedule's template, then the asset's, then the category's (D8). */
    public function versionFor(?ServiceCategory $category, ?Asset $asset, ?int $scheduleTemplateId = null): ?FormTemplateVersion
    {
        $tenant = Tenant::find($category?->tenant_id ?? $asset?->tenant_id);
        if (! $tenant || ! app(FormTemplates::class)->enabled($tenant)) {
            return null;
        }

        foreach ([$scheduleTemplateId, $asset?->form_template_id, $category?->active_form_template_id] as $templateId) {
            if (! $templateId) {
                continue;
            }
            $template = FormTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'active')->find($templateId);
            $version  = $template ? FormTemplateVersion::withoutGlobalScopes()->where('form_template_id', $template->id)->where('status', 'published')->first() : null;
            if ($version) {
                return $version;   // a template without a published version means: no checklist (D4)
            }
        }

        return null;
    }

    /** Stores the bound version on a new request. */
    public function bind(ServiceRequest $sr, ?ServiceCategory $category, ?Asset $asset, ?int $scheduleTemplateId = null): void
    {
        $version = $this->versionFor($category, $asset, $scheduleTemplateId);
        if ($version) {
            $sr->forceFill(['form_template_version_id' => $version->id])->save();
        }
    }

    /**
     * Saves answers for a request, or for one asset of a batch (D10). Idempotent on the event uuid, the newest device
     * time wins, and an event for another version than the bound one is refused.
     *
     * @param  array<string,mixed> $input    answers by field key (files already turned into paths by prepareInput)
     * @return array{result:?string, complete:bool, missing:int, applied:bool}
     */
    public function save(ServiceRequest $sr, ?ServiceRequestAsset $row, User $user, array $input, string $uuid, ?Carbon $deviceAt = null, ?int $versionId = null, string $slot = 'main'): array
    {
        $cols    = self::SLOTS[$slot] ?? null;   // depot intake and return checklists keep their own columns (spec D15)
        $boundId = $cols ? $sr->{$cols[0]} : ($row ? $row->form_template_version_id : $sr->form_template_version_id);
        $version = $boundId ? FormTemplateVersion::withoutGlobalScopes()->find($boundId) : null;
        if (! $version) {
            throw new FormException('CHECKLIST_NOT_BOUND', 'This job has no checklist.');
        }
        if ($versionId !== null && (int) $versionId !== (int) $version->id) {
            $this->log($sr, $user, $uuid, $deviceAt, 'conflict_rejected', ['asset_id' => $row?->asset_id, 'version_id' => $versionId, 'slot' => $slot], 'version_mismatch');
            throw new FormException('CHECKLIST_VERSION_MISMATCH', 'This checklist changed. Reload the job to continue.', 409);
        }

        $deviceAt = $deviceAt ?? now();
        $assetId  = $row?->asset_id;

        // The same event twice changes nothing.
        $dup = FieldEventLog::withoutGlobalScopes()->where('tenant_id', $sr->tenant_id)->where('client_event_uuid', $uuid)->exists();
        if ($dup) {
            return $this->state($version, $this->payload($sr, $row, $slot), false);
        }

        // An older event arriving after a newer one is ignored (D10).
        $newest = FieldEventLog::withoutGlobalScopes()
            ->where('tenant_id', $sr->tenant_id)->where('service_request_id', $sr->id)
            ->where('event_type', self::EVENT)->where('result', 'applied')
            ->get()->filter(fn ($e) => ($e->payload_json['asset_id'] ?? null) == $assetId && ($e->payload_json['slot'] ?? 'main') === $slot)
            ->max('recorded_at_device');
        if ($newest && $deviceAt->lt($newest)) {
            $this->log($sr, $user, $uuid, $deviceAt, 'conflict_rejected', ['asset_id' => $assetId, 'version_id' => $version->id, 'slot' => $slot], 'older_event');

            return $this->state($version, $this->payload($sr, $row, $slot), false);
        }

        $eval = FormSchema::evaluate($version->schema_json ?? [], $input);
        if ($eval['errors']) {
            $this->log($sr, $user, $uuid, $deviceAt, 'invalid', ['asset_id' => $assetId, 'version_id' => $version->id, 'slot' => $slot], 'invalid_answer');
            throw new FormException('CHECKLIST_INVALID', (string) array_values($eval['errors'])[0]);
        }

        DB::transaction(function () use ($sr, $row, $user, $uuid, $deviceAt, $version, $eval, $assetId, $cols, $slot) {
            if ($cols) {
                $sr->forceFill([$cols[1] => $eval['answers'], $cols[2] => $eval['result']])->save();
            } elseif ($row) {
                $row->forceFill(['asset_checklist_payload' => $eval['answers'], 'checklist_result' => $eval['result']])->save();
            } else {
                $sr->forceFill(['sr_checklist_payload' => $eval['answers'], 'checklist_result' => $eval['result'], 'checklist_saved_at' => now()])->save();
            }
            $this->log($sr, $user, $uuid, $deviceAt, 'applied', ['asset_id' => $assetId, 'version_id' => $version->id, 'slot' => $slot, 'answers' => $eval['answers']]);
        });

        return $this->state($version, $eval['answers'], true);
    }

    /**
     * Turns what a device posted into answers: uploaded photos are stored, a drawn signature (data URL) is stored, and a
     * photo or signature that was not sent again keeps its saved value. A path the server did not create is never accepted.
     */
    public function prepareInput(ServiceRequest $sr, ?ServiceRequestAsset $row, FormTemplateVersion $version, array $posted, array $files, string $slot = 'main'): array
    {
        $saved  = $this->payload($sr, $row, $slot);
        $answers = $posted;

        foreach (FormSchema::fields($version->schema_json ?? []) as $key => $f) {
            if (! in_array($f['type'], ['photo', 'signature'], true)) {
                continue;
            }
            $paths = [];
            foreach ((array) ($files[$key] ?? []) as $file) {
                if ($file instanceof UploadedFile && str_starts_with((string) $file->getMimeType(), 'image/')) {
                    $paths[] = $file->store("sr/{$sr->id}/checklist", 'public');
                }
            }
            $drawn = $posted[$key] ?? null;
            if ($f['type'] === 'signature' && is_string($drawn) && preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $drawn, $m) && strlen($m[1]) < 700000) {
                $path = "sr/{$sr->id}/checklist/sig-" . bin2hex(random_bytes(6)) . '.png';
                Storage::disk('public')->put($path, base64_decode($m[1]));
                $paths = [$path];
            }
            unset($answers[$key]);
            if ($paths) {
                $answers[$key] = $f['type'] === 'photo' ? array_slice(array_merge((array) ($saved[$key] ?? []), $paths), 0, 5) : $paths[0];
            } elseif (isset($saved[$key])) {
                $answers[$key] = $saved[$key];
            }
        }

        return $answers;
    }

    public function payload(ServiceRequest $sr, ?ServiceRequestAsset $row, string $slot = 'main'): array
    {
        if (isset(self::SLOTS[$slot])) {
            return (array) $sr->{self::SLOTS[$slot][1]};
        }

        return (array) ($row ? $row->asset_checklist_payload : $sr->sr_checklist_payload);
    }

    /**
     * What stops punch-out for the checklist and the batch (D11, D14). One message for each problem.
     *
     * @return string[]
     */
    public function blockers(ServiceRequest $sr): array
    {
        $rules  = is_array($sr->proof_rules_snapshot) ? $sr->proof_rules_snapshot : [];
        $tenant = Tenant::find($sr->tenant_id);
        $on     = app(FormTemplates::class)->enabled($tenant);   // a lost plan pauses the rule, never blocks work
        $rows   = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();
        $out    = [];

        if ($rows->count() > 0) {
            $pending = $rows->where('inspection_status', 'pending')->count();
            if ($pending > 0) {
                $out[] = "{$pending} assets are still pending. Mark each one before you finish.";
            }
            if ($on && ! empty($rules['require_per_asset_checklist'])) {
                $n = 0;
                $open = false;
                foreach ($rows as $r) {
                    if ($r->inspection_status === 'skipped' || ! $r->form_template_version_id || $r->checklist_result) {
                        continue;
                    }
                    $open = true;
                    $n += $this->missingCount($r->version, (array) $r->asset_checklist_payload);
                }
                if ($open) {
                    $out[] = "Complete the checklist before you finish: {$n} required answers are missing.";
                }
            }

            return $out;
        }

        if ($on && ! empty($rules['require_checklist']) && $sr->form_template_version_id && ! $sr->checklist_result) {
            $version = FormTemplateVersion::withoutGlobalScopes()->find($sr->form_template_version_id);
            $n = $this->missingCount($version, (array) $sr->sr_checklist_payload);
            $out[] = "Complete the checklist before you finish: {$n} required answers are missing.";
        }

        return $out;
    }

    public function missingCount(?FormTemplateVersion $version, array $answers): int
    {
        return $version ? FormSchema::evaluate($version->schema_json ?? [], $answers)['missing'] : 0;
    }

    private function state(FormTemplateVersion $version, array $answers, bool $applied): array
    {
        $e = FormSchema::evaluate($version->schema_json ?? [], $answers);

        return ['result' => $e['result'], 'complete' => $e['complete'], 'missing' => $e['missing'], 'applied' => $applied];
    }

    private function log(ServiceRequest $sr, User $user, string $uuid, ?Carbon $deviceAt, string $result, array $payload, ?string $conflict = null): void
    {
        FieldEventLog::withoutGlobalScopes()->create([
            'tenant_id' => $sr->tenant_id, 'user_id' => $user->id, 'client_event_uuid' => $uuid, 'event_type' => self::EVENT,
            'service_request_id' => $sr->id, 'recorded_at_device' => $deviceAt ?? now(), 'synced_to_server_at' => now(),
            'result' => $result, 'conflict_code' => $conflict, 'payload_hash' => hash('sha256', json_encode($payload)),
            'payload_json' => $payload,
        ]);
    }
}
