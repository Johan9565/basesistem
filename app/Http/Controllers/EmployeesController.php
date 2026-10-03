<?php

namespace App\Http\Controllers;

use App\Models\CompanyUser;
use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use MongoDB\BSON\ObjectId;

class EmployeesController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'role_id' => 'nullable|string',
            'status' => 'nullable|in:0,1',
        ]);

        $companyContext = app(CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        if (empty($activeCompanyId)) {
            return redirect()->route('dashboard')->with('flash', [
                'type' => 'error',
                'message' => 'Por favor selecciona una empresa activa para gestionar sus empleados.',
            ]);
        }

        // Obtener todas las membresías activas / existentes en esta empresa
        $memberships = CompanyUser::where('company_id', (string) $activeCompanyId)->get();
        $userIds = $memberships->pluck('user_id')->filter()->toArray();
        $membershipsMap = $memberships->keyBy('user_id');

        $query = User::whereIn('_id', $userIds)
            ->where('user_type', 'employee');

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
            $matchingUserIds = $memberships
                ->where('role_id', $filters['role_id'])
                ->pluck('user_id')
                ->toArray();
            $query->whereIn('_id', $matchingUserIds);
        }

        $employees = $query->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(function ($user) use ($membershipsMap) {
                $userId = (string) $user->getKey();
                $membership = $membershipsMap->get($userId);

                $roleId = $membership ? (string) $membership->role_id : '';
                $roleModel = $membership ? ($membership->role ?: (empty($roleId) ? null : RoleModel::find($roleId))) : null;
                $roleName = $roleModel ? $roleModel->role : '—';

                $status = ($membership && $membership->status === 'inactive') ? 0 : 1;
                $customPermissions = $membership ? ($membership->custom_permissions ?? []) : [];
                $isOwner = (bool) ($membership->is_owner ?? false);

                return [
                    'id' => $userId,
                    'membership_id' => $membership ? (string) $membership->_id : null,
                    'name' => $user->name,
                    'ape_pat' => $user->ape_pat ?? '',
                    'ape_mat' => $user->ape_mat ?? '',
                    'email' => $user->email,
                    'role' => $roleName,
                    'role_id' => $roleId,
                    'status' => $status,
                    'is_owner' => $isOwner,
                    'custom_permissions' => $customPermissions,
                ];
            });

        // Roles disponibles para esta empresa
        $roles = RoleModel::where('status', 1)
            ->availableForCompany($activeCompanyId)
            ->get(['id', 'name', 'role'])
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'name' => $r->role,
            ]);

        // Lista de permisos del sistema para excepciones/permisos personalizados
        $allPermissions = PermissionsModel::where('status', 1)
            ->get(['id', 'name', 'module', 'description'])
            ->map(fn ($p) => [
                'id' => (string) $p->id,
                'name' => $p->name,
                'module' => $p->module,
                'description' => $p->description ?? '',
            ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'roles' => $roles,
            'allPermissions' => $allPermissions,
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
        $companyContext = app(CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        if (empty($activeCompanyId)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'No hay una empresa activa seleccionada.',
            ]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'ape_pat' => 'required|string|max:255',
            'ape_mat' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => 'required|string',
            'status' => 'required|in:0,1',
            'custom_permissions' => 'nullable|array',
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $user->update([
                'name' => $request->name,
                'ape_pat' => $request->ape_pat,
                'ape_mat' => $request->ape_mat,
            ]);
        } else {
            $user = User::create([
                'name' => $request->name,
                'ape_pat' => $request->ape_pat,
                'ape_mat' => $request->ape_mat,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => new ObjectId($role->getKey()),
                'user_type' => 'employee',
                'status' => 1,
                'active' => false,
            ]);
        }

        CompanyUser::updateOrCreate(
            [
                'user_id' => (string) $user->_id,
                'company_id' => (string) $activeCompanyId,
            ],
            [
                'role_id' => (string) $role->_id,
                'status' => (int) $request->status === 1 ? 'active' : 'inactive',
                'is_owner' => false,
                'custom_permissions' => $request->custom_permissions ?? [],
            ]
        );

        app(PermissionCacheService::class)->broadcastPermissionUpdate([(string) $user->_id], 'Has sido registrado como empleado en la empresa.');

        return redirect()->route('employees')->with('flash', [
            'type' => 'success',
            'message' => 'Empleado registrado y vinculado exitosamente.',
        ]);
    }

    public function update(Request $request, string $userId)
    {
        $companyContext = app(CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        if (empty($activeCompanyId)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'No hay una empresa activa seleccionada.',
            ]);
        }

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
            'custom_permissions' => 'nullable|array',
        ]);

        $role = RoleModel::findOrFail($request->role_id);

        $userData = [
            'name' => $request->name,
            'ape_pat' => $request->ape_pat,
            'ape_mat' => $request->ape_mat,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        CompanyUser::updateOrCreate(
            [
                'user_id' => (string) $user->_id,
                'company_id' => (string) $activeCompanyId,
            ],
            [
                'role_id' => (string) $role->_id,
                'status' => (int) $request->status === 1 ? 'active' : 'inactive',
                'custom_permissions' => $request->custom_permissions ?? [],
            ]
        );

        app(PermissionCacheService::class)->broadcastPermissionUpdate([(string) $user->_id], 'Tus permisos en la empresa han sido actualizados.');

        return redirect()->route('employees')->with('flash', [
            'type' => 'success',
            'message' => 'Datos del empleado actualizados correctamente.',
        ]);
    }

    public function destroy(string $userId)
    {
        $companyContext = app(CompanyContext::class);
        $activeCompanyId = $companyContext->getCompanyId();

        if (! empty($activeCompanyId)) {
            CompanyUser::where('user_id', $userId)
                ->where('company_id', (string) $activeCompanyId)
                ->delete();
        }

        app(PermissionCacheService::class)->broadcastPermissionUpdate([(string) $userId], 'Has sido desvinculado de la empresa.');

        return redirect()->route('employees')->with('flash', [
            'type' => 'success',
            'message' => 'Empleado desvinculado de la empresa exitosamente.',
        ]);
    }
}
