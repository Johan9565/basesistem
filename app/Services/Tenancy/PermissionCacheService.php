<?php

namespace App\Services\Tenancy;

use App\Models\PermissionsModel;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionCacheService
{
    /**
     * TTL para la caché de permisos en segundos (1 hora).
     */
    protected const TTL = 3600;

    /**
     * Clave de caché para los permisos de un usuario en una empresa.
     */
    protected function getCacheKey(string $userId, ?string $companyId = null): string
    {
        $comp = $companyId ?: 'global';

        return "user_perms:{$userId}:{$comp}";
    }

    /**
     * Obtiene la lista de slugs de permisos permitidos para el usuario y empresa activa.
     *
     * @return list<string>
     */
    public function getUserPermissions(User $user, ?string $companyId = null): array
    {
        $userId = (string) $user->_id;
        $key = $this->getCacheKey($userId, $companyId);

        return Cache::remember($key, self::TTL, function () use ($user, $companyId) {
            // Si el usuario es Super Administrador y no tiene rol asignado directamente, buscar el rol superadmin como fallback
            if ($user->isSuperAdmin() && empty($user->role_id) && empty($companyId)) {
                $superAdminRole = \App\Models\RoleModel::whereIn('role', ['superadmin', 'super_admin'])->first();
                if ($superAdminRole) {
                    $roles[] = $superAdminRole;
                }
            }

            $roles = [];
            $customPermissions = [];

            if (! empty($companyId)) {
                $membership = $user->getMembershipForCompany($companyId);
                if ($membership) {
                    $mRole = $membership->role ?: (empty($membership->role_id) ? null : \App\Models\RoleModel::find($membership->role_id));
                    if ($mRole) {
                        $roles[] = $mRole;
                    }
                    $customPermissions = $membership->custom_permissions ?? [];
                }
            }

            // También incluir rol global del usuario si existe
            if (! empty($user->role_id)) {
                $globalRole = $user->role_data()->first() ?: \App\Models\RoleModel::find($user->role_id);
                if ($globalRole) {
                    $roles[] = $globalRole;
                }
            }

            if (empty($roles) && empty($customPermissions)) {
                return [];
            }

            $permissionIds = [];
            $permissionsFromRole = [];

            foreach ($roles as $roleItem) {
                $ids = collect($roleItem->getPermissionsList())
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->toArray();
                $permissionIds = array_merge($permissionIds, $ids);

                if (is_array($roleItem->permissions)) {
                    foreach ($roleItem->permissions as $p) {
                        if (is_array($p) && ! empty($p['name'])) {
                            $permissionsFromRole[] = $p['name'];
                        } elseif (is_string($p) && trim($p) !== '') {
                            $permissionsFromRole[] = trim($p);
                        }
                    }
                }
            }

            $permissionIds = array_values(array_unique(array_filter($permissionIds)));

            if (! empty($permissionIds)) {
                $objectIds = [];
                foreach ($permissionIds as $id) {
                    try {
                        $objectIds[] = new \MongoDB\BSON\ObjectId($id);
                    } catch (\Throwable) {
                    }
                }
                $allSearchIds = array_values(array_unique(array_merge($permissionIds, $objectIds)));

                $permModels = PermissionsModel::whereIn('_id', $allSearchIds)
                    ->where('status', 1)
                    ->get();

                foreach ($permModels as $pm) {
                    if (! empty($pm->name)) {
                        $permissionsFromRole[] = $pm->name;
                    }
                    // Si no es un permiso atómico (no contiene punto), incluir también su module
                    if (! empty($pm->module) && ! str_contains((string) $pm->name, '.')) {
                        $permissionsFromRole[] = $pm->module;
                    }
                }
            }

            $allPerms = array_unique(array_merge($permissionsFromRole, $customPermissions));

            // Si el usuario NO es superadmin, nos aseguramos de que NUNCA tenga permisos de 'roles'
            if (! $user->isSuperAdmin()) {
                $allPerms = array_filter($allPerms, fn ($p) => $p !== 'roles' && ! str_starts_with($p, 'roles.'));
            }

            return array_values($allPerms);
        });
    }

    /**
     * Determina si el usuario tiene un permiso específico dentro del contexto de la empresa.
     */
    public function hasPermission(User $user, string $permission, ?string $companyId = null): bool
    {
        // El módulo de roles es EXCLUSIVO para superadmin
        if ($permission === 'roles' || str_starts_with($permission, 'roles.')) {
            return $user->isSuperAdmin();
        }

        $permissions = $this->getUserPermissions($user, $companyId);

        // Normalizaciones de nombres comunes en español/inglés
        $aliases = [
            'administration' => ['administracion', 'administración', 'Administración'],
            'companies' => ['empresas'],
            'branches' => ['sucursales'],
            'users' => ['usuarios'],
            'employees' => ['empleados'],
            'products' => ['inventario'],
            'orders' => ['pedidos'],
            'services' => ['servicios'],
            'whatsapp.calendar' => ['calendario'],
        ];

        // 1. Coincidencia exacta con permiso (ej: 'products.create' o 'products')
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Revisar aliases directos
        if (isset($aliases[$permission])) {
            foreach ($aliases[$permission] as $alias) {
                if (in_array($alias, $permissions, true)) {
                    return true;
                }
            }
        }

        // 2. Si la ruta solicita un módulo general (ej: 'administration', 'products', 'services')
        if (! str_contains($permission, '.')) {
            // Si tiene algún permiso atómico de este módulo (ej: 'products.view' para 'products')
            foreach ($permissions as $p) {
                if (str_starts_with($p, "{$permission}.")) {
                    return true;
                }
            }

            // Caso especial: 'administration' engloba 'companies', 'branches', 'users', 'employees'
            if ($permission === 'administration' || in_array($permission, ['administracion', 'administración', 'Administración'], true)) {
                $adminSubmodules = ['companies', 'branches', 'users', 'employees'];
                foreach ($adminSubmodules as $sub) {
                    if (in_array($sub, $permissions, true)) {
                        return true;
                    }
                    if (isset($aliases[$sub])) {
                        foreach ($aliases[$sub] as $alias) {
                            if (in_array($alias, $permissions, true)) {
                                return true;
                            }
                        }
                    }
                    foreach ($permissions as $p) {
                        if (str_starts_with($p, "{$sub}.")) {
                            return true;
                        }
                    }
                }
            }
        } else {
            // 3. Si la ruta solicita un permiso atómico (ej: 'companies.view', 'products.create')
            $parts = explode('.', $permission);
            $baseModule = $parts[0];

            // Permisos atómicos exclusivos/de alta sensibilidad que NO deben heredarse por tener el módulo genérico 'companies' o 'administration'
            $explicitOnlyPermissions = [
                'companies.manage_all',
                'companies.select_all',
                'companies.manage_owner',
            ];
            if (in_array($permission, $explicitOnlyPermissions, true)) {
                return false;
            }

            // Si el rol tiene el módulo general heredado completo (ej: 'products' o 'inventario')
            if (in_array($baseModule, $permissions, true)) {
                return true;
            }
            if (isset($aliases[$baseModule])) {
                foreach ($aliases[$baseModule] as $alias) {
                    if (in_array($alias, $permissions, true)) {
                        return true;
                    }
                }
            }

            // Si pertenece a administración y el usuario tiene el permiso general de administración
            $adminSubmodules = ['companies', 'branches', 'users', 'employees'];
            if (in_array($baseModule, $adminSubmodules, true)) {
                if (in_array('administration', $permissions, true) || in_array('administracion', $permissions, true) || in_array('administración', $permissions, true) || in_array('Administración', $permissions, true)) {
                    return true;
                }
            }

            if (count($parts) > 2) {
                $subModule = $parts[0] . '.' . $parts[1];
                if (in_array($subModule, $permissions, true)) {
                    return true;
                }
                if (isset($aliases[$subModule])) {
                    foreach ($aliases[$subModule] as $alias) {
                        if (in_array($alias, $permissions, true)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Invalida la caché de permisos de un usuario (para una empresa específica o global).
     */
    public function invalidateUser(string $userId, ?string $companyId = null): void
    {
        if ($companyId) {
            Cache::forget($this->getCacheKey($userId, $companyId));
        } else {
            Cache::forget($this->getCacheKey($userId, null));
            Cache::forget("user_perms:{$userId}:global");

            try {
                $memberships = \App\Models\CompanyUser::where('user_id', (string) $userId)->get(['company_id']);
                foreach ($memberships as $m) {
                    if (! empty($m->company_id)) {
                        Cache::forget($this->getCacheKey($userId, (string) $m->company_id));
                    }
                }
            } catch (\Throwable $e) {
                // Fallback silencioso
            }
        }
    }

    /**
     * Invalida toda la caché de permisos (al modificar roles o matriz).
     */
    public function invalidateAll(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Fallback silencioso si no soporta flush total
        }
    }

    /**
     * Sincroniza en tiempo real los permisos y menú para uno o varios usuarios mediante WebSockets.
     *
     * @param  list<string>  $userIds
     */
    public function broadcastPermissionUpdate(array $userIds, string $message = 'Se han actualizado tus permisos de acceso.'): void
    {
        $this->invalidateAll();

        $uniqueIds = array_unique(array_filter(array_map('strval', $userIds)));
        foreach ($uniqueIds as $uid) {
            $this->invalidateUser((string) $uid);
            try {
                \App\Events\NotificacionToUser::dispatch(
                    $message,
                    (string) $uid,
                    null,
                    [(string) $uid],
                    null,
                    null,
                    null,
                    null,
                    [
                        'inertiaGlobal' => [
                            'only' => ['auth'],
                            'preserveScroll' => true,
                        ],
                    ]
                );
            } catch (\Throwable $e) {
                // Continuar si falla broadcast individual
            }
        }
    }

    /**
     * Sincroniza en tiempo real a todos los usuarios asignados a un rol específico.
     */
    public function broadcastRoleUpdate(string $roleId, string $message = 'Se han actualizado los permisos de tu rol.'): void
    {
        $this->invalidateAll();

        $fromUsers = [];
        try {
            $fromUsers = User::where(function ($q) use ($roleId) {
                $q->where('role_id', (string) $roleId);
                try {
                    $q->orWhere('role_id', new \MongoDB\BSON\ObjectId($roleId));
                } catch (\Throwable) {
                    // ignorar si no es un ObjectId válido
                }
            })
            ->get(['_id'])
            ->map(fn ($u) => (string) $u->getKey())
            ->filter()
            ->values()
            ->toArray();
        } catch (\Throwable $e) {
            $fromUsers = [];
        }

        $fromMemberships = [];
        try {
            $fromMemberships = \App\Models\CompanyUser::where(function ($q) use ($roleId) {
                $q->where('role_id', (string) $roleId);
                try {
                    $q->orWhere('role_id', new \MongoDB\BSON\ObjectId($roleId));
                } catch (\Throwable) {
                    // ignorar
                }
            })
            ->get(['user_id'])
            ->map(fn ($cu) => (string) ($cu->user_id ?? ''))
            ->filter()
            ->values()
            ->toArray();
        } catch (\Throwable $e) {
            $fromMemberships = [];
        }

        $currentUserId = auth()->id() ? (string) auth()->id() : null;
        $allUserIds = array_unique(array_filter(array_merge($fromUsers, $fromMemberships, $currentUserId ? [$currentUserId] : [])));
        $this->broadcastPermissionUpdate($allUserIds, $message);
    }

    /**
     * Sincroniza en tiempo real a todos los usuarios de una empresa (por ejemplo al cambiar módulos).
     */
    public function broadcastCompanyUpdate(string $companyId, string $message = 'Se han actualizado los módulos de la empresa.'): void
    {
        $this->invalidateAll();

        $companyUserIds = \App\Models\CompanyUser::where('company_id', (string) $companyId)
            ->get(['user_id'])
            ->map(fn ($cu) => (string) ($cu->user_id ?? ''))
            ->filter()
            ->values()
            ->toArray();

        $currentUserId = auth()->id() ? (string) auth()->id() : null;
        $allUserIds = array_unique(array_filter(array_merge($companyUserIds, $currentUserId ? [$currentUserId] : [])));

        $this->broadcastPermissionUpdate($allUserIds, $message);
    }
}
