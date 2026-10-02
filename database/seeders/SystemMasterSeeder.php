<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\RoleModel;
use App\Models\PermissionsModel;
use App\Models\ModulesModel;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Product;
use App\Models\Service;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Models\WhatsappBookingState;
use App\Models\WhatsappAppointment;
use App\Models\ComponentThemeModel;
use App\Services\Tenancy\PermissionCacheService;

class SystemMasterSeeder extends Seeder
{
    /**
     * Limpia la base de datos y siembra toda la estructura de permisos, roles,
     * empresas, usuarios multi-empresa, catálogo de productos, servicios e instancias.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('🧹 Limpiando y preparando la base de datos para la nueva arquitectura SaaS...');
        }

        // 1. Limpieza de tablas operativas para un arranque limpio
        User::truncate();
        Product::truncate();
        Service::truncate();
        CompanyUser::truncate();
        Company::truncate();
        Client::truncate();
        WhatsappInstance::truncate();
        WhatsappMessage::truncate();
        WhatsappBookingState::truncate();
        WhatsappAppointment::truncate();
        PermissionsModel::truncate();
        ModulesModel::truncate();
        RoleModel::truncate();

        // 2. Definición y creación de todos los Permisos y Módulos del Sistema
        if ($this->command) {
            $this->command->info('📦 Registrando Módulos y Permisos del Sistema...');
        }

        $systemModules = [
            // Sección Administración (Dropdown)
            [
                'perm_name'   => 'administracion',
                'perm_slug'   => 'administration',
                'perm_desc'   => 'Acceso a la sección principal de administración',
                'mod_name'    => 'Administración',
                'mod_route'   => 'administration',
                'relation'    => 0, // Dropdown contenedor
                'order_index' => 1,
            ],
            [
                'perm_name'   => 'empresas',
                'perm_slug'   => 'companies',
                'perm_desc'   => 'Gestión de empresas y activación de módulos',
                'mod_name'    => 'Empresas',
                'mod_route'   => 'companies',
                'relation'    => 'administration',
                'order_index' => 1,
            ],
            [
                'perm_name'   => 'usuarios',
                'perm_slug'   => 'users',
                'perm_desc'   => 'Gestión de cuentas principales de clientes y superadministrador',
                'mod_name'    => 'Usuarios Clientes',
                'mod_route'   => 'users',
                'relation'    => 'administration',
                'order_index' => 2,
            ],
            [
                'perm_name'   => 'empleados',
                'perm_slug'   => 'employees',
                'perm_desc'   => 'Gestión de empleados y permisos de la empresa',
                'mod_name'    => 'Empleados',
                'mod_route'   => 'employees',
                'relation'    => 'administration',
                'order_index' => 3,
            ],
            [
                'perm_name'   => 'roles',
                'perm_slug'   => 'roles',
                'perm_desc'   => 'Gestión de roles y matriz de permisos',
                'mod_name'    => 'Roles',
                'mod_route'   => 'roles',
                'relation'    => 'administration',
                'order_index' => 4,
            ],
            [
                'perm_name'   => 'componentes',
                'perm_slug'   => 'components',
                'perm_desc'   => 'Personalizador de estilos, colores y branding',
                'mod_name'    => 'Componentes & Tema',
                'mod_route'   => 'components',
                'relation'    => 'administration',
                'order_index' => 5,
            ],

            // Módulos de Negocio Directos
            [
                'perm_name'   => 'inventario',
                'perm_slug'   => 'products',
                'perm_desc'   => 'Gestión del inventario y productos',
                'mod_name'    => 'Inventario',
                'mod_route'   => 'products',
                'relation'    => 1, // Enlace directo
                'order_index' => 2,
            ],
            [
                'perm_name'   => 'servicios',
                'perm_slug'   => 'services',
                'perm_desc'   => 'Catálogo de servicios y asignación de personal',
                'mod_name'    => 'Servicios',
                'mod_route'   => 'services',
                'relation'    => 1,
                'order_index' => 3,
            ],
            [
                'perm_name'   => 'calendario',
                'perm_slug'   => 'whatsapp.calendar',
                'perm_desc'   => 'Calendario de citas y agenda de la empresa',
                'mod_name'    => 'Calendario',
                'mod_route'   => 'whatsapp.calendar',
                'relation'    => 1, // Enlace directo
                'order_index' => 4,
            ],

            // Sección WhatsApp CRM (Dropdown)
            [
                'perm_name'   => 'whatsapp',
                'perm_slug'   => 'whatsapp',
                'perm_desc'   => 'Acceso a la sección de WhatsApp CRM',
                'mod_name'    => 'WhatsApp CRM',
                'mod_route'   => 'whatsapp',
                'relation'    => 0,
                'order_index' => 5,
            ],
            [
                'perm_name'   => 'whatsapp-instancias',
                'perm_slug'   => 'whatsapp.instances',
                'perm_desc'   => 'Gestión y conexión de instancias WhatsApp',
                'mod_name'    => 'Instancias & Bots',
                'mod_route'   => 'whatsapp.instances',
                'relation'    => 'whatsapp',
                'order_index' => 1,
            ],
            [
                'perm_name'   => 'whatsapp-conversaciones',
                'perm_slug'   => 'whatsapp.conversations',
                'perm_desc'   => 'Visualización de chats y control en vivo',
                'mod_name'    => 'Conversaciones',
                'mod_route'   => 'whatsapp.conversations',
                'relation'    => 'whatsapp',
                'order_index' => 2,
            ],
            [
                'perm_name'   => 'whatsapp-enviar',
                'perm_slug'   => 'whatsapp.send',
                'perm_desc'   => 'Envío masivo o individual de mensajes',
                'mod_name'    => 'Enviar Mensaje',
                'mod_route'   => 'whatsapp.send',
                'relation'    => 'whatsapp',
                'order_index' => 3,
            ],
        ];

        $allPermissionObjects = [];
        $permissionMap = [];

        foreach ($systemModules as $item) {
            $perm = PermissionsModel::create([
                'name'        => $item['perm_name'],
                'module'      => $item['perm_slug'],
                'description' => $item['perm_desc'],
                'status'      => 1,
            ]);

            $permItem = [
                'id'   => (string) $perm->_id,
                'name' => $perm->name,
            ];
            $allPermissionObjects[] = $permItem;
            $permissionMap[$item['perm_slug']] = $permItem;

            ModulesModel::create([
                'name'        => $item['mod_name'],
                'route'       => $item['mod_route'],
                'relation'    => $item['relation'],
                'order_index' => $item['order_index'],
                'status'      => 1,
            ]);
        }

        // 3. Creación de Roles Globales
        if ($this->command) {
            $this->command->info('👥 Creando Roles del Sistema...');
        }

        // ROL: Super Administrador (100% de permisos del sistema)
        $superAdminRole = RoleModel::create([
            'name'        => 'Super Administrador',
            'role'        => 'superadmin',
            'status'      => 1,
            'is_system'   => true,
            'company_id'  => null,
            'permissions' => $allPermissionObjects,
        ]);

        // ROL: Administrador de Empresa
        $companyAdminPerms = array_values(array_filter($allPermissionObjects, function ($p) use ($permissionMap) {
            // Admin de empresa tiene empleados, roles, inventario, servicios, calendario y whatsapp
            return in_array($p['id'], [
                $permissionMap['administration']['id'] ?? '',
                $permissionMap['employees']['id'] ?? '',
                $permissionMap['roles']['id'] ?? '',
                $permissionMap['products']['id'] ?? '',
                $permissionMap['services']['id'] ?? '',
                $permissionMap['whatsapp.calendar']['id'] ?? '',
                $permissionMap['whatsapp']['id'] ?? '',
                $permissionMap['whatsapp.instances']['id'] ?? '',
                $permissionMap['whatsapp.conversations']['id'] ?? '',
                $permissionMap['whatsapp.send']['id'] ?? '',
            ]);
        }));

        $companyAdminRole = RoleModel::create([
            'name'        => 'Administrador de Empresa',
            'role'        => 'admin',
            'status'      => 1,
            'is_system'   => true,
            'company_id'  => null,
            'permissions' => $companyAdminPerms,
        ]);

        // ROL: Recepcionista / Operador (WhatsApp + Calendario + Servicios)
        $receptionistPerms = array_values(array_filter($allPermissionObjects, function ($p) use ($permissionMap) {
            return in_array($p['id'], [
                $permissionMap['services']['id'] ?? '',
                $permissionMap['whatsapp.calendar']['id'] ?? '',
                $permissionMap['whatsapp']['id'] ?? '',
                $permissionMap['whatsapp.conversations']['id'] ?? '',
                $permissionMap['whatsapp.send']['id'] ?? '',
            ]);
        }));

        $receptionistRole = RoleModel::create([
            'name'        => 'Recepcionista / Atención',
            'role'        => 'recepcionista',
            'status'      => 1,
            'is_system'   => true,
            'company_id'  => null,
            'permissions' => $receptionistPerms,
        ]);

        // ROL: Especialista / Profesional (Calendario + Servicios)
        $specialistPerms = array_values(array_filter($allPermissionObjects, function ($p) use ($permissionMap) {
            return in_array($p['id'], [
                $permissionMap['services']['id'] ?? '',
                $permissionMap['whatsapp.calendar']['id'] ?? '',
            ]);
        }));

        $specialistRole = RoleModel::create([
            'name'        => 'Especialista / Profesional',
            'role'        => 'especialista',
            'status'      => 1,
            'is_system'   => true,
            'company_id'  => null,
            'permissions' => $specialistPerms,
        ]);

        // ROL: Vendedor (Inventario + WhatsApp Conversaciones)
        $salesPerms = array_values(array_filter($allPermissionObjects, function ($p) use ($permissionMap) {
            return in_array($p['id'], [
                $permissionMap['products']['id'] ?? '',
                $permissionMap['whatsapp']['id'] ?? '',
                $permissionMap['whatsapp.conversations']['id'] ?? '',
            ]);
        }));

        $salesRole = RoleModel::create([
            'name'        => 'Vendedor / Asesor Comercial',
            'role'        => 'vendedor',
            'status'      => 1,
            'is_system'   => true,
            'company_id'  => null,
            'permissions' => $salesPerms,
        ]);

        // 4. Creación del Super Administrador y Cuentas Principales de Clientes SaaS
        if ($this->command) {
            $this->command->info('👑 Configurando Super Administrador y Usuarios Clientes SaaS...');
        }

        $superAdminUser = User::where('email', 'johan_palma45@hotmail.com')->first();

        if ($superAdminUser) {
            $superAdminUser->update([
                'name'      => 'JOHAN ALBERTO',
                'ape_pat'   => 'PALMA',
                'ape_mat'   => 'CHAVEZ',
                'role_id'   => (string) $superAdminRole->_id,
                'user_type' => 'client',
                'status'    => 1,
                'active'    => 1,
            ]);
        } else {
            $superAdminUser = User::create([
                'name'      => 'JOHAN ALBERTO',
                'ape_pat'   => 'PALMA',
                'ape_mat'   => 'CHAVEZ',
                'email'     => 'johan_palma45@hotmail.com',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $superAdminRole->_id,
                'user_type' => 'client',
                'status'    => 1,
                'active'    => 1,
            ]);
        }

        // Cuentas de Clientes SaaS (Dueños de Empresa / Cuenta Cliente)
        $clienteDental = User::firstOrCreate(
            ['email' => 'cliente.dental@dentalsalud.com'],
            [
                'name'      => 'Dr. Roberto',
                'ape_pat'   => 'Navarro',
                'ape_mat'   => 'Morales',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $companyAdminRole->_id,
                'user_type' => 'client',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        $clienteDistribuidora = User::firstOrCreate(
            ['email' => 'cliente.distribuidora@medicaexpress.com'],
            [
                'name'      => 'Lic. Mariana',
                'ape_pat'   => 'Estrada',
                'ape_mat'   => 'Solís',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $companyAdminRole->_id,
                'user_type' => 'client',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        $clienteSac = User::firstOrCreate(
            ['email' => 'cliente.sac@sacconsultores.com'],
            [
                'name'      => 'Mtro. Rodrigo',
                'ape_pat'   => 'Silva',
                'ape_mat'   => 'Aguilar',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $companyAdminRole->_id,
                'user_type' => 'client',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        // 5. Creación de Usuarios Empleados (Staff específico por Empresa)
        if ($this->command) {
            $this->command->info('👷 Creando Usuarios Empleados por empresa...');
        }

        // Empleados de Clínica Dental Norte
        $drGarciaUser = User::firstOrCreate(
            ['email' => 'dr.garcia@dentalnorte.com'],
            [
                'name'      => 'Dr. Alejandro',
                'ape_pat'   => 'García',
                'ape_mat'   => 'Torres',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $specialistRole->_id,
                'user_type' => 'employee',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        $recepcionUser = User::firstOrCreate(
            ['email' => 'recepcion@dentalnorte.com'],
            [
                'name'      => 'Laura',
                'ape_pat'   => 'Jiménez',
                'ape_mat'   => 'Pérez',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $receptionistRole->_id,
                'user_type' => 'employee',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        $gerenteDental = User::firstOrCreate(
            ['email' => 'gerente@dentalnorte.com'],
            [
                'name'      => 'Carlos',
                'ape_pat'   => 'Mendoza',
                'ape_mat'   => 'Ríos',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $companyAdminRole->_id,
                'user_type' => 'employee',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        // Empleados de Distribuidora Médica Express
        $vendedorExpress = User::firstOrCreate(
            ['email' => 'ventas@medicaexpress.com'],
            [
                'name'      => 'Roberto',
                'ape_pat'   => 'Vargas',
                'ape_mat'   => 'Luna',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $salesRole->_id,
                'user_type' => 'employee',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        $almacenExpress = User::firstOrCreate(
            ['email' => 'almacen@medicaexpress.com'],
            [
                'name'      => 'Jorge',
                'ape_pat'   => 'Ortega',
                'ape_mat'   => 'Ruiz',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $salesRole->_id,
                'user_type' => 'employee',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        // Empleados de SAC Consultores
        $licSanchezUser = User::firstOrCreate(
            ['email' => 'lic.sanchez@sacconsultores.com'],
            [
                'name'      => 'Lic. Fernando',
                'ape_pat'   => 'Sánchez',
                'ape_mat'   => 'Gómez',
                'password'  => Hash::make('password'),
                'role_id'   => (string) $specialistRole->_id,
                'user_type' => 'employee',
                'status'    => 1,
                'active'    => 1,
            ]
        );

        // 6. Creación de Clientes y Empresas con Diferentes Módulos
        if ($this->command) {
            $this->command->info('🏢 Creando Clientes y Empresas con módulos específicos...');
        }

        // CLIENTE 1: Corporativo Salud & Dental
        $client1 = Client::create([
            'name'            => 'Corporativo Dental & Salud S.A.',
            'document_number' => 'CORP-SALUD-001',
            'billing_email'   => 'facturacion@dentalsalud.com',
            'phone'           => '+52 998 123 4567',
            'status'          => 'active',
            'plan'            => 'enterprise',
            'max_companies'   => 10,
        ]);

        // EMPRESA 1.1: Clínica Dental Norte (TODOS los módulos encendidos)
        $companyDental = Company::create([
            'client_id'       => (string) $client1->_id,
            'name'            => 'Clínica Dental Norte',
            'slug'            => 'clinica-dental-norte',
            'document_number' => 'CDN-998877-A',
            'email'           => 'contacto@dentalnorte.com',
            'phone'           => '+52 998 555 1122',
            'status'          => 'active',
            'modules'         => [
                'inventory'    => true,
                'services'     => true,
                'appointments' => true,
                'whatsapp'     => true,
            ],
            'settings'        => [
                'timezone' => 'America/Cancun',
                'currency' => 'MXN',
            ],
        ]);

        // EMPRESA 1.2: Distribuidora Médica Express (Solo Inventario + WhatsApp, SIN Citas ni Servicios)
        $companyDistribuidora = Company::create([
            'client_id'       => (string) $client1->_id,
            'name'            => 'Distribuidora Médica Express',
            'slug'            => 'distribuidora-medica-express',
            'document_number' => 'DME-445566-B',
            'email'           => 'ventas@medicaexpress.com',
            'phone'           => '+52 998 555 3344',
            'status'          => 'active',
            'modules'         => [
                'inventory'    => true,
                'services'     => false,
                'appointments' => false,
                'whatsapp'     => true,
            ],
            'settings'        => [
                'timezone' => 'America/Cancun',
                'currency' => 'MXN',
            ],
        ]);

        // CLIENTE 2: SAC Consultores
        $client2 = Client::create([
            'name'            => 'Servicios y Asesorías SAC',
            'document_number' => 'SAC-CORP-002',
            'billing_email'   => 'admin@sacconsultores.com',
            'phone'           => '+52 555 987 6543',
            'status'          => 'active',
            'plan'            => 'pro',
            'max_companies'   => 5,
        ]);

        // EMPRESA 2.1: SAC Consultoría & Legal (Servicios + Citas + WhatsApp, SIN Inventario)
        $companySac = Company::create([
            'client_id'       => (string) $client2->_id,
            'name'            => 'SAC Consultoría & Asesoría',
            'slug'            => 'sac-consultoria-asesoria',
            'document_number' => 'SAC-112233-C',
            'email'           => 'citas@sacconsultores.com',
            'phone'           => '+52 555 888 9900',
            'status'          => 'active',
            'modules'         => [
                'inventory'    => false,
                'services'     => true,
                'appointments' => true,
                'whatsapp'     => true,
            ],
            'settings'        => [
                'timezone' => 'America/Mexico_City',
                'currency' => 'MXN',
            ],
        ]);

        // 7. Vinculación de Membresías en company_user
        if ($this->command) {
            $this->command->info('🔗 Asignando membresías de usuarios por empresa...');
        }

        // El Super Administrador tiene membresía como OWNER en TODAS las empresas
        foreach ([$companyDental, $companyDistribuidora, $companySac] as $comp) {
            CompanyUser::create([
                'user_id'            => (string) $superAdminUser->_id,
                'company_id'         => (string) $comp->_id,
                'role_id'            => (string) $superAdminRole->_id,
                'status'             => 'active',
                'is_owner'           => true,
                'custom_permissions' => [],
            ]);
        }

        // Clientes SaaS (Dueños de empresa)
        CompanyUser::create([
            'user_id'            => (string) $clienteDental->_id,
            'company_id'         => (string) $companyDental->_id,
            'role_id'            => (string) $companyAdminRole->_id,
            'status'             => 'active',
            'is_owner'           => true,
            'custom_permissions' => [],
        ]);

        CompanyUser::create([
            'user_id'            => (string) $clienteDistribuidora->_id,
            'company_id'         => (string) $companyDistribuidora->_id,
            'role_id'            => (string) $companyAdminRole->_id,
            'status'             => 'active',
            'is_owner'           => true,
            'custom_permissions' => [],
        ]);

        CompanyUser::create([
            'user_id'            => (string) $clienteSac->_id,
            'company_id'         => (string) $companySac->_id,
            'role_id'            => (string) $companyAdminRole->_id,
            'status'             => 'active',
            'is_owner'           => true,
            'custom_permissions' => [],
        ]);

        // Empleados de Clínica Dental Norte
        CompanyUser::create([
            'user_id'    => (string) $gerenteDental->_id,
            'company_id' => (string) $companyDental->_id,
            'role_id'    => (string) $companyAdminRole->_id,
            'status'     => 'active',
            'is_owner'   => false,
        ]);
        CompanyUser::create([
            'user_id'    => (string) $drGarciaUser->_id,
            'company_id' => (string) $companyDental->_id,
            'role_id'    => (string) $specialistRole->_id,
            'status'     => 'active',
            'is_owner'   => false,
        ]);
        CompanyUser::create([
            'user_id'    => (string) $recepcionUser->_id,
            'company_id' => (string) $companyDental->_id,
            'role_id'    => (string) $receptionistRole->_id,
            'status'     => 'active',
            'is_owner'   => false,
        ]);

        // Empleados de Distribuidora Médica Express
        CompanyUser::create([
            'user_id'    => (string) $vendedorExpress->_id,
            'company_id' => (string) $companyDistribuidora->_id,
            'role_id'    => (string) $salesRole->_id,
            'status'     => 'active',
            'is_owner'   => false,
        ]);
        CompanyUser::create([
            'user_id'    => (string) $almacenExpress->_id,
            'company_id' => (string) $companyDistribuidora->_id,
            'role_id'    => (string) $salesRole->_id,
            'status'     => 'active',
            'is_owner'   => false,
        ]);

        // Empleados de SAC Consultores
        CompanyUser::create([
            'user_id'    => (string) $licSanchezUser->_id,
            'company_id' => (string) $companySac->_id,
            'role_id'    => (string) $specialistRole->_id,
            'status'     => 'active',
            'is_owner'   => false,
        ]);

        // 7. Siembra de Servicios y Productos por Empresa
        if ($this->command) {
            $this->command->info('📦 Sembrando catálogo de Servicios y Productos...');
        }

        // Servicios para Clínica Dental Norte
        Service::create([
            'company_id'        => (string) $companyDental->_id,
            'name'              => 'Limpieza Dental Ultrasonido',
            'description'       => 'Profilaxis dental completa, eliminación de sarro y pulido dental.',
            'duration_minutes'  => 45,
            'price'             => 650.00,
            'assigned_user_ids' => [(string) $drGarciaUser->_id],
            'is_active'         => true,
        ]);
        Service::create([
            'company_id'        => (string) $companyDental->_id,
            'name'              => 'Blanqueamiento Dental Láser',
            'description'       => 'Tratamiento estético de aclaramiento dental en una sola sesión.',
            'duration_minutes'  => 60,
            'price'             => 1800.00,
            'assigned_user_ids' => [(string) $drGarciaUser->_id],
            'is_active'         => true,
        ]);
        Service::create([
            'company_id'        => (string) $companyDental->_id,
            'name'              => 'Consulta Diagnóstica y Valoración',
            'description'       => 'Revisión odontológica general, diagnóstico con cámara intraoral.',
            'duration_minutes'  => 30,
            'price'             => 350.00,
            'assigned_user_ids' => [(string) $drGarciaUser->_id],
            'is_active'         => true,
        ]);

        // Productos para Clínica Dental Norte (Inventario en clínica)
        Product::create([
            'company_id'  => (string) $companyDental->_id,
            'sku'         => 'DEN-001',
            'name'        => 'Cepillo Eléctrico SonicPro',
            'description' => 'Cepillo dental con tecnología sónica y 3 modos de cepillado.',
            'category'    => 'Higiene Bucal',
            'price'       => 850.00,
            'cost'        => 480.00,
            'stock'       => 25,
            'min_stock'   => 5,
            'is_active'   => true,
        ]);
        Product::create([
            'company_id'  => (string) $companyDental->_id,
            'sku'         => 'DEN-002',
            'name'        => 'Enjuague Antiséptico Clorhexidina 500ml',
            'description' => 'Enjuague bucal coadyuvante en tratamientos gingivales.',
            'category'    => 'Insumos',
            'price'       => 140.00,
            'cost'        => 75.00,
            'stock'       => 50,
            'min_stock'   => 10,
            'is_active'   => true,
        ]);

        // Productos para Distribuidora Médica Express (Catálogo mayorista)
        Product::create([
            'company_id'  => (string) $companyDistribuidora->_id,
            'sku'         => 'MED-101',
            'name'        => 'Guantes de Nitrilo Azul Caja x100 (Talla M)',
            'description' => 'Guantes desechables de examinación libres de polvo.',
            'category'    => 'Protección',
            'price'       => 195.00,
            'cost'        => 110.00,
            'stock'       => 200,
            'min_stock'   => 20,
            'is_active'   => true,
        ]);
        Product::create([
            'company_id'  => (string) $companyDistribuidora->_id,
            'sku'         => 'MED-102',
            'name'        => 'Termómetro Infrarrojo Digital Frontal',
            'description' => 'Termómetro sin contacto con pantalla LCD y memoria.',
            'category'    => 'Equipamiento',
            'price'       => 450.00,
            'cost'        => 260.00,
            'stock'       => 40,
            'min_stock'   => 8,
            'is_active'   => true,
        ]);
        Product::create([
            'company_id'  => (string) $companyDistribuidora->_id,
            'sku'         => 'MED-103',
            'name'        => 'Oxímetro de Pulso Digital Recargable',
            'description' => 'Sensor de SpO2 y frecuencia cardíaca para adultos y niños.',
            'category'    => 'Equipamiento',
            'price'       => 380.00,
            'cost'        => 210.00,
            'stock'       => 60,
            'min_stock'   => 10,
            'is_active'   => true,
        ]);

        // Servicios para SAC Consultoría
        Service::create([
            'company_id'        => (string) $companySac->_id,
            'name'              => 'Asesoría Jurídica y Corporativa',
            'description'       => 'Sesión de consultoría en derecho mercantil y contratos.',
            'duration_minutes'  => 60,
            'price'             => 1500.00,
            'assigned_user_ids' => [(string) $licSanchezUser->_id],
            'is_active'         => true,
        ]);
        Service::create([
            'company_id'        => (string) $companySac->_id,
            'name'              => 'Auditoría Fiscal y Contable',
            'description'       => 'Revisión y diagnóstico de situación tributaria.',
            'duration_minutes'  => 90,
            'price'             => 2800.00,
            'assigned_user_ids' => [(string) $licSanchezUser->_id],
            'is_active'         => true,
        ]);

        // 8. Instancias de WhatsApp aisladas por empresa
        if ($this->command) {
            $this->command->info('📱 Configurando Instancias de WhatsApp por empresa...');
        }

        WhatsappInstance::create([
            'company_id'              => (string) $companyDental->_id,
            'instance_name'           => 'clinica_dental_norte',
            'evolution_instance_name' => 'clinica_dental_norte',
            'business_name'           => 'Clínica Dental Norte',
            'system_prompt'           => 'Eres el asistente virtual de Clínica Dental Norte. Atiende amablemente y ayuda a los clientes a conocer servicios y agendar citas.',
            'business_hours'          => 'Lunes a Viernes de 9:00 a 19:00, Sábados de 9:00 a 14:00',
            'locations'               => 'Av. Tulum 123, Cancún, Quintana Roo',
            'status'                  => 'active',
            'ai_provider'             => 'deepseek',
            'timezone'                => 'America/Cancun',
        ]);

        WhatsappInstance::create([
            'company_id'              => (string) $companyDistribuidora->_id,
            'instance_name'           => 'distribuidora_express',
            'evolution_instance_name' => 'distribuidora_express',
            'business_name'           => 'Distribuidora Médica Express',
            'system_prompt'           => 'Eres el asistente de ventas de Distribuidora Médica Express. Brinda información de stock y precios de insumos médicos.',
            'business_hours'          => 'Lunes a Viernes de 8:00 a 18:00',
            'locations'               => 'Parque Industrial Cancún, Bodega 4B',
            'status'                  => 'active',
            'ai_provider'             => 'deepseek',
            'timezone'                => 'America/Cancun',
        ]);

        WhatsappInstance::create([
            'company_id'              => (string) $companySac->_id,
            'instance_name'           => 'sac_consultoria',
            'evolution_instance_name' => 'sac_consultoria',
            'business_name'           => 'SAC Consultores',
            'system_prompt'           => 'Eres el asistente de SAC Consultores. Coordina sesiones de asesoría legal y fiscal.',
            'business_hours'          => 'Lunes a Viernes de 9:00 a 18:00',
            'locations'               => 'Insurgentes Sur 450, CDMX',
            'status'                  => 'active',
            'ai_provider'             => 'deepseek',
            'timezone'                => 'America/Mexico_City',
        ]);

        // 9. Component Theme por Defecto
        ComponentThemeModel::firstOrCreate([], [
            'active_theme'           => 'dark',
            'landing_palette_preset' => 'azul',
            'styles'                 => [],
        ]);

        // 10. Limpiar toda la caché en Redis
        app(PermissionCacheService::class)->invalidateAll();

        if ($this->command) {
            $this->command->info('🎉 ¡Base de datos reestructurada y sembrada con éxito!');
            $this->command->info('👑 Super Administrador: ' . $superAdminUser->email);
            $this->command->info('🏢 Empresas Creadas: 3 (Clínica Dental Norte, Distribuidora Médica Express, SAC Consultoría)');
        }
    }
}
