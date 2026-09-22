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
    public function index()
    {
        $instances = WhatsappInstance::query()
            ->where('status', 'active')
            ->orderBy('instance_name')
            ->get()
            ->map(fn (WhatsappInstance $row) => [
                'id' => (string) $row->getKey(),
                'instance_name' => (string) $row->instance_name,
            ])
            ->values();

        return Inertia::render('Whatsapp/Send/Index', [
            'instances' => $instances,
        ]);
    }

    public function store(Request $request, EvolutionApiClient $evolution)
    {
        $validated = $request->validate([
            'instance_name' => 'required|string|max:100',
            'phone' => 'required|string|max:32',
            'text' => 'required|string|max:4000',
        ]);

        $exists = WhatsappInstance::query()
            ->where('instance_name', $validated['instance_name'])
            ->exists();

        if (! $exists) {
            return back()->withInput()->with('flash', [
                'type' => 'error',
                'message' => 'La instancia no existe en Mongo.',
            ]);
        }

        try {
            $evolution->sendText(
                $validated['instance_name'],
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
