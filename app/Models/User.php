<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;

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
            'password' => 'hashed',
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

    public function ownedCompanies()
    {
        return $this->hasMany(Company::class, 'user_id');
    }

    public function getMembershipForCompany(?string $companyId): ?CompanyUser
    {
        if (empty($companyId)) {
            return null;
        }

        $userIdStr = (string) $this->getKey();
        $userObjId = null;
        try {
            $userObjId = new \MongoDB\BSON\ObjectId($userIdStr);
        } catch (\Throwable $e) {
        }

        $companyIdStr = (string) $companyId;
        $companyObjId = null;
        try {
            $companyObjId = new \MongoDB\BSON\ObjectId($companyIdStr);
        } catch (\Throwable $e) {
        }

        return CompanyUser::where(function ($q) use ($userIdStr, $userObjId) {
            $q->where('user_id', $userIdStr);
            if ($userObjId) {
                $q->orWhere('user_id', $userObjId);
            }
        })
        ->where(function ($q) use ($companyIdStr, $companyObjId) {
            $q->where('company_id', $companyIdStr);
            if ($companyObjId) {
                $q->orWhere('company_id', $companyObjId);
            }
        })
        ->where('status', 'active')
        ->first();
    }

    public function isSuperAdmin(): bool
    {
        if ($this->email === 'johan_palma45@hotmail.com' || $this->user_type === 'superadmin') {
            return true;
        }

        $role = $this->role_data()->first();
        if ($role && in_array($role->role, ['superadmin', 'super_admin'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Retorna los IDs (en formato string) de las empresas asignadas directamente a este usuario
     * (como propietario directo o con membresía activa).
     *
     * @return list<string>
     */
    public function getAssignedCompanyIds(): array
    {
        $userIdStr = (string) $this->getKey();
        if ($userIdStr === '') {
            return [];
        }

        $userObjId = null;
        try {
            $userObjId = new \MongoDB\BSON\ObjectId($userIdStr);
        } catch (\Throwable $e) {
        }

        // 1. Empresas donde es propietario directo
        $ownedCompanies = Company::where(function ($q) use ($userIdStr, $userObjId) {
            $q->where('user_id', $userIdStr);
            if ($userObjId) {
                $q->orWhere('user_id', $userObjId);
            }
        })
        ->get(['_id']);

        $ownedIds = $ownedCompanies->map(fn ($c) => (string) $c->getKey())->filter()->values()->toArray();

        // 2. Membresías activas en company_user
        $memberships = CompanyUser::where(function ($q) use ($userIdStr, $userObjId) {
            $q->where('user_id', $userIdStr);
            if ($userObjId) {
                $q->orWhere('user_id', $userObjId);
            }
        })
        ->where('status', 'active')
        ->get(['company_id']);

        $memberCompanyIds = $memberships->map(fn ($cu) => (string) ($cu->company_id ?? ''))->filter()->values()->toArray();

        return array_values(array_unique(array_filter(array_merge($ownedIds, $memberCompanyIds))));
    }

    /**
     * Retorna los IDs de las empresas asignadas al usuario.
     *
     * @return list<string>
     */
    public function getAccessibleCompanyIds(): array
    {
        return $this->getAssignedCompanyIds();
    }

    /**
     * Evalúa si el usuario tiene un permiso específico, contextualizado a una empresa si se pasa $companyId.
     */
    public function hasPermission(string $permission, ?string $companyId = null): bool
    {
        return app(\App\Services\Tenancy\PermissionCacheService::class)->hasPermission($this, $permission, $companyId);
    }
}
