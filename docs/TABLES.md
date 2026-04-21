# Tablas — DaisyUI vs PrimeVue DataTable

## Cuándo usar cada una

| Criterio | DaisyUI | PrimeVue DataTable |
|---|---|---|
| Número de filas | ≤ 50 filas estáticas | Cientos o miles de filas |
| Filtros | Sin filtros o filtros simples | Filtros múltiples, búsqueda global |
| Ordenamiento | No necesario o manual | Click en columna para ordenar |
| Paginación | No necesaria | Paginación server-side o client-side |
| Ejemplos de uso | Lista de roles, módulos, permisos | Lista de usuarios, reportes, logs |
| Tiempo de implementación | 5 minutos | 15 minutos (pero cubre todo) |

---

## DaisyUI — Tabla simple

No importas nada. Solo clases CSS de DaisyUI sobre HTML semántico.

```vue
<template>
    <div class="overflow-x-auto rounded-box border border-base-content/5 bg-base-100">
        <table class="table">
            <!-- Encabezado -->
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(user, i) in users" :key="user.id" class="hover">
                    <th>{{ i + 1 }}</th>
                    <td>{{ user.name }}</td>
                    <td>{{ user.email }}</td>
                    <td>
                        <span class="badge badge-primary">{{ user.role }}</span>
                    </td>
                    <td>
                        <span
                            :class="user.status == 1 ? 'badge-success' : 'badge-error'"
                            class="badge"
                        >
                            {{ user.status == 1 ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                </tr>
                <tr v-if="!users.length">
                    <td colspan="5" class="text-center text-base-content/50">
                        Sin registros.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
```

### Variantes de tabla DaisyUI

```html
<table class="table">           <!-- tabla base -->
<table class="table table-xs">  <!-- más compacta -->
<table class="table table-sm">  <!-- compacta -->
<table class="table table-md">  <!-- default -->
<table class="table table-lg">  <!-- más espaciada -->
<table class="table table-xl">  <!-- extra grande -->

<tr class="hover">              <!-- fila con hover -->
<tr class="active">             <!-- fila seleccionada -->
```

### Badges de estado útiles

```html
<span class="badge badge-primary">Primario</span>
<span class="badge badge-success">Activo</span>
<span class="badge badge-error">Inactivo</span>
<span class="badge badge-warning">Pendiente</span>
<span class="badge badge-info">Info</span>
<span class="badge badge-ghost">Neutral</span>
```

---

## PrimeVue DataTable — Tabla compleja

### Importación por componente (tree-shaking)

Siempre importar directamente del módulo, no del barrel `primevue`:

```vue
<script setup>
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';

const props = defineProps({
    users: Array,
});

const filters = ref({
    global: { value: null, matchMode: 'contains' },
});
</script>
```

### Template básico con filtro global y ordenamiento

```vue
<template>
    <DataTable
        :value="users"
        :paginator="true"
        :rows="10"
        :rows-per-page-options="[10, 25, 50]"
        v-model:filters="filters"
        filter-display="menu"
        :global-filter-fields="['name', 'email', 'role']"
        sort-mode="multiple"
        removable-sort
        striped-rows
        class="p-datatable-sm"
    >
        <!-- Barra de herramientas superior -->
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <span class="text-xl font-bold">Usuarios</span>
                <InputText
                    v-model="filters['global'].value"
                    placeholder="Buscar..."
                    class="w-64"
                />
            </div>
        </template>

        <!-- Sin resultados -->
        <template #empty>
            <div class="py-8 text-center text-gray-400">
                No se encontraron registros.
            </div>
        </template>

        <Column field="name"  header="Nombre" sortable />
        <Column field="email" header="Email"  sortable />
        <Column field="role"  header="Rol"    sortable />
        <Column field="status" header="Estado">
            <template #body="{ data }">
                <span
                    :class="data.status == 1 ? 'badge badge-success' : 'badge badge-error'"
                    class="badge"
                >
                    {{ data.status == 1 ? 'Activo' : 'Inactivo' }}
                </span>
            </template>
        </Column>

        <!-- Columna de acciones -->
        <Column header="Acciones" style="width: 8rem">
            <template #body="{ data }">
                <div class="flex gap-2">
                    <Link :href="route('users.edit', data.id)" class="btn btn-xs btn-ghost">
                        Editar
                    </Link>
                </div>
            </template>
        </Column>
    </DataTable>
</template>
```

### Paginación server-side (recomendada para miles de registros)

```vue
<script setup>
import { router } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { ref } from 'vue';

const props = defineProps({
    users: Object,  // { data: [], total: 0, per_page: 10, current_page: 1 }
});

function onPage(event) {
    router.get(route('users'), {
        page: event.page + 1,
        per_page: event.rows,
    }, { preserveState: true });
}

function onSort(event) {
    router.get(route('users'), {
        sort_field: event.sortField,
        sort_order: event.sortOrder === 1 ? 'asc' : 'desc',
    }, { preserveState: true });
}
</script>

<template>
    <DataTable
        :value="users.data"
        :lazy="true"
        :paginator="true"
        :rows="users.per_page"
        :total-records="users.total"
        @page="onPage"
        @sort="onSort"
    >
        <Column field="name"  header="Nombre" sortable />
        <Column field="email" header="Email"  sortable />
    </DataTable>
</template>
```

Controlador Laravel correspondiente:

```php
public function index(Request $request)
{
    $query = User::query();

    if ($request->filled('search')) {
        $query->where('name', 'like', "%{$request->search}%")
              ->orWhere('email', 'like', "%{$request->search}%");
    }

    if ($request->filled('sort_field')) {
        $query->orderBy($request->sort_field, $request->sort_order ?? 'asc');
    }

    return Inertia::render('Users/Index', [
        'users' => $query->paginate($request->per_page ?? 10),
    ]);
}
```

### Componentes PrimeVue más usados en admin

```vue
<!-- Importar solo lo que usas -->
import DataTable  from 'primevue/datatable';
import Column     from 'primevue/column';
import InputText  from 'primevue/inputtext';
import Select     from 'primevue/select';        // dropdown
import DatePicker from 'primevue/datepicker';    // fecha
import MultiSelect from 'primevue/multiselect';  // selección múltiple
import Tag        from 'primevue/tag';           // etiqueta de estado
import Button     from 'primevue/button';        // botón estilizado
import Dialog     from 'primevue/dialog';        // modal
import Toast      from 'primevue/toast';         // notificaciones
import { useToast } from 'primevue/usetoast';    // hook del toast
```

### Toast (notificaciones) — reemplaza los flash messages

```vue
<script setup>
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { usePage, router } from '@inertiajs/vue3';
import { watch } from 'vue';

const toast = useToast();
const page  = usePage();

// Mostrar flash de Laravel automáticamente
watch(() => page.props.flash, (flash) => {
    if (flash?.message) {
        toast.add({
            severity: flash.type ?? 'info',   // 'success' | 'error' | 'warn' | 'info'
            summary: flash.message,
            life: 4000,
        });
    }
}, { immediate: true });
</script>

<template>
    <!-- Poner una sola vez en AuthenticatedLayout -->
    <Toast />
</template>
```

---

## Resumen de decisión

```
¿Cuántas filas?
├── < 50 filas estáticas → DaisyUI table  (clases CSS, cero JS)
└── > 50 o necesita filtrar/ordenar/paginar → PrimeVue DataTable
    ├── ¿Los datos caben en memoria? → lazy: false  (client-side)
    └── ¿Miles de registros? → lazy: true + paginación server-side
```
