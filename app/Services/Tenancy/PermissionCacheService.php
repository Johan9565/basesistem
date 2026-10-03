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
            $role = null;
            $customPermissions = [];

            if (! empty($companyId)) {
                $membership = $user->getMembershipForCompany($companyId);
                if ($membership) {
                    $role = $membership->role ?: (empty($membership->role_id) ? null : \App\Models\RoleModel::find($membership->role_id));
                    $customPermissions = $membership->custom_permissions ?? [];
                }
            }

            // Fallback a rol global del usuario
            if (! $role && ! empty($user->role_id)) {
                $role = $user->role_data()->first() ?: \App\Models\RoleModel::find($user->role_id);
            }

            if (! $role && empty($customPermissions)) {
                return [];
            }

            $permissionIds = [];
            if ($role) {
                $permissionIds = collect($role->getPermissionsList())
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->toArray();
            }

            $permissionsFromRole = [];
            if (! empty($permissionIds)) {
                $permissionsFromRole = PermissionsModel::whereIn('_id', $permissionIds)
                    ->where('status', 1)
                    ->pluck('module')
                    ->toArray();
            }

            $allPerms = array_unique(array_merge($permissionsFromRole, $customPermissions));

            return array_values($allPerms);
        });
    }

    /**
     * Determina si el usuario tiene un permiso específico dentro del contexto de la empresa.
     */
    public function hasPermission(User $user, string $permission, ?string $companyId = null): bool
    {
        $permissions = $this->getUserPermissions($user, $companyId);

        return in_array($permission, $permissions, true);
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
                $companyIds = \App\Models\CompanyUser::where('user_id', (string) $userId)
                    ->pluck('company_id')
                    ->filter()
                    ->toArray();

                foreach ($companyIds as $cid) {
                    Cache::forget($this->getCacheKey($userId, (string) $cid));
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

        $uniqueIds = array_unique(array_filter($userIds));
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
                            'only' => ['auth', 'company_modules'],
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
            $fromUsers = User::where('role_id', new \MongoDB\BSON\ObjectId($roleId))
                ->orWhere('role_id', $roleId)
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } catch (\Throwable $e) {
            $fromUsers = User::where('role_id', $roleId)
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        $fromMemberships = [];
        try {
            $fromMemberships = \App\Models\CompanyUser::where('role_id', new \MongoDB\BSON\ObjectId($roleId))
                ->orWhere('role_id', $roleId)
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } catch (\Throwable $e) {
            $fromMemberships = \App\Models\CompanyUser::where('role_id', $roleId)
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        $allUserIds = array_unique(array_merge($fromUsers, $fromMemberships));
        $this->broadcastPermissionUpdate($allUserIds, $message);
    }

    /**
     * Sincroniza en tiempo real a todos los usuarios de una empresa (por ejemplo al cambiar módulos).
     */
    public function broadcastCompanyUpdate(string $companyId, string $message = 'Se han actualizado los módulos de la empresa.'): void
    {
        $this->invalidateAll();

        $companyUserIds = \App\Models\CompanyUser::where('company_id', (string) $companyId)
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $this->broadcastPermissionUpdate($companyUserIds, $message);
    }
}
