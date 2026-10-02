<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\PermissionsModel;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'ape_pat',
        'ape_mat',
        'email',
        'password',
        'role_id',
        'user_type',
        'client_id',
        'status',
        'active',
        'profile_photo_path',
        'profile_banner_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
        'profile_banner_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function role_data()
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }

    public function companyMemberships()
    {
        return $this->hasMany(CompanyUser::class, 'user_id');
    }

    public function companies()
    {
        return $this->belongsToMany(
            Company::class,
            null,
            'user_id',
            'company_id',
            '_id',
            '_id',
            'company_user'
        );
    }

    public function getMembershipForCompany(?string $companyId): ?CompanyUser
    {
        if (empty($companyId)) {
            return null;
        }

        $userId = (string) $this->getKey();

        return CompanyUser::where('user_id', $userId)
            ->where('company_id', (string) $companyId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Evalúa si el usuario tiene un permiso específico, contextualizado a una empresa si se pasa $companyId.
     */
    public function hasPermission(string $permission, ?string $companyId = null): bool
    {
        $role = null;

        if (!empty($companyId)) {
            $membership = $this->getMembershipForCompany($companyId);
            if ($membership) {
                // Si la membresía tiene excepciones personalizadas
                if (!empty($membership->custom_permissions) && in_array($permission, $membership->custom_permissions, true)) {
                    return true;
                }
                $role = $membership->role;
            }
        }

        // Fallback al rol asignado directo al usuario (compatibilidad global)
        if (!$role) {
            $role = $this->role_data()->first();
        }

        if (!$role) {
            return false;
        }

        $permissionIds = collect($role->getPermissionsList())
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (string) $id)
            ->toArray();

        if (empty($permissionIds)) {
            return false;
        }

        return PermissionsModel::whereIn('_id', $permissionIds)
            ->where('status', 1)
            ->where('module', $permission)
            ->exists();
    }
}
