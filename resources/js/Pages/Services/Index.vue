<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
    services: Object,
    branches: {
        type: Array,
        default: () => [],
    },
    staff: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const selectedBranch = ref(props.filters?.branch_id ?? '');
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingServiceId = ref(null);

const branchOptions = computed(() => [
    { label: '🏢 Todas las sucursales', value: '' },
    ...(props.branches || []).map((b) => ({
        label: `${b.name} ${b.is_main ? '(Matriz)' : ''}`,
        value: b.id,
    })),
]);

const form = useForm({
    branch_id: '',
    name: '',
    description: '',
    duration_minutes: 30,
    price: 0,
    assigned_user_ids: [],
    is_active: true,
});

function openCreateDialog() {
    isEditing.value = false;
    editingServiceId.value = null;
    form.reset();
    form.clearErrors();
    const mainBranch = props.branches?.find((b) => b.is_main) || props.branches?.[0];
    form.branch_id = mainBranch ? mainBranch.id : '';
    form.duration_minutes = 30;
    form.is_active = true;
    dialogVisible.value = true;
}

function openEditDialog(service) {
    isEditing.value = true;
    editingServiceId.value = service.id || service._id;
    form.reset();
    form.clearErrors();
    form.branch_id = service.branch_id ?? '';
    form.name = service.name ?? '';
    form.description = service.description ?? '';
    form.duration_minutes = Number(service.duration_minutes ?? 30);
    form.price = Number(service.price ?? 0);
    form.assigned_user_ids = Array.isArray(service.assigned_user_ids) ? service.assigned_user_ids : [];
    form.is_active = Boolean(service.is_active ?? true);
    dialogVisible.value = true;
}

function submitForm() {
    if (isEditing.value) {
        form.patch(route('services.update', editingServiceId.value), {
            onSuccess: () => { dialogVisible.value = false; },
        });
    } else {
        form.post(route('services.store'), {
            onSuccess: () => { dialogVisible.value = false; },
        });
    }
}

function deleteService(service) {
    if (confirm(`¿Estás seguro de eliminar el servicio "${service.name}"?`)) {
        router.delete(route('services.destroy', service.id || service._id));
    }
}

function applyFilters() {
    router.get(route('services'), {
        search: search.value,
        branch_id: selectedBranch.value,
    }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Catálogo de Servicios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-base-content flex items-center gap-2">
                        <span>💼</span>
                        <span>Catálogo de Servicios</span>
                    </h2>
                    <p class="text-xs text-base-content/60">
                        Administra los servicios, duración y costos de la empresa identificados por sucursal para agendamientos.
                    </p>
                </div>
                <Button
                    label="Nuevo Servicio"
                    icon="pi pi-plus"
                    size="small"
                    severity="primary"
                    @click="openCreateDialog"
                />
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Barra de Búsqueda y Filtro de Sucursal -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="relative flex-1">
                        <InputText
                            v-model="search"
                            placeholder="Buscar servicio por nombre o descripción..."
                            class="w-full text-sm"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div v-if="branches && branches.length > 0" class="w-full sm:w-64">
                        <Select
                            v-model="selectedBranch"
                            :options="branchOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Filtrar por sucursal"
                            class="w-full text-sm"
                            @change="applyFilters"
                        />
                    </div>
                    <Button
                        label="Buscar"
                        icon="pi pi-search"
                        size="small"
                        severity="secondary"
                        @click="applyFilters"
                    />
                </div>

                <!-- Tabla de Servicios -->
                <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-base-content">
                            <thead class="border-b border-base-300 bg-base-200/60 text-xs font-semibold uppercase tracking-wider text-base-content/70">
                                <tr>
                                    <th class="px-6 py-4">Servicio</th>
                                    <th class="px-6 py-4">Sucursal</th>
                                    <th class="px-6 py-4">Duración</th>
                                    <th class="px-6 py-4">Precio</th>
                                    <th class="px-6 py-4">Personal Asignado</th>
                                    <th class="px-6 py-4">Estado</th>
                                    <th class="px-6 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-300">
                                <tr
                                    v-for="service in services.data"
                                    :key="service.id || service._id"
                                    class="transition-colors hover:bg-base-200/40"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-base-content">{{ service.name }}</div>
                                        <div class="text-xs text-base-content/60 line-clamp-1">{{ service.description || 'Sin descripción' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                            <i class="pi pi-building text-[10px]"></i>
                                            {{ service.branch_name }}
                                            <span v-if="service.branch_code" class="text-[10px] opacity-70">({{ service.branch_code }})</span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-base-300 px-2.5 py-1 text-xs font-medium text-base-content/80">
                                            <i class="pi pi-clock text-[10px]"></i>
                                            {{ service.duration_minutes }} min
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-emerald-600 dark:text-emerald-400">
                                        ${{ Number(service.price).toFixed(2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs text-base-content/70">
                                            {{ (service.assigned_user_ids || []).length }} asignados
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="badge badge-sm"
                                            :class="service.is_active ? 'badge-success' : 'badge-ghost'"
                                        >
                                            {{ service.is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            size="small"
                                            severity="secondary"
                                            @click="openEditDialog(service)"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            size="small"
                                            severity="danger"
                                            @click="deleteService(service)"
                                        />
                                    </td>
                                </tr>

                                <tr v-if="!services.data || services.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-base-content/50">
                                        No hay servicios registrados en esta empresa para los filtros seleccionados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear / Editar -->
        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="isEditing ? 'Editar Servicio' : 'Nuevo Servicio'"
            :style="{ width: '520px' }"
            class="p-fluid"
        >
            <form @submit.prevent="submitForm" class="space-y-4 pt-2">
                <div v-if="branches && branches.length > 0">
                    <label class="text-xs font-semibold text-base-content/80">Sucursal Asignada *</label>
                    <Select
                        v-model="form.branch_id"
                        :options="branches"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Selecciona una sucursal"
                        class="w-full text-sm mt-1"
                        required
                    >
                        <template #option="slotProps">
                            <div class="flex items-center justify-between gap-2 w-full">
                                <span class="font-medium">{{ slotProps.option.name }}</span>
                                <span v-if="slotProps.option.is_main" class="badge badge-xs badge-primary">Matriz</span>
                            </div>
                        </template>
                    </Select>
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Nombre del Servicio *</label>
                    <InputText v-model="form.name" class="w-full text-sm mt-1" placeholder="Ej. Consulta Médica General" required />
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Descripción</label>
                    <Textarea v-model="form.description" rows="2" class="w-full text-sm mt-1" placeholder="Detalles o requisitos para el cliente" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Duración (Minutos) *</label>
                        <InputNumber v-model="form.duration_minutes" :min="5" :max="480" class="w-full text-sm mt-1" required />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Precio ($) *</label>
                        <InputNumber v-model="form.price" :min="0" :minFractionDigits="2" class="w-full text-sm mt-1" required />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Personal / Doctores Asignados</label>
                    <MultiSelect
                        v-model="form.assigned_user_ids"
                        :options="staff"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Selecciona profesionales"
                        class="w-full text-sm mt-1"
                        display="chip"
                    />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-xs font-semibold text-base-content/80">Servicio Habilitado para Citas</span>
                    <ToggleSwitch v-model="form.is_active" />
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                    <Button label="Cancelar" severity="secondary" text size="small" @click="dialogVisible = false" />
                    <Button :label="isEditing ? 'Actualizar' : 'Guardar Servicio'" type="submit" severity="primary" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AuthenticatedLayout>
</template>
