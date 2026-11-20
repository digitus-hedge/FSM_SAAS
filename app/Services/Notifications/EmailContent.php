<?php

namespace App\Services\Notifications;

use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Support\TenantUrl;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * The words and the details table of every email that replaces a WhatsApp message (blueprint 9.2; WhatsApp is paused).
 * One place builds the data for the one shared layout (`emails.base` and `emails.base-text`):
 *   company (name, logo, contact), heading, one intro line, a details table, one main button, an optional code and note.
 *
 * Everything here works from plain arrays (a "context"), so the local preview page can show every email with sample data
 * and the Mailables only have to build a context from a record.
 *
 * Context keys: company, labels (request, client, site, technician), number, title, client, site, when, whenLabel, technician,
 * previous, reason, asset, stage, url, name, extra (label => value rows to add).
 */
class EmailContent
{
    /** Events a client contact gets (ClientEventMail). */
    public const CLIENT_EVENTS = [
        'sr.created', 'sr.approved', 'sr.cancelled', 'sr.assigned', 'sr.eta_changed', 'tech.arrived',
        'depot.received', 'depot.ready_for_return',
    ];

    /** The company block of the layout. Reads the company's own name, logo and contact details. */
    public static function company(Tenant|int|null $tenant): array
    {
        $t = $tenant instanceof Tenant ? $tenant : ($tenant ? Tenant::find($tenant) : null);
        if (! $t) {
            return ['name' => config('app.name'), 'logo' => null, 'email' => null, 'phone' => null, 'color' => null, 'timezone' => config('app.timezone')] + self::brand(null);
        }

        $logo = null;
        if ($t->logo_path) {
            try {
                if (Storage::disk('public')->exists($t->logo_path)) {
                    $logo = TenantUrl::for($t, 'storage/' . ltrim($t->logo_path, '/'));
                }
            } catch (\Throwable $e) {
                $logo = null;
            }
        }

        return [
            'name' => $t->name, 'logo' => $logo, 'email' => $t->contact_email, 'phone' => $t->contact_phone,
            'color' => $t->primary_color, 'timezone' => $t->timezone ?: config('app.timezone'), ...self::brand($t),
            'id' => $t->id, 'portal' => in_array($t->plan?->code, ['professional', 'enterprise'], true),
        ];
    }

