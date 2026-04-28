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
        'area_id',
        'email',
        'password',
        'role_id',
        'status',
        'active',
        'settings',
        'portfolio',
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
            // Nota: en Mongo queremos guardar subdocumentos reales, no JSON string.
            // Los casts tipo `array` en Eloquent tienden a serializar a JSON string.
        ];
    }

    private function decodeJsonIfString($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $trim = trim($value);
        if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[')) {
            return $value;
        }

        try {
            $decoded = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function getSettingsAttribute($value)
    {
        return $this->decodeJsonIfString($value);
    }

    public function setSettingsAttribute($value): void
    {
        $decoded = $this->decodeJsonIfString($value);
        $this->attributes['settings'] = $decoded;
    }

    public function getPortfolioAttribute($value)
    {
        return $this->decodeJsonIfString($value);
    }

    public function setPortfolioAttribute($value): void
    {
        $decoded = $this->decodeJsonIfString($value);
        $this->attributes['portfolio'] = $decoded;
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
