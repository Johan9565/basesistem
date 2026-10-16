<?php

namespace Database\Seeders;

use App\Models\PermissionsModel;
use App\Models\RoleModel;
use App\Services\Tenancy\PermissionCacheService;
use Illuminate\Database\Seeder;

class AtomicPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $atomicPermissions = [
            // --- 1. Módulo Empresas (SaaS) ---
            [
                'name' => 'companies.view',
                'module' => 'companies',
                'description' => 'Ver listado y detalles de empresas',
            ],
            [
                'name' => 'companies.create',
                'module' => 'companies',
                'description' => 'Crear y registrar nuevas empresas',
            ],
            [
                'name' => 'companies.edit',
                'module' => 'companies',
                'description' => 'Modificar información de empresas',
            ],
            [
                'name' => 'companies.toggle_module',
                'module' => 'companies',
                'description' => 'Activar o desactivar módulos en empresas',
            ],
            [
                'name' => 'companies.manage_owner',
                'module' => 'companies',
                'description' => 'Asignar o transferir el propietario de la empresa',
            ],
            [
                'name' => 'companies.manage_all',
                'module' => 'companies',
                'description' => 'Seleccionar, alternar y configurar todas las empresas del sistema aunque no sean propias',
            ],
            [
                'name' => 'companies.select_all',
                'module' => 'companies',
                'description' => 'Seleccionar y alternar entre todas las empresas del sistema en el conmutador de contexto',
            ],

            // --- 1.1 Módulo Sucursales de Empresa ---
            [
                'name' => 'branches.view',
                'module' => 'branches',
                'description' => 'Ver sucursales de la empresa',
            ],
            [
                'name' => 'branches.create',
                'module' => 'branches',
                'description' => 'Crear y registrar nuevas sucursales',
            ],
            [
                'name' => 'branches.edit',
                'module' => 'branches',
                'description' => 'Modificar información de sucursales',
            ],
            [
                'name' => 'branches.delete',
                'module' => 'branches',
                'description' => 'Eliminar sucursales de la empresa',
            ],

            // --- 2. Módulo Usuarios Clientes (SaaS) ---
            [
                'name' => 'users.view',
                'module' => 'users',
                'description' => 'Ver usuarios clientes y cuentas registradas',
            ],
            [
                'name' => 'users.create',
                'module' => 'users',
                'description' => 'Crear nuevas cuentas de usuario',
            ],
            [
                'name' => 'users.edit',
                'module' => 'users',
                'description' => 'Modificar datos de usuarios y estados',
            ],
            [
                'name' => 'users.delete',
                'module' => 'users',
                'description' => 'Eliminar cuentas de usuario',
            ],

            // --- 3. Módulo Empleados de Empresa ---
            [
                'name' => 'employees.view',
                'module' => 'employees',
                'description' => 'Ver personal y empleados de la empresa',
            ],
            [
                'name' => 'employees.create',
                'module' => 'employees',
                'description' => 'Registrar nuevos empleados en la empresa',
            ],
            [
                'name' => 'employees.edit',
                'module' => 'employees',
                'description' => 'Modificar datos de empleados y asignar roles',
            ],
            [
                'name' => 'employees.delete',
                'module' => 'employees',
                'description' => 'Dar de baja o eliminar empleados',
            ],

            // --- 4. Módulo Roles y Permisos (EXCLUSIVO SUPERADMIN) ---
            [
                'name' => 'roles.view',
                'module' => 'roles',
                'description' => 'Ver matriz de roles y permisos del sistema',
            ],
            [
                'name' => 'roles.create',
                'module' => 'roles',
                'description' => 'Crear nuevos roles en el sistema',
            ],
            [
                'name' => 'roles.edit',
                'module' => 'roles',
                'description' => 'Modificar permisos y asignaciones de roles',
            ],
            [
                'name' => 'roles.delete',
                'module' => 'roles',
                'description' => 'Eliminar roles personalizados',
            ],



            // --- 6. Módulo Inventario / Productos ---
            [
                'name' => 'products.view',
                'module' => 'products',
                'description' => 'Ver catálogo de productos y stock',
            ],
            [
                'name' => 'products.create',
                'module' => 'products',
                'description' => 'Crear y registrar nuevos productos',
            ],
            [
                'name' => 'products.edit',
                'module' => 'products',
                'description' => 'Modificar productos, precios y stock',
            ],
            [
                'name' => 'products.delete',
                'module' => 'products',
                'description' => 'Eliminar productos del catálogo',
            ],

            // --- 6.1 Módulo Pedidos (Descuento de Inventario) ---
            [
                'name' => 'orders.view',
                'module' => 'orders',
                'description' => 'Ver listado y detalles de pedidos',
            ],
            [
                'name' => 'orders.create',
                'module' => 'orders',
                'description' => 'Crear pedidos y descontar inventario',
            ],
            [
                'name' => 'orders.edit',
                'module' => 'orders',
                'description' => 'Modificar pedidos y estado de entrega',
            ],
            [
                'name' => 'orders.delete',
                'module' => 'orders',
                'description' => 'Eliminar o cancelar pedidos y restaurar stock',
            ],

            // --- 7. Módulo Servicios ---
            [
                'name' => 'services.view',
                'module' => 'services',
                'description' => 'Ver catálogo de servicios ofrecidos',
            ],
            [
                'name' => 'services.create',
                'module' => 'services',
                'description' => 'Registrar nuevos servicios en la empresa',
            ],
            [
                'name' => 'services.edit',
                'module' => 'services',
                'description' => 'Modificar servicios, duración y tarifas',
            ],
            [
                'name' => 'services.delete',
                'module' => 'services',
                'description' => 'Eliminar servicios del catálogo',
            ],

            // --- 8. Módulo Agenda & Citas ---
            [
                'name' => 'whatsapp.calendar.view',
                'module' => 'whatsapp.calendar',
                'description' => 'Ver calendario y citas programadas',
            ],
            [
                'name' => 'whatsapp.calendar.sync',
                'module' => 'whatsapp.calendar',
                'description' => 'Sincronizar citas con Google Calendar',
            ],
            [
                'name' => 'whatsapp.calendar.delete',
                'module' => 'whatsapp.calendar',
                'description' => 'Cancelar o eliminar citas agendadas',
            ],

            // --- 9. Módulo WhatsApp CRM - Instancias & Bots ---
            [
                'name' => 'whatsapp.instances.view',
                'module' => 'whatsapp.instances',
                'description' => 'Ver instancias y números conectados',
            ],
            [
                'name' => 'whatsapp.instances.create',
                'module' => 'whatsapp.instances',
                'description' => 'Crear nuevas instancias de WhatsApp',
            ],
            [
                'name' => 'whatsapp.instances.edit',
                'module' => 'whatsapp.instances',
                'description' => 'Configurar prompts de IA y parámetros de bot',
            ],
            [
                'name' => 'whatsapp.instances.connect',
                'module' => 'whatsapp.instances',
                'description' => 'Generar código QR y conectar número',
            ],
            [
                'name' => 'whatsapp.instances.delete',
                'module' => 'whatsapp.instances',
                'description' => 'Desconectar y eliminar instancias WhatsApp',
            ],

            // --- 10. Módulo WhatsApp CRM - Conversaciones ---
            [
                'name' => 'whatsapp.conversations.view',
                'module' => 'whatsapp.conversations',
                'description' => 'Ver chats y conversaciones en vivo',
            ],
            [
                'name' => 'whatsapp.conversations.delete',
                'module' => 'whatsapp.conversations',
                'description' => 'Vaciar historial de mensajes o borrar chats',
            ],
            [
                'name' => 'whatsapp.conversations.resume_bot',
                'module' => 'whatsapp.conversations',
                'description' => 'Reanudar o pausar bot en chats',
            ],

            // --- 11. Módulo WhatsApp CRM - Enviar Mensajes ---
            [
                'name' => 'whatsapp.send.view',
                'module' => 'whatsapp.send',
                'description' => 'Acceso a la pantalla de envío de mensajes',
            ],
            [
                'name' => 'whatsapp.send.execute',
                'module' => 'whatsapp.send',
                'description' => 'Enviar mensajes individuales o masivos',
            ],

            // --- 12. Módulo WhatsApp CRM - Chat Test (Simulador Bot) ---
            [
                'name' => 'whatsapp.chat-test',
                'module' => 'whatsapp.chat-test',
                'description' => 'Acceso general al módulo Chat Test (Simulador)',
            ],
            [
                'name' => 'whatsapp.chat-test.view',
                'module' => 'whatsapp.chat-test',
                'description' => 'Visualizar y acceder a la interfaz de Chat Test',
            ],
            [
                'name' => 'whatsapp.chat-test.send',
                'module' => 'whatsapp.chat-test',
                'description' => 'Enviar mensajes de prueba en el simulador de bot',
            ],
            [
                'name' => 'whatsapp.chat-test.reset',
                'module' => 'whatsapp.chat-test',
                'description' => 'Reiniciar historial de conversación y estado en pruebas',
            ],
        ];

        $savedPermissions = [];

        foreach ($atomicPermissions as $item) {
            $perm = PermissionsModel::updateOrCreate(
                ['name' => $item['name']],
                [
                    'module' => $item['module'],
                    'description' => $item['description'],
                    'status' => 1,
                ]
            );

            $savedPermissions[$item['name']] = [
                'id' => (string) $perm->_id,
                'name' => $perm->name,
            ];
        }

        // Actualizar ROL: Super Administrador (100% de los permisos atómicos)
        $superAdminRole = RoleModel::where('role', 'superadmin')->first();
        if ($superAdminRole) {
            $superAdminRole->permissions = array_values($savedPermissions);
            $superAdminRole->save();
        }

        // Actualizar ROL: Administrador de Empresa (Módulos operativos y empleados, SIN ROLES)
        $compAdminPermKeys = [
            // Empresas y Sucursales
            'companies.view', 'companies.edit',
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
            // Empleados
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            // Inventario
            'products.view', 'products.create', 'products.edit', 'products.delete',
            // Pedidos
            'orders.view', 'orders.create', 'orders.edit', 'orders.delete',
            // Servicios
            'services.view', 'services.create', 'services.edit', 'services.delete',
            // Calendario
            'whatsapp.calendar.view', 'whatsapp.calendar.sync', 'whatsapp.calendar.delete',
            // WhatsApp Instancias
            'whatsapp.instances.view', 'whatsapp.instances.create', 'whatsapp.instances.edit', 'whatsapp.instances.connect', 'whatsapp.instances.delete',
            // WhatsApp Conversaciones
            'whatsapp.conversations.view', 'whatsapp.conversations.delete', 'whatsapp.conversations.resume_bot',
            // WhatsApp Envío
            'whatsapp.send.view', 'whatsapp.send.execute',
        ];

        $compAdminPerms = [];
        foreach ($compAdminPermKeys as $key) {
            if (isset($savedPermissions[$key])) {
                $compAdminPerms[] = $savedPermissions[$key];
            }
        }

        $companyAdminRoles = RoleModel::whereIn('role', ['company_admin', 'admin'])->get();
        foreach ($companyAdminRoles as $roleModel) {
            $roleModel->permissions = $compAdminPerms;
            $roleModel->save();
        }

        // Invalidar caché de permisos
        app(PermissionCacheService::class)->invalidateAll();
    }
}
