<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import WrappingSelect from '@/Components/WrappingSelect.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Password from 'primevue/password';
import SpeedDial from 'primevue/speeddial';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const props = defineProps({
    users: Array,
    roles: {
        type: Array,
        default: () => [],
    },
    areas: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role_id: '',
            status: '',
            area_id: '',
        }),
    },
});

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const selectedUserId = ref(null);

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

const areaFilterOptions = computed(() => [
    { label: 'Todas las áreas', value: '' },
    ...(props.areas ?? []).map((a) => ({ label: a.name, value: a.id })),
]);

function normalizeFilterStatus(status) {
    if (status === 0 || status === '0') return '0';
    if (status === 1 || status === '1') return '1';
    return '';
}

const filterSearch = ref(props.filters.search ?? '');
const filterRoleId = ref(props.filters.role_id ?? '');
const filterStatus = ref(normalizeFilterStatus(props.filters.status));
const filterAreaId = ref(props.filters.area_id ?? '');

watch(
    () => props.filters,
    (f) => {
        filterSearch.value = f.search ?? '';
        filterRoleId.value = f.role_id ?? '';
        filterStatus.value = normalizeFilterStatus(f.status);
        filterAreaId.value = f.area_id ?? '';
    },
    { deep: true },
);

const hasActiveFilters = computed(
    () =>
        (filterSearch.value ?? '').trim() !== '' ||
        (filterRoleId.value ?? '') !== '' ||
        (filterStatus.value ?? '') !== '' ||
        (filterAreaId.value ?? '') !== '',
);

function applyFilters() {
    const params = {};
    const q = (filterSearch.value ?? '').trim();
    if (q) params.search = q;
    if (filterRoleId.value) params.role_id = filterRoleId.value;
    if (filterStatus.value !== '') params.status = filterStatus.value;
    if (filterAreaId.value) params.area_id = filterAreaId.value;

    router.get(route('users'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters() {
    filterSearch.value = '';
    filterRoleId.value = '';
    filterStatus.value = '';
    filterAreaId.value = '';
    router.get(route('users'), {}, {
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
    area_id: props.areas?.[0]?.id ?? '',
    status: 1,
});

function openCreateDialog() {
    if (props.roles?.length && !form.role_id) {
        form.role_id = props.roles[0].id;
    }
    if (props.areas?.length && !form.area_id) {
        form.area_id = props.areas[0].id;
    }

    form.status = 1;
    createDialogVisible.value = true;
}

function closeCreateDialog() {
    createDialogVisible.value = false;
    form.clearErrors();
    form.reset();

    if (props.roles?.length) {
        form.role_id = props.roles[0].id;
    }
    if (props.areas?.length) {
        form.area_id = props.areas[0].id;
    }
}

function submit() {
    form.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateDialog(),
    });
}

function openEditDialog(user) {
    selectedUserId.value = user.id;
    form.name = user.name ?? '';
    form.ape_pat = user.ape_pat ?? '';
    form.ape_mat = user.ape_mat ?? '';
    form.email = user.email ?? '';
    form.role_id = user.role_id ?? '';
    form.area_id = user.area_id ?? '';
    form.status = user.status ?? 1;
    form.password = null;
    form.password_confirmation = null;
    form.clearErrors();
    editDialogVisible.value = true;
}

function closeEditDialog() {
    editDialogVisible.value = false;
    selectedUserId.value = null;
    form.clearErrors();
    form.reset();

    if (props.roles?.length) {
        form.role_id = props.roles[0].id;
    }
    if (props.areas?.length) {
        form.area_id = props.areas[0].id;
    }
}

function submitEdit() {
    if (!selectedUserId.value) return;

    // Si el usuario no escribe contraseña, la dejamos en `null` para que Laravel no valide.
    if (!form.password) {
        form.password = null;
        form.password_confirmation = null;
    }

    form.patch(route('users.update', selectedUserId.value), {
        preserveScroll: true,
        onSuccess: () => {
            const authId = String(page.props.auth?.user?.id ?? '');
            const editedId = String(selectedUserId.value ?? '');
            toast.add({
                severity: 'success',
                summary: 'Listo',
                detail:
                    authId === editedId
                        ? 'Tus datos se guardaron correctamente.'
                        : 'Cambios guardados. Si el usuario está en línea, recibirá un aviso en su sesión.',
                life: 4000,
            });
            closeEditDialog();
        },
    });
}

function openDeleteDialog(user) {
    selectedUserId.value = user.id;
    deleteDialogVisible.value = true;
}

function closeDeleteDialog() {
    deleteDialogVisible.value = false;
    selectedUserId.value = null;
}

function submitDelete() {
    if (!selectedUserId.value) return;

    router.delete(route('users.destroy', selectedUserId.value), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Eliminado',
                detail: 'El usuario se eliminó correctamente.',
                life: 3000,
            });
            closeDeleteDialog();
        },
    });
}

