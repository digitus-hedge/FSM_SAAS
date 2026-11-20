<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Services\Feedback\FeedbackLinks;
use Illuminate\Http\Request;

/**
 * The client's feedback page, opened from the secure link in the completion message (blueprint 7.7b): no sign-in, one answer, valid for feedback_link_days.
 * The link works on its own company's address and on the root address, and is a 404 on another company's address (like /i/ and /q/).
 * A used or expired link shows a clear page. The routes are rate limited (see routes/web.php).
 */
class PublicFeedbackController extends Controller
{
    public function __construct(private FeedbackLinks $feedback)
    {
    }

    public function show(string $token)
    {
        [$link, $sr] = $this->resolve($token);

        $state = $this->feedback->state($link);
        if ($state !== 'live') {
            return $this->closed($sr, $state);
        }

        return view('public.feedback', ['sr' => $sr, 'company' => Tenant::find($sr->tenant_id), 'token' => $token]);
    }

    public function store(Request $request, string $token)
    {
        [$link, $sr] = $this->resolve($token);

        $state = $this->feedback->state($link);
        if ($state !== 'live') {
            return $this->closed($sr, $state);
        }

        $data = $request->validate([
            'stars'   => ['required', 'integer', 'between:1,5'],
            'score'   => ['required', 'integer', 'between:0,10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'stars.required' => 'Please choose a rating from 1 to 5 stars.',
            'stars.between'  => 'Please choose a rating from 1 to 5 stars.',
            'score.required' => 'Please choose a score from 0 to 10.',
            'score.between'  => 'Please choose a score from 0 to 10.',
        ]);

        if (! $this->feedback->answer($link, (int) $data['stars'], (int) $data['score'], $data['comment'] ?? null, (string) $request->ip())) {
            return $this->closed($sr, $this->feedback->state($link->fresh()));      // used or expired while the form was open
        }

        return view('public.feedback-closed', ['state' => 'thanks', 'sr' => $sr, 'company' => Tenant::find($sr->tenant_id)]);
    }

    /** @return array{0:\App\Models\SecureLink,1:ServiceRequest} */
    private function resolve(string $token): array
    {
        $link = $this->feedback_links()->lookup($token, FeedbackLinks::PURPOSE);
        abort_unless($link && $link->entity_type === FeedbackLinks::ENTITY, 404);

        $current = Tenant::current();
        abort_if($current && (int) $current->id !== (int) $link->tenant_id, 404);          // another company's link

        $sr = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $link->tenant_id)->find($link->entity_id);
        abort_unless($sr, 404);

        return [$link, $sr];
    }

    private function feedback_links(): \App\Services\Auth\SecureLinkService
    {
        return app(\App\Services\Auth\SecureLinkService::class);
    }

    private function closed(ServiceRequest $sr, string $state)
    {
        return response()->view('public.feedback-closed', ['state' => $state, 'sr' => $sr, 'company' => Tenant::find($sr->tenant_id)], 410);
    }
}
