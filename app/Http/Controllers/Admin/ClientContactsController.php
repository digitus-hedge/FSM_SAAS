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
        $data = $this->validated($request, $client);

        $contact = DB::transaction(function () use ($data, $client) {
            if (!empty($data['is_primary'])) {
                // Only one primary per client
                $client->contacts()->update(['is_primary' => false]);
            }
            return $client->contacts()->create($data);
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
        $data = $this->validated($request, $client, $contact->id);

        DB::transaction(function () use ($data, $client, $contact) {
            if (!empty($data['is_primary'])) {
                $client->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
            }
            $contact->update($data);
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

    protected function validated(Request $request, Client $client, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'            => 'required|string|max:150',
            'email'           => 'nullable|email|max:190',
            'phone'           => 'nullable|string|max:40',
            'whatsapp_number' => 'nullable|string|max:40',
            'is_primary'      => 'sometimes|boolean',
        ]);
    }
}