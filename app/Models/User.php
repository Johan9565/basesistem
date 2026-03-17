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
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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

    public function hasPermission(string $permission): bool
    {
        $role = $this->role_data()->first();

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
