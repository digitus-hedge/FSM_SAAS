<?php
namespace App\Http\Controllers;

use App\Models\{Quote, QuoteToken, QuoteEvent};
use Illuminate\Http\Request;

class PublicQuoteController extends Controller
{
    private function resolve(string $token): array {
        $t = QuoteToken::where('token', $token)->firstOrFail();
        $t->increment('viewed_count');
        if (! $t->viewed_first_at) $t->update(['viewed_first_at' => now()]);
        $t->quote->load(['items.taxRate','serviceRequest.site','client']);
        return [$t->quote, $t];
    }

    public function show(string $token) {
        [$quote, $t] = $this->resolve($token);
        return view('public.quote', compact('quote', 't'));
    }

    public function pdf(string $token) {
        [$quote, $t] = $this->resolve($token);
        return view('admin.quotes.pdf', compact('quote'));
    }

    public function accept(Request $request, string $token) {
        [$quote, $t] = $this->resolve($token);

        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'confirmed' => 'required|accepted',
        ], [
            'name.required'      => 'Please enter your full name.',
            'confirmed.accepted' => 'Tick the box to confirm you are allowed to accept this quote.',
        ]);

        if (! in_array($quote->status, ['sent','accepted'])) {
            return back()->with('error', 'This quote cannot be accepted anymore.');
        }

        $quote->update([
            'status'              => 'accepted',
            'responded_at'        => now(),
            'responded_by_name'   => $data['name'],
            'responded_ip'        => $request->ip(),
        ]);
        $quote->serviceRequest->update(['billing_status' => 'awaiting_invoice','status'=>'awaiting_invoice']);

        QuoteEvent::create([
            'tenant_id'   => $quote->tenant_id,
            'quote_id'    => $quote->id,
            'user_id'     => null,
            'type'        => 'accepted',
            'label'       => 'Accepted by '.$data['name'],
            'occurred_at' => now(),
        ]);

        return redirect()->route('public.quote.show', $token)->with('accepted', 1);
    }

    public function reject(Request $request, string $token) {
        [$quote, $t] = $this->resolve($token);

        $data = $request->validate([
            'name'   => 'required|string|max:150',
            'reason' => 'nullable|string|max:500',
        ]);

        $quote->update([
            'status'              => 'rejected',
            'responded_at'        => now(),
            'responded_by_name'   => $data['name'],
            'responded_ip'        => $request->ip(),
            'rejection_reason'    => $data['reason'] ?? null,
        ]);

        QuoteEvent::create([
            'tenant_id'   => $quote->tenant_id,
            'quote_id'    => $quote->id,
            'user_id'     => null,
            'type'        => 'rejected',
            'label'       => 'Rejected by '.$data['name'],
            'occurred_at' => now(),
        ]);

        return redirect()->route('public.quote.show', $token)->with('rejected', 1);
    }
}