function toggleUserStatus(user) {
    const nextStatus = user.status == 1 ? 0 : 1;

    selectedUserId.value = user.id;
    form.name = user.name ?? '';
    form.ape_pat = user.ape_pat ?? '';
    form.ape_mat = user.ape_mat ?? '';
    form.email = user.email ?? '';
    form.role_id = user.role_id ?? '';
    form.area_id = user.area_id ?? '';
    form.status = nextStatus;
    form.password = null;
    form.password_confirmation = null;
    form.clearErrors();

    form.patch(route('users.update', selectedUserId.value), {
        preserveScroll: true,
        onSuccess: () => {
            const authId = String(page.props.auth?.user?.id ?? '');
            const editedId = String(user.id ?? '');
            toast.add({
                severity: 'success',
                summary: 'Estado actualizado',
                detail:
                    authId === editedId
                        ? `${user.name ?? 'Usuario'} ahora está ${
                              nextStatus == 1 ? 'activo' : 'inactivo'
                          }.`
                        : `${user.name ?? 'Usuario'} ahora está ${
                              nextStatus == 1 ? 'activo' : 'inactivo'
                          }. Si está conectado, también verá un aviso por notificación.`,
                life: 4000,
            });
            closeEditDialog();
        },
    });
}

function dialItemsFor(user) {
    const nextStatus = user.status == 1 ? 0 : 1;

    return [
        {
            label: 'Editar',
            icon: 'pi pi-pencil',
            command: () => openEditDialog(user),
        },
        {
            label: nextStatus == 1 ? 'Activar' : 'Inactivar',
            icon: nextStatus == 1 ? 'pi pi-check-circle' : 'pi pi-power-off',
            command: () => toggleUserStatus(user),
        },
        {
            label: 'Eliminar',
            icon: 'pi pi-trash',
            command: () => openDeleteDialog(user),
        },
    ];
}
</script>

