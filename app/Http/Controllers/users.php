<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\RoleModel;
use App\Models\User;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use MongoDB\BSON\ObjectId;

class users extends Controller
{
    /**
     * Gestión de Cuentas Principales de Clientes y Superadministrador (Nivel SaaS / Global).
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'role_id' => 'nullable|string',
            'status' => 'nullable|in:0,1',
        ]);

        $query = User::query()
            ->where(function ($q) {
                $q->where('user_type', 'client')
                    ->orWhere('user_type', 'superadmin')
                    ->orWhereNull('user_type');
            })
            ->where('user_type', '!=', 'employee');

        $search = isset($filters['search']) ? trim($filters['search']) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('ape_pat', 'like', '%'.$search.'%')
                    ->orWhere('ape_mat', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['role_id'])) {
            try {
                $query->where('role_id', new ObjectId($filters['role_id']));
            } catch (\Throwable $e) {
                // id inválido: no aplicar filtro
            }
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $allMemberships = CompanyUser::all();
        $allCompanies = Company::all()->keyBy(fn ($c) => (string) $c->_id);

        $users = $query->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(function ($user) use ($allMemberships, $allCompanies) {
                $userId = (string) $user->getKey();
                $globalRole = $user->role_data()->first();

                // Empresas a las que pertenece el usuario
                $userCompanyIds = $allMemberships
                    ->where('user_id', $userId)
                    ->pluck('company_id')
                    ->filter()
                    ->toArray();

                $companiesList = collect($userCompanyIds)
                    ->map(function ($cid) use ($allCompanies) {
                        $c = $allCompanies->get($cid);

                        return $c ? $c->name : null;
                    })
                    ->filter()
                    ->values()
                    ->toArray();

                return [
                    'id' => $userId,
                    'name' => $user->name,
                    'ape_pat' => $user->ape_pat ?? '',
                    'ape_mat' => $user->ape_mat ?? '',
                    'email' => $user->email,
                    'role' => $globalRole ? $globalRole->role : '—',
                    'role_id' => $globalRole ? (string) $globalRole->getKey() : '',
                    'status' => $user->status ?? 1,
                    'companies_count' => count($companiesList),
                    'companies_list' => $companiesList,
                ];
            });

        // Roles globales del sistema (Super Administrador, Administrador de Empresa, etc.)
        $roles = RoleModel::where('status', 1)
            ->where(function ($q) {
                $q->whereNull('company_id')->orWhere('company_id', '');
            })
            ->get(['id', 'name', 'role'])
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'name' => $r->role,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'role_id' => $filters['role_id'] ?? '',
                'status' => array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== ''
                    ? (string) $filters['status']
                    : '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ape_pat' => 'required|string|max:255',
            'ape_mat' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => 'required|string',
            'status' => 'required|in:0,1',
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        $user = User::create([
            'name' => $request->name,
            'ape_pat' => $request->ape_pat,
            'ape_mat' => $request->ape_mat,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => new ObjectId($role->getKey()),
            'user_type' => 'client',
            'status' => (int) $request->status,
            'active' => false,
        ]);

        app(PermissionCacheService::class)->invalidateUser((string) $user->_id);

        return redirect()->route('users')->with('flash', [
            'type' => 'success',
            'message' => 'Usuario del sistema registrado correctamente.',
        ]);
    }

    public function update(Request $request, $userId)
    {
        $user = User::findOrFail(new ObjectId($userId));

        $request->validate([
            'name' => 'required|string|max:255',
            'ape_pat' => 'required|string|max:255',
            'ape_mat' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role_id' => 'required|string',
            'status' => 'required|in:0,1',
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        $data = [
            'name' => $request->name,
            'ape_pat' => $request->ape_pat,
            'ape_mat' => $request->ape_mat,
            'email' => $request->email,
            'role_id' => new ObjectId($role->getKey()),
            'status' => (int) $request->status,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->refresh();

        app(PermissionCacheService::class)->invalidateUser((string) $user->_id);

        return redirect()->route('users')->with('flash', [
            'type' => 'success',
            'message' => 'Usuario del sistema actualizado correctamente.',
        ]);
    }

    public function destroy($userId)
    {
        $user = User::findOrFail(new ObjectId($userId));

        // Eliminar membresías vinculadas
        CompanyUser::where('user_id', (string) $user->_id)->delete();
        $user->delete();

        app(PermissionCacheService::class)->invalidateUser((string) $user->_id);

        return redirect()->route('users')->with('flash', [
            'type' => 'success',
            'message' => 'Usuario eliminado del sistema.',
        ]);
    }
}
