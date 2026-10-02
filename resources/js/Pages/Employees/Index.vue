<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Password from 'primevue/password';
import SpeedDial from 'primevue/speeddial';
import Checkbox from 'primevue/checkbox';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const props = defineProps({
    employees: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            current_page: 1,
            last_page: 1,
            total: 0,
            from: null,
            to: null,
        }),
    },
    roles: {
        type: Array,
        default: () => [],
    },
    allPermissions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role_id: '',
            status: '',
        }),
    },
});

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const permissionsDialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const selectedEmployee = ref(null);

const statusOptions = [
    { label: 'Activo', value: 1 },
    { label: 'Inactivo', value: 0 },
];

const filterStatusOptions = [
    { label: 'Todos', value: '' },
    { label: 'Activo', value: '1' },
    { label: 'Inactivo', value: '0' },
];

const roleFilterOptions = computed(() => [
    { label: 'Todos los roles', value: '' },
    ...props.roles.map((r) => ({ label: r.name, value: r.id })),
]);

function normalizeFilterStatus(status) {
    if (status === 0 || status === '0') return '0';
    if (status === 1 || status === '1') return '1';
    return '';
}

const filterSearch = ref(props.filters.search ?? '');
const filterRoleId = ref(props.filters.role_id ?? '');
const filterStatus = ref(normalizeFilterStatus(props.filters.status));

watch(
    () => props.filters,
    (f) => {
        filterSearch.value = f.search ?? '';
        filterRoleId.value = f.role_id ?? '';
        filterStatus.value = normalizeFilterStatus(f.status);
    },
    { deep: true },
);

const hasActiveFilters = computed(
    () =>
        (filterSearch.value ?? '').trim() !== '' ||
        (filterRoleId.value ?? '') !== '' ||
        (filterStatus.value ?? '') !== '',
);

const employeeList = computed(() => props.employees?.data ?? []);
const activeCompany = computed(() => page.props.auth?.active_company);

