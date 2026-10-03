<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Models\WhatsappAppointment;
use App\Models\WhatsappBookingState;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use Illuminate\Database\Seeder;

class MultiTenantInitialMigrationSeeder extends Seeder
{
    /**
     * Ejecuta la migración y adaptación inicial de datos hacia la arquitectura Multi-Tenant.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Iniciando migración multi-empresa en MongoDB...');
        }

        // 1. Cliente principal por defecto
        $client = Client::first();
        if (! $client) {
            $client = Client::create([
                'name' => 'Cliente Principal',
                'document_number' => 'CLI-001',
                'billing_email' => 'admin@example.com',
                'phone' => '+1234567890',
                'status' => 'active',
                'plan' => 'enterprise',
                'max_companies' => 10,
                'metadata' => ['migrated' => true],
            ]);
            if ($this->command) {
                $this->command->info("Cliente principal creado: {$client->name} [{$client->_id}]");
            }
        } else {
            if ($this->command) {
                $this->command->info("Cliente existente reutilizado: {$client->name} [{$client->_id}]");
            }
        }

        // 2. Empresa inicial vinculada al cliente
        $company = Company::where('client_id', (string) $client->_id)->first();
        if (! $company) {
            $company = Company::create([
                'client_id' => (string) $client->_id,
                'name' => 'Empresa Principal',
                'slug' => 'empresa-principal',
                'document_number' => 'EMP-001',
                'email' => 'contacto@empresa-principal.com',
                'phone' => '+1234567890',
                'status' => 'active',
                'modules' => [
                    'whatsapp' => true,
                    'appointments' => true,
                    'services' => true,
                    'inventory' => true,
                ],
                'settings' => [
                    'timezone' => 'America/Mexico_City',
                    'currency' => 'USD',
                ],
            ]);
            if ($this->command) {
                $this->command->info("Empresa principal creada: {$company->name} [{$company->_id}]");
            }
        } else {
            if ($this->command) {
                $this->command->info("Empresa existente reutilizada: {$company->name} [{$company->_id}]");
            }
        }

        $companyId = (string) $company->_id;

        // 3. Vincular usuarios existentes a la empresa en company_user
        $users = User::all();
        $linkedUsersCount = 0;

        foreach ($users as $index => $user) {
            $existingMembership = CompanyUser::where('user_id', (string) $user->_id)
                ->where('company_id', $companyId)
                ->first();

            if (! $existingMembership) {
                CompanyUser::create([
                    'user_id' => (string) $user->_id,
                    'company_id' => $companyId,
                    'role_id' => $user->role_id ? (string) $user->role_id : null,
                    'status' => 'active',
                    'is_owner' => ($index === 0), // El primer usuario queda como owner
                    'custom_permissions' => [],
                ]);
                $linkedUsersCount++;
            }
        }
        if ($this->command) {
            $this->command->info("Membresías de usuario vinculadas en company_user: {$linkedUsersCount}");
        }

        // 4. Inyectar company_id en documentos huérfanos de WhatsApp
        $updatedInstances = WhatsappInstance::whereNull('company_id')
            ->orWhere('company_id', '')
            ->update(['company_id' => $companyId]);
        if ($this->command) {
            $this->command->info("Instancias WhatsApp actualizadas con company_id: {$updatedInstances}");
        }

        $updatedMessages = WhatsappMessage::whereNull('company_id')
            ->orWhere('company_id', '')
            ->update(['company_id' => $companyId]);
        if ($this->command) {
            $this->command->info("Mensajes WhatsApp actualizados con company_id: {$updatedMessages}");
        }

        $updatedStates = WhatsappBookingState::whereNull('company_id')
            ->orWhere('company_id', '')
            ->update(['company_id' => $companyId]);
        if ($this->command) {
            $this->command->info("Estados de reserva actualizados con company_id: {$updatedStates}");
        }

        $updatedAppointments = WhatsappAppointment::whereNull('company_id')
            ->orWhere('company_id', '')
            ->update(['company_id' => $companyId]);
        if ($this->command) {
            $this->command->info("Citas WhatsApp actualizadas con company_id: {$updatedAppointments}");
            $this->command->info('✅ Fase 1 completada con éxito.');
        }
    }
}
