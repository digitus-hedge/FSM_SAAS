<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientContactsController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $this->validated($request);

        $contact = DB::transaction(function () use ($data, $client, $request) {
            $contact = new ClientContact([
                'client_id'       => $client->id,
                'tenant_id'       => $client->tenant_id,
                'name'            => $data['name'],
                'email'           => $data['email']           ?? null,
                'phone'           => $data['phone']           ?? null,
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'is_primary'      => (bool) ($data['is_primary'] ?? false),
                'is_active'       => true,
                'site_scope_json' => [
                    'mode'     => $data['site_access_mode'] ?? 'all',
                    'site_ids' => $data['site_ids']         ?? [],
                ],
            ]);

            // WhatsApp consent → today's date
            if (!empty($data['whatsapp_consent'])) {
                $contact->whatsapp_opt_in_at  = now();
                $contact->whatsapp_opt_out_at = null;
            }

            // Only one primary per client
            if ($contact->is_primary) {
                $client->contacts()->update(['is_primary' => false]);
            }

            $contact->save();
            return $contact;
        });

        return response()->json([
            'ok'      => true,
            'message' => "Contact {$contact->name} added.",
            'contact' => $contact,
        ]);
    }

    public function update(Request $request, Client $client, ClientContact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $client, $contact) {
            $contact->fill([
                'name'            => $data['name'],
                'email'           => $data['email']           ?? null,
                'phone'           => $data['phone']           ?? null,
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'is_primary'      => (bool) ($data['is_primary'] ?? false),
                'site_scope_json' => [
                    'mode'     => $data['site_access_mode'] ?? 'all',
                    'site_ids' => $data['site_ids']         ?? [],
                ],
            ]);

            // Toggle opt-in / opt-out
            $consent = (bool) ($data['whatsapp_consent'] ?? false);
            if ($consent && !$contact->whatsapp_opt_in_at) {
                $contact->whatsapp_opt_in_at  = now();
                $contact->whatsapp_opt_out_at = null;
            } elseif (!$consent && $contact->whatsapp_opt_in_at && !$contact->whatsapp_opt_out_at) {
                $contact->whatsapp_opt_out_at = now();
            }

            if ($contact->is_primary) {
                $client->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
            }

            $contact->save();
        });

        return response()->json([
            'ok'      => true,
            'message' => "Contact {$contact->name} updated.",
            'contact' => $contact->fresh(),
        ]);
    }

    public function destroy(Client $client, ClientContact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);
        $name = $contact->name;
        $contact->delete();

        return response()->json([
            'ok'      => true,
            'message' => "Contact {$name} removed.",
        ]);
    }

    public function setActive(Request $request, Client $client, ClientContact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);
        $contact->update(['is_active' => $request->boolean('is_active')]);
        return response()->json([
            'ok'      => true,
            'message' => $contact->is_active ? 'Contact reactivated' : 'Contact deactivated',
        ]);
    }

    public function makePrimary(Client $client, ClientContact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);

        DB::transaction(function () use ($client, $contact) {
            $client->contacts()->update(['is_primary' => false]);
            $contact->update(['is_primary' => true]);
        });

        return response()->json([
            'ok'      => true,
            'message' => 'Primary contact updated',
        ]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'              => 'required|string|max:150',
            'email'             => 'nullable|email|max:190',
            'phone'             => 'nullable|string|max:40',
            'whatsapp_number'   => 'nullable|string|max:40',
            'whatsapp_consent'  => 'sometimes|boolean',
            'site_access_mode'  => 'sometimes|in:all,selected',
            'site_ids'          => 'sometimes|array',
            'site_ids.*'        => 'integer',
            'is_primary'        => 'sometimes|boolean',
        ]);
    }
}