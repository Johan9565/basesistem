<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'companies';
    protected $table      = 'companies';

    protected $fillable = [
        'client_id',
        'name',
        'slug',
        'document_number',
        'email',
        'phone',
        'status',           // 'active', 'inactive'
        'modules',          // ['inventory' => true, 'services' => true, 'appointments' => true, 'whatsapp' => true]
        'settings',         // ['timezone' => 'America/Mexico_City', 'currency' => 'MXN', ...]
    ];

    protected function casts(): array
    {
        return [
            'modules'  => 'array',
            'settings' => 'array',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function memberships()
    {
        return $this->hasMany(CompanyUser::class, 'company_id');
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            null,
            'company_id',
            'user_id',
            '_id',
            '_id',
            'company_user'
        );
    }

    public function roles()
    {
        return $this->hasMany(RoleModel::class, 'company_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'company_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'company_id');
    }

    public function whatsappInstances()
    {
        return $this->hasMany(WhatsappInstance::class, 'company_id');
    }

    public function whatsappAppointments()
    {
        return $this->hasMany(WhatsappAppointment::class, 'company_id');
    }

    /**
     * Verifica si un módulo específico está habilitado en la empresa.
     */
    public function isModuleEnabled(string $module): bool
    {
        $modules = $this->modules ?? [];

        if (is_array($modules)) {
            // Soporta formato asociativo ['inventory' => true] o lista ['inventory', 'whatsapp']
            if (array_key_exists($module, $modules)) {
                return (bool) $modules[$module];
            }

            return in_array($module, $modules, true);
        }

        return false;
    }

    /**
     * Activa un módulo en la empresa.
     */
    public function enableModule(string $module): void
    {
        $modules = $this->modules ?? [];
        $modules[$module] = true;
        $this->modules = $modules;
        $this->save();
    }

    /**
     * Desactiva un módulo en la empresa.
     */
    public function disableModule(string $module): void
    {
        $modules = $this->modules ?? [];
        $modules[$module] = false;
        $this->modules = $modules;
        $this->save();
    }
}