function applyFilters() {
    const params = { page: 1 };
    const q = (filterSearch.value ?? '').trim();
    if (q) params.search = q;
    if (filterRoleId.value) params.role_id = filterRoleId.value;
    if (filterStatus.value !== '') params.status = filterStatus.value;

    router.get(route('employees'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters() {
    filterSearch.value = '';
    filterRoleId.value = '';
    filterStatus.value = '';
    router.get(route('employees'), { page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const form = useForm({
    name: '',
    ape_pat: '',
    ape_mat: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: props.roles?.[0]?.id ?? '',
    status: 1,
    custom_permissions: [],
});

function openCreateDialog() {
    if (props.roles?.length && !form.role_id) {
        form.role_id = props.roles[0].id;
    }
    form.status = 1;
    form.custom_permissions = [];
    createDialogVisible.value = true;
}

function closeCreateDialog() {
    createDialogVisible.value = false;
    form.clearErrors();
    form.reset();
}

function submit() {
    form.post(route('employees.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateDialog(),
    });
}

function openEditDialog(employee) {
    selectedEmployee.value = employee;
    form.name = employee.name ?? '';
    form.ape_pat = employee.ape_pat ?? '';
    form.ape_mat = employee.ape_mat ?? '';
    form.email = employee.email ?? '';
    form.role_id = employee.role_id ?? '';
    form.status = employee.status ?? 1;
    form.custom_permissions = [...(employee.custom_permissions ?? [])];
    form.password = null;
    form.password_confirmation = null;
    form.clearErrors();
    editDialogVisible.value = true;
}

function closeEditDialog() {
    editDialogVisible.value = false;
    selectedEmployee.value = null;
    form.clearErrors();
    form.reset();
}

function submitEdit() {
    if (!selectedEmployee.value) return;

    if (!form.password) {
        form.password = null;
        form.password_confirmation = null;
    }

    form.patch(route('employees.update', selectedEmployee.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeEditDialog();
        },
    });
}

function openPermissionsDialog(employee) {
    selectedEmployee.value = employee;
    form.name = employee.name;
    form.ape_pat = employee.ape_pat;
    form.ape_mat = employee.ape_mat;
    form.email = employee.email;
    form.role_id = employee.role_id;
    form.status = employee.status;
    form.custom_permissions = [...(employee.custom_permissions ?? [])];
    permissionsDialogVisible.value = true;
}

function closePermissionsDialog() {
    permissionsDialogVisible.value = false;
    selectedEmployee.value = null;
}

function submitPermissions() {
    if (!selectedEmployee.value) return;

    form.patch(route('employees.update', selectedEmployee.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closePermissionsDialog();
        },
    });
}

function openDeleteDialog(employee) {
    selectedEmployee.value = employee;
    deleteDialogVisible.value = true;
}

function closeDeleteDialog() {
    deleteDialogVisible.value = false;
    selectedEmployee.value = null;
}

function submitDelete() {
    if (!selectedEmployee.value) return;

    router.delete(route('employees.destroy', selectedEmployee.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteDialog();
        },
    });
}

function toggleEmployeeStatus(employee) {
    const nextStatus = employee.status == 1 ? 0 : 1;

    form.name = employee.name ?? '';
    form.ape_pat = employee.ape_pat ?? '';
    form.ape_mat = employee.ape_mat ?? '';
    form.email = employee.email ?? '';
    form.role_id = employee.role_id ?? '';
    form.status = nextStatus;
    form.custom_permissions = employee.custom_permissions ?? [];
    form.password = null;
    form.password_confirmation = null;

    form.patch(route('employees.update', employee.id), {
        preserveScroll: true,
    });
}

function dialItemsFor(employee) {
    const nextStatus = employee.status == 1 ? 0 : 1;

    return [
        {
            label: 'Editar',
            icon: 'pi pi-pencil',
            command: () => openEditDialog(employee),
        },
        {
            label: 'Permisos',
            icon: 'pi pi-shield',
            command: () => openPermissionsDialog(employee),
        },
        {
            label: nextStatus == 1 ? 'Inactivar' : 'Activar',
            icon: nextStatus == 1 ? 'pi pi-power-off' : 'pi pi-check-circle',
            command: () => toggleEmployeeStatus(employee),
        },
        {
            label: 'Desvincular',
            icon: 'pi pi-trash',
            command: () => openDeleteDialog(employee),
        },
    ];
}
</script>

<template>
    <Head title="Equipo de Empleados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Equipo de Empleados
                </h2>
                <div v-if="activeCompany" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-3 py-1 rounded-full w-fit">
                    🏢 {{ activeCompany.name }}
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-visible rounded-lg bg-white shadow dark:bg-gray-800">

                    <!-- Header de la gestión -->
                    <div
                        class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ employees.total }} empleado{{ employees.total !== 1 ? 's' : '' }} asignado{{ employees.total !== 1 ? 's' : '' }} a esta empresa
                            </p>
                        </div>
                        <Button label="Agregar empleado" icon="pi pi-user-plus" class="w-fit" @click="openCreateDialog"
                            severity="secondary" />
                    </div>

                    <!-- Filtros -->
                    <div
                        class="flex flex-col gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-700 sm:flex-row sm:flex-wrap sm:items-end"
                    >
                        <div class="min-w-48 flex-1">
                            <InputLabel value="Buscar empleado" />
                            <InputText
                                v-model="filterSearch"
                                class="mt-1 block w-full"
                                placeholder="Nombre, apellidos o correo"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="min-w-44 sm:w-48">
                            <InputLabel value="Rol en la empresa" />
                            <Select
                                v-model="filterRoleId"
                                :options="roleFilterOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div class="min-w-40 sm:w-40">
                            <InputLabel value="Estado" />
                            <Select
                                v-model="filterStatus"
                                :options="filterStatusOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2 pb-0.5">
                            <Button type="button" label="Aplicar" icon="pi pi-filter" @click="applyFilters" />
                            <Button
                                type="button"
                                label="Limpiar"
                                icon="pi pi-times"
                                class="p-button-text"
                                :disabled="!hasActiveFilters"
                                @click="clearFilters"
                            />
                        </div>
                    </div>

                    <!-- Modal Agregar Empleado -->
                    <Dialog v-model:visible="createDialogVisible" modal header="Agregar empleado a la empresa"
                        :style="{ width: '34rem' }" @hide="closeCreateDialog">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <InputLabel value="Nombre" />
                                <InputText v-model="form.name" class="mt-1 block w-full" autocomplete="given-name" />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Apellido paterno" />
                                    <InputText v-model="form.ape_pat" class="mt-1 block w-full" autocomplete="family-name" />
                                    <InputError :message="form.errors.ape_pat" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Apellido materno" />
                                    <InputText v-model="form.ape_mat" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.ape_mat" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <InputLabel value="Email corporativo / personal" />
                                <InputText v-model="form.email" type="email" class="mt-1 block w-full"
                                    autocomplete="email" />
                                <InputError :message="form.errors.email" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Rol asignado" />
                                    <Select v-model="form.role_id" :options="props.roles" optionLabel="name"
                                        optionValue="id" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.role_id" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Estado" />
                                    <Select v-model="form.status" :options="statusOptions" optionLabel="label"
                                        optionValue="value" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.status" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Contraseña" />
                                    <Password v-model="form.password" :feedback="false" toggleMask />
                                    <InputError :message="form.errors.password" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Confirmar contraseña" />
                                    <Password v-model="form.password_confirmation" :feedback="false" toggleMask />
                                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <Button type="button" label="Cancelar" class="p-button-text" :disabled="form.processing"
                                    @click="closeCreateDialog" />
                                <Button type="submit" label="Guardar empleado" icon="pi pi-check" :disabled="form.processing" />
                            </div>
                        </form>
                    </Dialog>

                    <!-- Editar Empleado -->
                    <Dialog
                        v-model:visible="editDialogVisible"
                        modal
                        header="Editar empleado"
                        :style="{ width: '34rem' }"
                        @hide="closeEditDialog"
                    >
                        <form @submit.prevent="submitEdit" class="space-y-4">
                            <div>
                                <InputLabel value="Nombre" />
                                <InputText v-model="form.name" class="mt-1 block w-full" autocomplete="given-name" />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Apellido paterno" />
                                    <InputText v-model="form.ape_pat" class="mt-1 block w-full" autocomplete="family-name" />
                                    <InputError :message="form.errors.ape_pat" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Apellido materno" />
                                    <InputText v-model="form.ape_mat" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.ape_mat" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <InputLabel value="Email" />
                                <InputText v-model="form.email" type="email" class="mt-1 block w-full"
                                    autocomplete="email" />
                                <InputError :message="form.errors.email" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Rol en la empresa" />
                                    <Select v-model="form.role_id" :options="props.roles" optionLabel="name"
                                        optionValue="id" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.role_id" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Estado" />
                                    <Select v-model="form.status" :options="statusOptions" optionLabel="label"
                                        optionValue="value" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.status" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Nueva contraseña (opcional)" />
                                    <Password v-model="form.password" :feedback="false" toggleMask />
                                    <InputError :message="form.errors.password" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Confirmar contraseña" />
                                    <Password v-model="form.password_confirmation" :feedback="false" toggleMask />
                                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <Button type="button" label="Cancelar" class="p-button-text" :disabled="form.processing"
                                    @click="closeEditDialog" />
                                <Button type="submit" label="Guardar cambios" icon="pi pi-check" :disabled="form.processing" />
                            </div>
                        </form>
                    </Dialog>

                    <!-- Modal Permisos Personalizados / Excepciones -->
                    <Dialog
                        v-model:visible="permissionsDialogVisible"
                        modal
                        header="Permisos personalizados del empleado"
                        :style="{ width: '36rem' }"
                        @hide="closePermissionsDialog"
                    >
                        <div class="space-y-4">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Puedes otorgar permisos adicionales a <strong>{{ selectedEmployee?.name }}</strong> independientemente de su rol base.
                            </p>

                            <div class="max-h-80 overflow-y-auto space-y-2 border border-gray-200 dark:border-gray-700 p-3 rounded-lg">
                                <div
                                    v-for="perm in props.allPermissions"
                                    :key="perm.id"
                                    class="flex items-start gap-3 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                >
                                    <Checkbox
                                        v-model="form.custom_permissions"
                                        :inputId="'perm_' + perm.id"
                                        :value="perm.module"
                                    />
                                    <label :for="'perm_' + perm.id" class="cursor-pointer text-sm">
                                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ perm.name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ perm.description || perm.module }}</div>
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <Button type="button" label="Cancelar" class="p-button-text" :disabled="form.processing"
                                    @click="closePermissionsDialog" />
                                <Button type="button" label="Guardar permisos" icon="pi pi-check" :disabled="form.processing"
                                    @click="submitPermissions" />
                            </div>
                        </div>
                    </Dialog>

                    <!-- Desvincular Empleado -->
                    <Dialog
                        v-model:visible="deleteDialogVisible"
                        modal
                        header="Desvincular empleado"
                        :style="{ width: '28rem' }"
                        @hide="closeDeleteDialog"
                    >
                        <div class="space-y-4">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                ¿Seguro que deseas desvincular a <strong>{{ selectedEmployee?.name }}</strong> de la empresa <strong>{{ activeCompany?.name }}</strong>?
                            </p>
                            <div class="flex justify-end gap-3 pt-2">
                                <Button type="button" label="Cancelar" class="p-button-text" :disabled="form.processing"
                                    @click="closeDeleteDialog" />
                                <Button
                                    type="button"
                                    label="Desvincular"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    :disabled="form.processing"
                                    @click="submitDelete"
                                />
                            </div>
                        </div>
                    </Dialog>

                    <!-- Lista de Empleados -->
                    <div class="grid">
                        <div v-if="employeeList.length === 0" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ hasActiveFilters ? 'No hay empleados que coincidan con los filtros.' : 'No hay empleados registrados en esta empresa.' }}
                        </div>

                        <div v-else class="grid lg:grid-cols-2">
                            <div
                                v-for="emp in employeeList"
                                :key="emp.id"
                                class="card bg-base-100 overflow-visible"
                                style="border-radius: 0rem;"
                            >
                                <div class="card-body">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div
                                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300"
                                            >
                                                {{ emp.name.charAt(0).toUpperCase() }}
                                            </div>

                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="truncate text-sm font-bold text-base-content">
                                                        {{ emp.name }} {{ emp.ape_pat }} {{ emp.ape_mat }}
                                                    </span>
                                                    <span v-if="emp.is_owner" class="badge badge-warning badge-xs">Dueño</span>
                                                </div>
                                                <div class="break-all text-sm text-base-content/70">
                                                    {{ emp.email }}
                                                </div>
                                                <div v-if="emp.custom_permissions?.length" class="text-xs text-indigo-500 mt-0.5">
                                                    +{{ emp.custom_permissions.length }} permiso(s) personalizado(s)
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-col items-end gap-2">
                                            <div class="flex flex-wrap items-center gap-2 justify-end">
                                                <span class="badge badge-primary badge-outline">
                                                    {{ emp.role }}
                                                </span>
                                                <span
                                                    class="badge"
                                                    :class="emp.status == 1 ? 'badge-success' : 'badge-error'"
                                                >
                                                    {{ emp.status == 1 ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>

                                            <!-- Dial de acciones -->
                                            <div
                                                class="flex items-end justify-end"
                                                style="height: 44px; width: 44px; overflow: visible; z-index: 50"
                                            >
                                                <SpeedDial
                                                    :model="dialItemsFor(emp)"
                                                    direction="left"
                                                    :radius="55"
                                                    :buttonProps="{ severity: 'warn', rounded: true, size: 'small' }"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div
                        v-if="employees.last_page > 1"
                        class="flex flex-col items-center gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700 sm:flex-row sm:justify-between"
                    >
                        <p class="text-center text-sm text-gray-500 dark:text-gray-400 sm:text-left">
                            <template v-if="employees.total">
                                Mostrando {{ employees.from }}–{{ employees.to }} de {{ employees.total }}
                            </template>
                        </p>
                        <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Paginación">
                            <template v-for="(link, i) in employees.links" :key="i">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-md border border-gray-300 px-2.5 text-sm font-medium transition-colors hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                                    :class="link.active
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-950 dark:text-indigo-200'
                                        : 'text-gray-700 dark:text-gray-200'"
                                    preserve-scroll
                                    preserve-state
                                >
                                    <span v-html="link.label" />
                                </Link>
                                <span
                                    v-else
                                    class="inline-flex min-h-9 min-w-9 items-center justify-center px-2.5 text-sm"
                                    :class="link.active
                                        ? 'font-semibold text-indigo-600 dark:text-indigo-400'
                                        : 'text-gray-400 dark:text-gray-500'"
                                    v-html="link.label"
                                />
                            </template>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
