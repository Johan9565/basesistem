<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicesController extends Controller
{
    /**
     * Catálogo de servicios de la empresa activa.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $query = Service::query()->orderBy('name', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $services = $query->paginate(15)->withQueryString();

        $staffUsers = User::where('status', 1)
            ->get(['_id', 'name', 'ape_pat', 'email'])
            ->map(fn($u) => [
                'id'   => (string) $u->_id,
                'name' => trim("{$u->name} {$u->ape_pat}"),
                'email' => $u->email,
            ]);

        return Inertia::render('Services/Index', [
            'services' => $services,
            'staff'    => $staffUsers,
            'filters'  => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Guarda un nuevo servicio.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'duration_minutes'  => 'required|integer|min:5|max:480',
            'price'             => 'required|numeric|min:0',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'string',
            'is_active'         => 'boolean',
        ]);

        Service::create($validated);

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Servicio creado exitosamente en el catálogo.',
        ]);
    }

    /**
     * Actualiza un servicio existente.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'duration_minutes'  => 'required|integer|min:5|max:480',
            'price'             => 'required|numeric|min:0',
            'assigned_user_ids' => 'nullable|array',
            'assigned_user_ids.*' => 'string',
            'is_active'         => 'boolean',
        ]);

        $service->update($validated);

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Servicio actualizado correctamente.',
        ]);
    }

    /**
     * Elimina un servicio.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Servicio eliminado del catálogo.',
        ]);
    }
}
