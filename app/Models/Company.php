<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'companies';

    protected $table = 'companies';

    protected $fillable = [
        'user_id',
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
            'modules' => 'array',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Company $company) {
            $companyIdStr = (string) $company->_id;
            if (Branch::where('company_id', $companyIdStr)->count() === 0) {
                Branch::create([
                    'company_id' => $companyIdStr,
                    'name' => $company->name,
                    'code' => 'MATRIZ',
                    'phone' => $company->phone ?? '',
                    'email' => $company->email ?? '',
                    'is_main' => true,
                    'is_active' => true,
                ]);
            }
        });

        static::updated(function (Company $company) {
            $companyIdStr = (string) $company->_id;
            // Si la empresa solo tiene su primera sucursal (la matriz) y cambia de nombre o teléfono, mantenerla sincronizada
            if ($company->wasChanged(['name', 'phone', 'email']) && Branch::where('company_id', $companyIdStr)->count() === 1) {
                $mainBranch = Branch::where('company_id', $companyIdStr)->where('is_main', true)->first();
                if ($mainBranch) {
                    $mainBranch->update([
                        'name' => $company->name,
                        'phone' => $company->phone ?? '',
                        'email' => $company->email ?? '',
                    ]);
                }
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope para buscar por _id soportando tanto string como ObjectId en MongoDB.
     */
    public function scopeWhereInMongoIds($query, array $ids)
    {
        $searchValues = [];
        foreach ($ids as $id) {
            $str = (string) $id;
            if ($str === '') {
                continue;
            }
            $searchValues[] = $str;
            try {
                $searchValues[] = new \MongoDB\BSON\ObjectId($str);
            } catch (\Throwable $e) {
                // ignorar si no es un ObjectId válido
            }
        }

        $searchValues = array_values(array_unique($searchValues));

        return $query->whereIn('_id', $searchValues);
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

    public function orders()
    {
        return $this->hasMany(Order::class, 'company_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'company_id');
    }

    public function whatsappInstances()
    {
        return $this->hasMany(WhatsappInstance::class, 'company_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'company_id');
    }

    public function mainBranch(): ?Branch
    {
        return $this->branches()->where('is_main', true)->first()
            ?? $this->branches()->first();
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
        // El módulo 'orders' (pedidos) es inherente de inventarios
        if ($module === 'orders') {
            if (! $this->isModuleEnabled('inventory')) {
                return false;
            }
        }

        $modules = $this->modules ?? [];

        if (is_array($modules)) {
            // Soporta formato asociativo ['inventory' => true] o lista ['inventory', 'whatsapp']
            if (array_key_exists($module, $modules)) {
                return (bool) $modules[$module];
            }

            if (in_array($module, $modules, true)) {
                return true;
            }

            // Si se consulta chat-test y no fue explícitamente configurado, hereda de whatsapp
            if ($module === 'chat-test') {
                return $this->isModuleEnabled('whatsapp');
            }

            // Si se consulta pedidos y no fue configurado explícitamente, hereda de inventario
            if ($module === 'orders') {
                return $this->isModuleEnabled('inventory');
            }
        }

        return false;
    }

    /**
     * Activa un módulo en la empresa.
     */
    public function enableModule(string $module): void
    {
        $modules = $this->modules ?? [];

        // Si se activa pedidos, asegurar que inventario también quede activo
        if ($module === 'orders' && ! $this->isModuleEnabled('inventory')) {
            $modules['inventory'] = true;
        }

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

        // Si se desactiva inventario, pedidos también debe desactivarse por ser inherente
        if ($module === 'inventory') {
            $modules['orders'] = false;
        }

        $this->modules = $modules;
        $this->save();
    }
}
