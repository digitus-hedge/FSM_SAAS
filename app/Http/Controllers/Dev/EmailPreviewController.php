<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Mail\ClientEventMail;
use App\Mail\FeedbackRequestMail;
use App\Mail\InvoiceOverdueMail;
use App\Mail\InvoiceSentMail;
use App\Mail\NotificationMail;
use App\Mail\OneTimeCodeMail;
use App\Mail\QuoteSentMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceToken;
use App\Models\ProjectSite;
use App\Models\Quote;
use App\Models\QuoteToken;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Mail\Mailable;

/**
 * Local-only page that lists every email that replaces a WhatsApp message, with sample data, so the layout and wording can be looked at.
 * It sends nothing and writes nothing. The route exists only in the local environment, and every method refuses to run anywhere else
 * (production included) with a 404.
 */
class EmailPreviewController extends Controller
{
    private function guard(): void
    {
        abort_unless(app()->environment('local') && ! app()->isProduction(), 404);
    }

    public function index()
    {
        $this->guard();

        $groups = [];
        foreach ($this->catalogue() as $key => $row) {
            $groups[$row['group']][$key] = $row['label'];
        }

        return view('dev.emails', ['groups' => $groups, 'key' => null]);
    }

    public function show(string $key)
    {
        $this->guard();
        $row = $this->catalogue()[$key] ?? abort(404);
        $m   = $this->frameOf($row['build']());

        return view('dev.emails', [
            'groups' => null, 'key' => $key, 'label' => $row['label'], 'frame' => $m,
            'html' => view('emails.base', ['m' => $m])->render(),
            'text' => view('emails.base-text', ['m' => $m])->render(),
        ]);
    }

    /** The raw HTML of one email, for the preview frame. */
    public function html(string $key)
    {
        $this->guard();
        $row = $this->catalogue()[$key] ?? abort(404);

        return response(view('emails.base', ['m' => $this->frameOf($row['build']())])->render());
    }

    private function frameOf(Mailable $mail): array
    {
        return method_exists($mail, 'frame') ? $mail->frame() : $mail->content()->with['m'];
    }

    /* ---------- sample data (nothing is saved) ---------- */

    private function sample(): array
    {
        // a real company's name, logo and contact details when the database has one, so the preview shows real branding
        $tenant = Tenant::query()->orderBy('id')->first();
        $tid    = $tenant?->id;

        $client = (new Client())->forceFill(['id' => 0, 'tenant_id' => $tid, 'name' => 'Greenfield Hospital']);
        $site   = (new ProjectSite())->forceFill(['id' => 0, 'tenant_id' => $tid, 'name' => 'Main Building, Floor 2']);
        $tech   = (new User())->forceFill(['id' => 0, 'tenant_id' => $tid, 'name' => 'Ravi Kumar']);

        $sr = (new ServiceRequest())->forceFill([
            'id' => 0, 'tenant_id' => $tid, 'sr_number' => 'SR-2026-00042', 'title' => 'Air conditioner not cooling',
            'scheduled_eta_at' => now()->addDay()->setTime(10, 30), 'first_punch_in_at' => now(), 'created_at' => now()->subHours(2),
            'cancellation_reason' => 'The client no longer needs the visit.', 'depot_stage' => null, 'qc_rework_count' => 1,
        ]);
        $sr->setRelation('client', $client)->setRelation('site', $site)->setRelation('assignedTech', $tech)->setRelation('primaryAsset', null);

        return compact('tenant', 'tid', 'client', 'site', 'tech', 'sr');
    }