    /**
     * The company's own colour and font, for emails and for the public pages a client opens from an email (the same choice as Branding).
     * No colour saved, or only an old code default (yellow, pink, blue): the theme's Sage. The ink is dark or white, whichever reads on the colour.
     * Returns accent, ink, soft (a pale tint), font_body, font_display and font_param (the Google Fonts address part).
     */
    public static function brand(Tenant|int|null $tenant): array
    {
        $t      = $tenant instanceof Tenant ? $tenant : ($tenant ? Tenant::find($tenant) : null);
        $color  = (string) ($t?->primary_color ?? '');
        $legacy = ['#FFC61A', '#E22A6B', '#E11D5C', '#2F5BEA'];
        $accent = preg_match('/^#[0-9A-Fa-f]{6}$/', $color) && ! in_array(strtoupper($color), $legacy, true) ? $color : '#5E8A3C';

        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($accent, '#'), 2));
        $lum  = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        $soft = sprintf('#%02X%02X%02X', (int) round($r * .12 + 255 * .88), (int) round($g * .12 + 255 * .88), (int) round($b * .12 + 255 * .88));

        $font = \App\Models\BrandFont::where('key', $t?->font_key ?: 'dm_sans')->first();
        $safe = fn ($f) => preg_replace('/[^A-Za-z0-9 ,\'"\-]/', '', (string) $f);   // only safe characters in a CSS font list

        return [
            'accent' => $accent, 'ink' => $lum > 0.55 ? '#1B1A24' : '#FFFFFF', 'soft' => $soft,
            'font_body' => $safe($font->body_family ?? "'DM Sans'"), 'font_display' => $safe($font->display_family ?? "'Figtree'"),
            'font_param' => $font->google_param ?? 'DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800',
        ];
    }

    /** The company's own labels (service request, client, site, technician), whatever the current company is. */
    public static function labels(Tenant|int|null $tenant): array
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $previous = Tenant::current();
        try {
            if ($id && $previous?->id !== $id) {
                Tenant::setCurrent($tenant instanceof Tenant ? $tenant : Tenant::find($id));
            }

            return [
                'request' => ucfirst(term('service_request')), 'client' => ucfirst(term('client')),
                'site' => ucfirst(term('site')), 'technician' => ucfirst(term('technician')),
            ];
        } finally {
            Tenant::setCurrent($previous);
        }
    }

    /** A context from a real request. */
    public static function contextFor(ServiceRequest $sr, array $over = []): array
    {
        $company = self::company((int) $sr->tenant_id);
        $tz      = $company['timezone'];
        $local   = fn ($d) => $d ? Carbon::parse($d)->copy()->setTimezone($tz)->format('D j M Y, H:i') : null;
        $home    = $sr->home_site_id ? \App\Models\ProjectSite::withoutGlobalScopes()->find($sr->home_site_id) : null;

        return array_merge([
            'company' => $company, 'labels' => self::labels((int) $sr->tenant_id),
            'number' => $sr->sr_number, 'title' => $sr->title,
            'client' => $sr->client?->name, 'site' => $home?->name ?? $sr->site?->name,
            'when' => $local($sr->scheduled_eta_at ?? $sr->planned_at), 'whenLabel' => 'Date and time',
            'technician' => $sr->assignedTech?->name,
            'asset' => $sr->primaryAsset?->name, 'stage' => $sr->depot_stage ? ucfirst(str_replace('_', ' ', $sr->depot_stage)) : null,
            'received' => $local($sr->created_at), 'reason' => $sr->cancellation_reason,
            'url' => null, 'name' => null, 'extra' => [],
        ], $over);
    }

    /* ---------- the layout data ---------- */

    public static function frame(array $company, string $heading, ?string $intro, array $rows = [], ?array $button = null, ?string $note = null, ?string $code = null): array
    {
        return [
            'company' => $company, 'heading' => $heading, 'intro' => $intro,
            'rows' => array_filter($rows, fn ($v) => $v !== null && $v !== ''), 'button' => $button, 'note' => $note, 'code' => $code,
        ];
    }

    /** Request number, client, site, date and time, technician (only the ones the message needs). */
    public static function rows(array $c, array $keys): array
    {
        $l = $c['labels'];
        $all = [
            'number'     => [$l['request'] . ' number', $c['number'] ?? null],
            'title'      => ['Subject', $c['title'] ?? null],
            'client'     => [$l['client'], $c['client'] ?? null],
            'site'       => [$l['site'], $c['site'] ?? null],
            'when'       => [$c['whenLabel'] ?? 'Date and time', $c['when'] ?? null],
            'technician' => [$l['technician'], $c['technician'] ?? null],
            'asset'      => ['Equipment', $c['asset'] ?? null],
            'stage'      => ['Stage', $c['stage'] ?? null],
            'reason'     => ['Reason', $c['reason'] ?? null],
            'previous'   => ['Earlier time', $c['previous'] ?? null],
            'received'   => ['Received', $c['received'] ?? null],
        ];
        $rows = [];
        foreach ($keys as $k) {
            $rows[$all[$k][0]] = $all[$k][1];
        }

        return $rows + ($c['extra'] ?? []);
    }

    /** The one button of a client message: the portal where the company has one, else a way to write to the company. */
    private static function clientButton(array $c): ?array
    {
        if (! empty($c['url'])) {
            return ['label' => 'View your ' . strtolower($c['labels']['request']), 'url' => $c['url']];
        }
        $email = $c['company']['email'] ?? null;

        return $email ? ['label' => 'Contact ' . $c['company']['name'], 'url' => 'mailto:' . $email] : null;
    }

    /* ---------- client contact messages ---------- */

    /** The email a client contact gets for one event. Never carries internal fields (blueprint 7.7b). */
    public static function client(string $event, array $c): array
    {
        $r   = strtolower($c['labels']['request']);
        $hi  = 'Hello' . (filled($c['name'] ?? null) ? ' ' . $c['name'] : '') . ', ';
        $num = $c['number'] ?? '';
        $co  = $c['company'];
        $btn = self::clientButton($c);

        [$heading, $intro, $keys, $note] = match ($event) {
            'sr.created' => ["We received your {$r} {$num}", $hi . "we have logged your {$r}. We will confirm it as soon as it is reviewed.", ['number', 'title', 'client', 'site', 'received'], null],
            'sr.approved' => ["Your {$r} {$num} is approved", $hi . "your {$r} is approved. We will tell you when a technician is assigned.", ['number', 'title', 'client', 'site'], null],
            'sr.cancelled' => ["Your {$r} {$num} was cancelled", $hi . "your {$r} was cancelled.", ['number', 'title', 'client', 'site', 'reason'], 'If this is not what you expected, please contact us.'],
            'sr.assigned' => ["A technician is assigned to {$num}", $hi . strtolower($c['labels']['technician']) . ' ' . ($c['technician'] ?? 'our team') . ' will visit you.', ['number', 'client', 'site', 'when', 'technician'], null],
            'sr.eta_changed' => ["Your visit for {$num} has a new time", $hi . 'the time of your visit has changed.', ['number', 'client', 'site', 'previous', 'when', 'technician', 'reason'], 'Sorry for any inconvenience. If this time does not suit you, please contact us.'],
            'tech.arrived' => ["Our technician has arrived for {$num}", $hi . strtolower($c['labels']['technician']) . ' ' . ($c['technician'] ?? '') . ' has arrived and the work is starting.', ['number', 'client', 'site', 'when', 'technician'], null],
            'depot.received' => ["We received your equipment for {$num}", $hi . 'your equipment is at our workshop.', ['number', 'client', 'site', 'asset', 'stage', 'when'], null],
            'depot.ready_for_return' => ["Your equipment for {$num} is ready to collect", $hi . 'the work is done and your equipment can go back.', ['number', 'client', 'site', 'asset', 'stage', 'when'], null],
        };

        return self::frame($co, $heading, $intro, self::rows($c, $keys), $btn, $note);
    }

    /* ---------- staff and field messages (through NotificationService) ---------- */

    /**
     * The email behind an in-app notification: its title and text from config/notifications.php, plus the request's details when the
     * message is about a request. The button opens the exact page (company address).
     */
    public static function staff(string $event, string $title, ?string $body, array $c, ?string $buttonUrl, ?string $buttonLabel = null): array
    {
        $rows = ! empty($c['number']) ? self::rows($c, ['number', 'client', 'site', 'when', 'technician']) : [];
        $lines = $body !== null ? array_values(array_filter(array_map('trim', explode("\n", $body)), 'strlen')) : [];
        // a summary has many lines: the first is the intro, the rest are details
        $intro = $lines[0] ?? null;
        foreach (array_slice($lines, 1) as $i => $line) {
            $rows[$i === 0 && count($lines) === 2 ? 'Details' : 'Detail ' . ($i + 1)] = $line;
        }
        $button = $buttonUrl ? ['label' => $buttonLabel ?: (config('notifications.events')[$event]['button'] ?? 'Open'), 'url' => $buttonUrl] : null;

        return self::frame($c['company'], $title, $intro, $rows, $button, null);
    }

    /* ---------- codes ---------- */

    /** A login or signature code, sent as a code (never as a link). */
    public static function code(string $kind, array $c, string $code, int $minutes = 10, int $tries = 3): array
    {
        $r = strtolower($c['labels']['request'] ?? 'service request');
        if ($kind === 'signature') {
            return self::frame($c['company'], 'Your signature code',
                "Hello" . (filled($c['name'] ?? null) ? ' ' . $c['name'] : '') . ', give this code to the technician to confirm the work on ' . ($c['number'] ?? "your {$r}") . '.',
                self::rows($c, ['number', 'client', 'site', 'technician']), null,
                "The code is valid for {$minutes} minutes and can be tried {$tries} times. If you did not expect it, ignore this email.", $code);
        }

        return self::frame($c['company'], 'Your sign-in code',
            'Use this code to sign in to ' . $c['company']['name'] . '.', [], null,
            "The code is valid for {$minutes} minutes and can be tried {$tries} times. If you did not try to sign in, ignore this email.", $code);
    }
}