<template>

    <Head title="Usuarios del Sistema" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Usuarios del Sistema
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-visible rounded-lg bg-white shadow dark:bg-gray-800">

                    <!-- Header de la gestión -->
                    <div
                        class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ users.length }} usuario{{ users.length !== 1 ? 's' : '' }} registrado{{ users.length !==
                            1 ? 's'
                            : '' }}
                        </p>
                        <Button label="Agregar usuario" icon="pi pi-user-plus" class="w-fit " @click="openCreateDialog"
                            severity="secondary" />
                    </div>

                    <!-- Filtros -->
                    <div
                        class="flex flex-col gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-700 sm:flex-row sm:flex-wrap sm:items-end"
                    >
                        <div class="min-w-48 flex-1">
                            <InputLabel value="Buscar" />
                            <InputText
                                v-model="filterSearch"
                                class="mt-1 block w-full"
                                placeholder="Nombre, apellidos o correo"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="min-w-44 sm:w-48">
                            <InputLabel value="Rol" />
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
                        <div class="min-w-44 sm:w-48">
                            <InputLabel value="Área" />
                            <WrappingSelect
                                v-model="filterAreaId"
                                panel-preset="filter"
                                :options="areaFilterOptions"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                filterPlaceholder="Buscar área..."
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

                    <Dialog v-model:visible="createDialogVisible" modal header="Agregar usuario"
                        :style="{ width: '32rem' }" @hide="closeCreateDialog">
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
                                <InputLabel value="Email" />
                                <InputText v-model="form.email" type="email" class="mt-1 block w-full"
                                    autocomplete="email" />
                                <InputError :message="form.errors.email" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel value="Rol" />
                                <Select v-model="form.role_id" :options="props.roles" optionLabel="name"
                                    optionValue="id" class="mt-1 block w-full" />
                                <InputError :message="form.errors.role_id" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel value="Área" />
                                <WrappingSelect
                                    v-model="form.area_id"
                                    panel-preset="!max-w-[min(12rem,calc(100vw-2rem))] min-w-0 overflow-hidden"
                                    :options="props.areas"
                                    optionLabel="name"
                                    optionValue="id"
                                    class="mt-1 block w-full"
                                    placeholder="Selecciona un área"
                                />
                                <InputError :message="form.errors.area_id" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel value="Estado" />
                                <Select v-model="form.status" :options="statusOptions" optionLabel="label"
                                    optionValue="value" class="mt-1 block w-full" />
                                <InputError :message="form.errors.status" class="mt-2" />
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
                                <Button type="submit" label="Guardar" icon="pi pi-check" :disabled="form.processing" />
                            </div>
                        </form>
                    </Dialog>

                    <!-- Editar usuario -->
                    <Dialog
                        v-model:visible="editDialogVisible"
                        modal
                        header="Editar usuario"
                        :style="{ width: '32rem' }"
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

                            <div>
                                <InputLabel value="Rol" />
                                <Select v-model="form.role_id" :options="props.roles" optionLabel="name"
                                    optionValue="id" class="mt-1 block w-full" />
                                <InputError :message="form.errors.role_id" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel value="Área" />
                                <WrappingSelect
                                    v-model="form.area_id"
                                    panel-preset="!max-w-[min(12rem,calc(100vw-2rem))] min-w-0 overflow-hidden"
                                    :options="props.areas"
                                    optionLabel="name"
                                    optionValue="id"
                                    class="mt-1 block w-full"
                                    filter
                                    filterPlaceholder="Buscar área..."
                                />
                                <InputError :message="form.errors.area_id" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel value="Estado" />
                                <Select v-model="form.status" :options="statusOptions" optionLabel="label"
                                    optionValue="value" class="mt-1 block w-full" />
                                <InputError :message="form.errors.status" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Contraseña (opcional)" />
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
                                <Button type="submit" label="Guardar" icon="pi pi-check" :disabled="form.processing" />
                            </div>
                        </form>
                    </Dialog>

                    <!-- Eliminar -->
                    <Dialog
                        v-model:visible="deleteDialogVisible"
                        modal
                        header="Eliminar usuario"
                        :style="{ width: '28rem' }"
                        @hide="closeDeleteDialog"
                    >
                        <div class="space-y-4">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                ¿Seguro que quieres eliminar este usuario?
                            </p>
                            <div class="flex justify-end gap-3 pt-2">
                                <Button type="button" label="Cancelar" class="p-button-text" :disabled="form.processing"
                                    @click="closeDeleteDialog" />
                                <Button
                                    type="button"
                                    label="Eliminar"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    :disabled="form.processing"
                                    @click="submitDelete"
                                />
                            </div>
                        </div>
                    </Dialog>

                    <!-- Lista (sin tabla) -->
                    <div class="grid ">
                        <div v-if="users.length === 0" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ hasActiveFilters ? 'No hay usuarios que coincidan con los filtros.' : 'No hay usuarios registrados.' }}
                        </div>

                        <div v-else class="grid  lg:grid-cols-2">
                            <div
                                v-for="user in users"
                                :key="user.id"
                                class="card bg-base-100 overflow-visible"
                                style="border-radius: 0rem;"
                            >
                                <div class="card-body">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300"
                                            >
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </div>

                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-bold text-base-content">
                                                    {{ user.name }} {{ user.ape_pat }} {{ user.ape_mat }}
                                                </div>
                                                <div class="break-all text-sm text-base-content/70">
                                                    {{ user.email }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-col items-end gap-2">
                                            <div class="flex flex-wrap items-center gap-2 justify-end">
                                                <span class="badge badge-primary badge-outline">
                                                    {{ user.role }}
                                                </span>
                                                <span
                                                    class="badge"
                                                    :class="user.status == 1 ? 'badge-success' : 'badge-error'"
                                                >
                                                    {{ user.status == 1 ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>

                                            <!-- Dial de acciones para el usuario -->
                                            <div
                                                class=" flex items-end justify-end"
                                                style="height: 44px; width: 44px; overflow: visible; z-index: 50"
                                            >
                                                <SpeedDial
                                                    :model="dialItemsFor(user)"
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
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