    /** @return array<string, array{group:string,label:string,build:\Closure}> */
    private function catalogue(): array
    {
        $s = $this->sample();
        $sr = $s['sr'];
        $out = [];

        // client contact: the WhatsApp events, now email
        $client = [
            'sr.created' => 'Request received', 'sr.approved' => 'Request approved', 'sr.cancelled' => 'Request cancelled',
            'sr.assigned' => 'Technician assigned (name and ETA)', 'sr.eta_changed' => 'Visit time changed', 'tech.arrived' => 'Technician arrived',
            'depot.received' => 'Depot: equipment received', 'depot.ready_for_return' => 'Depot: ready to collect',
        ];
        foreach ($client as $event => $label) {
            $out[$event] = ['group' => 'Client contact', 'label' => "{$event}: {$label}", 'build' => function () use ($event, $sr) {
                $copy = clone $sr;
                $copy->setRelation('client', $sr->client)->setRelation('site', $sr->site)->setRelation('assignedTech', $sr->assignedTech);
                if (str_starts_with($event, 'depot.')) {
                    $copy->forceFill(['depot_stage' => $event === 'depot.received' ? 'received' : 'ready_for_return']);
                    $copy->setRelation('primaryAsset', (new \App\Models\Asset())->forceFill(['name' => 'Diesel generator 20 kVA']));
                }

                return new ClientEventMail($copy, $event, 'Anita', $event === 'sr.eta_changed' ? ['previous' => now()->addDay()->setTime(9, 0)->toIso8601String(), 'reason' => 'The technician is on another job.'] : []);
            }];
        }

        // client contact: messages that were already email (now in the shared layout)
        $out['sr.completed'] = ['group' => 'Client contact', 'label' => 'sr.completed: feedback link', 'build' => fn () => new FeedbackRequestMail($sr, 'Anita', url('/feedback/sample-token'), now()->addDays(7))];

        $tid = $s['tid'];
        $inv = (new Invoice())->forceFill(['id' => 0, 'tenant_id' => $tid, 'invoice_number' => 'INV-2026-0107', 'currency_code' => 'INR', 'total' => 18500, 'balance' => 18500, 'due_date' => now()->subDays(8)]);
        $inv->setRelation('client', $s['client'])->setRelation('serviceRequest', $sr);
        $invToken = (new InvoiceToken())->forceFill(['token' => 'sample-token']);
        $invToken->setRelation('invoice', $inv);
        $quote = (new Quote())->forceFill(['id' => 0, 'tenant_id' => $tid, 'quote_number' => 'QT-2026-0031', 'currency_code' => 'INR', 'total' => 9200, 'valid_until' => now()->addDays(14)]);
        $quote->setRelation('client', $s['client'])->setRelation('serviceRequest', $sr);
        $quoteToken = (new QuoteToken())->forceFill(['token' => 'sample-token']);
        $quoteToken->setRelation('quote', $quote);

        $out['quote.sent']      = ['group' => 'Client contact', 'label' => 'quote.sent: quote with secure link', 'build' => fn () => new QuoteSentMail($quoteToken)];
        $out['invoice.sent']    = ['group' => 'Client contact', 'label' => 'invoice.sent: invoice with secure link', 'build' => fn () => new InvoiceSentMail($invToken)];
        $out['invoice.overdue'] = ['group' => 'Client contact', 'label' => 'invoice.overdue: reminder', 'build' => fn () => new InvoiceOverdueMail($inv, $invToken)];

        // codes
        $request = ['number' => 'SR-2026-00042', 'client' => 'Greenfield Hospital', 'site' => 'Main Building, Floor 2', 'technician' => 'Ravi Kumar'];
        $out['signature.code'] = ['group' => 'Codes', 'label' => 'signature.code: to the chosen project contact', 'build' => fn () => new OneTimeCodeMail('signature', '482913', $s['tenant'], 'Anita', $request)];
        $out['login.code']     = ['group' => 'Codes', 'label' => 'Field login code', 'build' => fn () => new OneTimeCodeMail('login', '715204', $s['tenant'])];

        // staff and field: every notification event that sends email, built from config/notifications.php with sample values
        $sampleVars = [
            'sr_number' => 'SR-2026-00042', 'client' => 'Greenfield Hospital', 'site' => 'Main Building, Floor 2', 'when' => 'Due 21 Nov 2026 10:30.', 'reason' => 'Reason given by the user.',
            'headline' => 'SR-2026-00042 was reassigned to you', 'detail' => 'Due 21 Nov 2026 10:30.', 'decision' => 'approved', 'note' => 'It is valid for 2 hours.',
            'date' => '21 Nov 2026', 'summary' => "3 requests are open.\n1 is overdue.\n2 are due today.", 'kind' => 'response', 'percent' => '80', 'due' => '21 Nov 2026 10:30',
            'technician' => 'Ravi Kumar', 'asset' => 'Diesel generator 20 kVA', 'what' => 'Diesel generator 20 kVA', 'note ' => '',
        ];
        foreach ((config('notifications.events') ?? []) as $event => $def) {
            if (! ($def['email'] ?? false)) {
                continue;
            }
            $out[$event] = ['group' => 'Staff and field', 'label' => $event, 'build' => function () use ($event, $def, $sampleVars, $s) {
                $fill = function (string $text) use ($sampleVars) {
                    $text = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $sampleVars[$m[1]] ?? ('[' . $m[1] . ']'), $text);

                    return $text;
                };
                $n = (new UserNotification())->forceFill([
                    'tenant_id' => $s['tid'], 'event_key' => $event, 'title' => $fill($def['title']), 'body' => $fill($def['body'] ?? '') ?: null,
                    'url' => '/service-requests/1', 'data' => null,
                ]);

                return new NotificationMail($n);
            }];
        }

        return $out;
    }
}
