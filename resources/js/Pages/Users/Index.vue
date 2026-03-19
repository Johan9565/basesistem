<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Password from 'primevue/password';
import SpeedDial from 'primevue/speeddial';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

const props = defineProps({
    users: Array,
    roles: {
        type: Array,
        default: () => [],
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

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: props.roles?.[0]?.id ?? '',
    status: 1,
});

function openCreateDialog() {
    if (props.roles?.length && !form.role_id) {
        form.role_id = props.roles[0].id;
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
    form.email = user.email ?? '';
    form.role_id = user.role_id ?? '';
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
            toast.add({
                severity: 'success',
                summary: 'Actualizado',
                detail: 'El usuario se actualizó correctamente.',
                life: 3000,
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
    form.email = user.email ?? '';
    form.role_id = user.role_id ?? '';
    form.status = nextStatus;
    form.password = null;
    form.password_confirmation = null;
    form.clearErrors();

    form.patch(route('users.update', selectedUserId.value), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Estado actualizado',
                detail: `${user.name ?? 'Usuario'} ahora está ${
                    nextStatus == 1 ? 'Activo' : 'Inactivo'
                }.`,
                life: 3000,
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

                    <Dialog v-model:visible="createDialogVisible" modal header="Agregar usuario"
                        :style="{ width: '32rem' }" @hide="closeCreateDialog">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <InputLabel value="Nombre" />
                                <InputText v-model="form.name" class="mt-1 block w-full" autocomplete="name" />
                                <InputError :message="form.errors.name" class="mt-2" />
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
                                <InputText v-model="form.name" class="mt-1 block w-full" autocomplete="name" />
                                <InputError :message="form.errors.name" class="mt-2" />
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
                            No hay usuarios registrados.
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
                                                    {{ user.name }}
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
