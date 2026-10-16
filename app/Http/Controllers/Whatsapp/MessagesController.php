<?php

namespace App\Http\Controllers\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\EvolutionApiClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class MessagesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        $query = WhatsappInstance::withoutGlobalScopes()->where('status', 'active');
        if (! $canSeeAllCompanies) {
            $searchVals = [];
            foreach ($accessibleCompanyIds as $id) {
                $searchVals[] = (string) $id;
                try {
                    $searchVals[] = new \MongoDB\BSON\ObjectId((string) $id);
                } catch (\Throwable $e) {}
            }
            $query->whereIn('company_id', array_values(array_unique($searchVals)));
        }

        $instances = $query
            ->with('company')
            ->orderBy('instance_name')
            ->get()
            ->map(fn (WhatsappInstance $row) => [
                'id' => (string) $row->getKey(),
                'instance_name' => (string) $row->instance_name,
                'company_name' => $row->company?->name ?? '',
            ])
            ->values();

        return Inertia::render('Whatsapp/Send/Index', [
            'instances' => $instances,
            'canSeeAllCompanies' => $canSeeAllCompanies,
        ]);
    }

    public function store(Request $request, EvolutionApiClient $evolution)
    {
        $validated = $request->validate([
            'instance_name' => 'required|string|max:100',
            'phone' => 'required|string|max:32',
            'text' => 'required|string|max:4000',
        ]);

        $user = $request->user();
        $canSeeAllCompanies = $user ? $user->canSelectAllCompanies() : false;
        $accessibleCompanyIds = $user ? $user->getAccessibleCompanyIds() : [];

        $row = WhatsappInstance::withoutGlobalScopes()
            ->where('instance_name', $validated['instance_name'])
            ->first();

        if (! $row) {
            return back()->withInput()->with('flash', [
                'type' => 'error',
                'message' => 'La instancia no existe en Mongo.',
            ]);
        }

        if (! $canSeeAllCompanies && ! in_array((string) $row->company_id, $accessibleCompanyIds, true)) {
            return back()->withInput()->with('flash', [
                'type' => 'error',
                'message' => 'No tienes permiso para enviar mensajes desde esta instancia.',
            ]);
        }

        try {
            $evolution->sendText(
                $row->evolutionName(),
                $validated['phone'],
                $validated['text'],
            );
        } catch (Throwable $e) {
            return back()->withInput()->with('flash', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        return redirect()->route('whatsapp.send')->with('flash', [
            'type' => 'success',
            'message' => 'Mensaje enviado.',
        ]);
    }
}
