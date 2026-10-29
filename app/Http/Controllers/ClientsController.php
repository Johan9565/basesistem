<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    /**
     * Registra una nueva Cuenta Principal (Cliente SaaS / Corporativo).
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'billing_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'plan' => 'nullable|string|in:basic,pro,enterprise',
            'max_companies' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:active,suspended,cancelled',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['plan'] = $validated['plan'] ?? 'pro';
        $validated['max_companies'] = isset($validated['max_companies']) ? (int) $validated['max_companies'] : 10;

        $client = Client::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Cuenta principal '{$client->name}' registrada exitosamente.",
                'client' => [
                    'id' => (string) $client->_id,
                    'name' => $client->name,
                    'document_number' => $client->document_number ?? '',
                    'billing_email' => $client->billing_email ?? '',
                    'phone' => $client->phone ?? '',
                    'plan' => $client->plan,
                    'max_companies' => $client->max_companies,
                    'status' => $client->status,
                ],
            ]);
        }

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Cuenta principal '{$client->name}' creada con éxito.",
            'new_client_id' => (string) $client->_id,
        ]);
    }

    /**
     * Actualiza una Cuenta Principal existente.
     */
    public function update(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:50',
            'billing_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'plan' => 'nullable|string|in:basic,pro,enterprise',
            'max_companies' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:active,suspended,cancelled',
        ]);

        $client->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Cuenta principal '{$client->name}' actualizada con éxito.",
            ]);
        }

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Cuenta principal '{$client->name}' actualizada con éxito.",
        ]);
    }

    /**
     * Elimina una Cuenta Principal siempre que no tenga empresas asociadas.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $companiesCount = $client->companies()->count();

        if ($companiesCount > 0) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "No es posible eliminar la cuenta '{$client->name}' porque tiene {$companiesCount} empresa(s) asociada(s). Reasigna o elimina las empresas antes de proceder.",
            ]);
        }

        $clientName = $client->name;
        $client->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Cuenta principal '{$clientName}' eliminada exitosamente.",
        ]);
    }
}

