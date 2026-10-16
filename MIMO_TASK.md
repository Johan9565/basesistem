caveman mode on. execute tasks now.

TASK 1: Update database/seeders/AtomicPermissionsSeeder.php
Add 'companies.view' and 'companies.edit' to $compAdminPermKeys:
```php
            $compAdminPermKeys = [
                // Empresas
                'companies.view', 'companies.edit',
                // Empleados
                'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
                // Inventario
                'products.view', 'products.create', 'products.edit', 'products.delete',
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
```

TASK 2: Update app/Http/Controllers/CompaniesController.php
1. In index(), pass can array in Inertia response:
```php
        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'clients' => $clients,
            'users' => $users,
            'can' => [
                'create' => $user->isSuperAdmin() || $user->hasPermission('companies.create'),
                'edit' => $user->isSuperAdmin() || $user->hasPermission('companies.edit'),
                'toggle_module' => $user->isSuperAdmin() || $user->hasPermission('companies.toggle_module'),
                'manage_owner' => $user->isSuperAdmin(),
            ],
        ]);
```
2. In update(), prevent non-superadmin from overriding user_id or status:
```php
        if (! $user->isSuperAdmin()) {
            $validated['user_id'] = (string) $company->user_id;
            $validated['status'] = $company->status;
        }
```

TASK 3: Update resources/js/Pages/Companies/Index.vue
1. Add `can: Object` to defineProps.
2. Wrap "Nueva Empresa" button with `v-if="can?.create"`.
3. In module toggles, set `:disabled="!can?.toggle_module"`.
4. In edit modal:
   - Only show `user_id` Select if `can?.manage_owner`. If not, show text: `<p class="text-xs font-semibold py-2 text-base-content/70">{{ company.owner_name }}</p>`.
   - Only show status Select if `can?.manage_owner`.

TASK 4: Run seeder and tests:
```bash
./vendor/bin/sail artisan db:seed --class=AtomicPermissionsSeeder
./vendor/bin/sail test --filter=CompanyOwnershipAndScopingTest
```
