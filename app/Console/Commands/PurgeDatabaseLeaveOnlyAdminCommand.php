<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\RoleModel;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsappAppointment;
use App\Models\WhatsappBookingState;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PurgeDatabaseLeaveOnlyAdminCommand extends Command
{
    protected $signature = 'db:purge-leave-admin';

    protected $description = 'Elimina todos los datos de la base de datos dejando únicamente al usuario administrador Johan.';

    public function handle(PermissionCacheService $permissionCache): int
    {
        $this->warn('Iniciando purga de datos en MongoDB...');

        $adminEmail = 'johan_palma45@hotmail.com';
        $admin = User::withoutGlobalScopes()->where('email', $adminEmail)->first();

        if (! $admin) {
            $this->error("No se encontró el usuario administrador con email: {$adminEmail}");

            return self::FAILURE;
        }

        $superAdminRole = RoleModel::where('role', 'superadmin')->first();
        if ($superAdminRole) {
            $admin->update([
                'role_id' => (string) $superAdminRole->_id,
                'status' => 1,
                'active' => 1,
            ]);
        }

        // 1. Eliminar todos los demás usuarios
        $deletedUsers = User::withoutGlobalScopes()->where('email', '!=', $adminEmail)->delete();
        $this->info("Usuarios eliminados: {$deletedUsers}");

        // 2. Eliminar datos multi-tenant y de negocio
        $deletedCompanies = Company::withoutGlobalScopes()->delete();
        $this->info("Empresas eliminadas: {$deletedCompanies}");

        $deletedClients = Client::withoutGlobalScopes()->delete();
        $this->info("Clientes eliminados: {$deletedClients}");

        $deletedMemberships = CompanyUser::withoutGlobalScopes()->delete();
        $this->info("Membresías company_user eliminadas: {$deletedMemberships}");

        $deletedBranches = Branch::withoutGlobalScopes()->delete();
        $this->info("Sucursales eliminadas: {$deletedBranches}");

        $deletedProducts = Product::withoutGlobalScopes()->delete();
        $this->info("Productos eliminados: {$deletedProducts}");

        $deletedServices = Service::withoutGlobalScopes()->delete();
        $this->info("Servicios eliminados: {$deletedServices}");

        // 3. Eliminar datos del módulo WhatsApp
        $deletedInstances = WhatsappInstance::withoutGlobalScopes()->delete();
        $this->info("Instancias WhatsApp eliminadas: {$deletedInstances}");

        $deletedMessages = WhatsappMessage::withoutGlobalScopes()->delete();
        $this->info("Mensajes WhatsApp eliminados: {$deletedMessages}");

        $deletedStates = WhatsappBookingState::withoutGlobalScopes()->delete();
        $this->info("Estados de reserva WhatsApp eliminados: {$deletedStates}");

        $deletedAppointments = WhatsappAppointment::withoutGlobalScopes()->delete();
        $this->info("Citas WhatsApp eliminadas: {$deletedAppointments}");

        // 4. Limpieza de colecciones temporales o de prueba
        $db = DB::connection('mongodb')->getDatabase();
        $collectionsToDrop = ['test_ignore'];
        foreach ($collectionsToDrop as $colName) {
            try {
                $db->selectCollection($colName)->drop();
                $this->info("Colección {$colName} eliminada.");
            } catch (\Throwable $e) {
                // ignorar si no existe
            }
        }

        // 5. Limpieza de Caché
        Cache::flush();
        $permissionCache->invalidateAll();
        $this->info('Caché y permisos invalidados.');

        $this->newLine();
        $this->info("✅ Purga completada exitosamente. Usuario conservado: {$admin->name} ({$admin->email}) con rol {$superAdminRole?->name}.");

        return self::SUCCESS;
    }
}
