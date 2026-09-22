<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const props = defineProps({
    instances: {
        type: Array,
        default: () => [],
    },
    defaults: {
        type: Object,
        default: () => ({
            timezone: 'America/Merida',
            calendar_id: '',
            webhook_url: '',
        }),
    },
    connectResult: {
        type: Object,
        default: null,
    },
});

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const connectDialogVisible = ref(false);
const selectedId = ref(null);
const isConnecting = ref(false);
const isDeleting = ref(false);

const statusOptions = [
    { label: 'Activa', value: 'active' },
    { label: 'Inactiva', value: 'inactive' },
];

const selected = computed(
    () => props.instances.find((i) => i.id === selectedId.value) ?? null,
);

const form = useForm({
    instance_name: '',
    google_calendar_id: '',
    timezone: '',
    system_prompt: '',
    status: 'active',
    create_in_evolution: true,
    webhook_url: '',
    credentials_file: null,
});

const credentialsFileInput = ref(null);
const editCredentialsFileInput = ref(null);

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash?.message) return;
        toast.add({
            severity: flash.type === 'error' ? 'error' : 'success',
            summary: flash.type === 'error' ? 'Error' : 'Listo',
            detail: flash.message,
            life: 4000,
        });
    },
    { immediate: true },
);

watch(
    () => props.connectResult,
    (result) => {
        if (result?.instance_name) {
            connectDialogVisible.value = true;
        }
    },
    { immediate: true },
);

const connectStateLabel = computed(() => {
    const state = props.connectResult?.state;
    if (!state) return '—';
    return (
        state?.instance?.state ??
        state?.state ??
        JSON.stringify(state)
    );
});

function onCredentialsSelected(event, target = 'create') {
    const file = event?.target?.files?.[0] ?? null;
    form.credentials_file = file;
}

function openCreateDialog() {
    selectedId.value = null;
    form.reset();
    form.instance_name = '';
    form.google_calendar_id = props.defaults.calendar_id ?? '';
    form.timezone = props.defaults.timezone ?? 'America/Merida';
    form.system_prompt = '';
    form.status = 'active';
    form.create_in_evolution = true;
    form.webhook_url = props.defaults.webhook_url ?? '';
    form.credentials_file = null;
    if (credentialsFileInput.value) credentialsFileInput.value.value = '';
    form.clearErrors();
    createDialogVisible.value = true;
}

function closeCreateDialog() {
    createDialogVisible.value = false;
    form.clearErrors();
    form.reset();
    form.credentials_file = null;
    if (credentialsFileInput.value) credentialsFileInput.value.value = '';
}

function submitCreate() {
    form.post(route('whatsapp.instances.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeCreateDialog(),
    });
}

function openEditDialog(id) {
    const row = props.instances.find((i) => i.id === id);
    if (!row) return;
    selectedId.value = id;
    form.clearErrors();
    form.instance_name = row.instance_name;
    form.google_calendar_id = row.google_calendar_id;
    form.timezone = row.timezone;
    form.system_prompt = row.system_prompt ?? '';
    form.status = row.status || 'active';
    form.credentials_file = null;
    if (editCredentialsFileInput.value) editCredentialsFileInput.value.value = '';
    editDialogVisible.value = true;
}

function closeEditDialog() {
    editDialogVisible.value = false;
    form.clearErrors();
    form.credentials_file = null;
    if (editCredentialsFileInput.value) editCredentialsFileInput.value.value = '';
}

function submitEdit() {
    if (!selectedId.value) return;
    form.post(route('whatsapp.instances.update', selectedId.value), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeEditDialog(),
    });
}

function openDeleteDialog(id) {
    selectedId.value = id;
    deleteDialogVisible.value = true;
}

function closeDeleteDialog() {
    deleteDialogVisible.value = false;
}

function submitDelete() {
    if (!selectedId.value) return;
    isDeleting.value = true;
    router.delete(route('whatsapp.instances.destroy', selectedId.value), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            closeDeleteDialog();
        },
    });
}

function requestConnect(id) {
    isConnecting.value = true;
    router.post(
        route('whatsapp.instances.connect', id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isConnecting.value = false;
            },
        },
    );
}

function closeConnectDialog() {
    connectDialogVisible.value = false;
}
</script>

<template>
    <Head title="Instancias WhatsApp" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-base-content">
                Instancias WhatsApp
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
                    <div
                        class="flex flex-col gap-3 border-b border-base-300 p-6 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <h3 class="text-lg font-medium text-base-content">
                                Instancias
                            </h3>
                            <p class="mt-1 text-sm text-base-content/60">
                                {{ instances.length }} registrada{{
                                    instances.length !== 1 ? 's' : ''
                                }}. Gestiona Evolution, Calendar y prompt.
                            </p>
                        </div>
                        <Button
                            label="Nueva instancia"
                            icon="pi pi-plus"
                            severity="secondary"
                            @click="openCreateDialog"
                        />
                    </div>

                    <div class="overflow-x-auto p-6">
                        <table class="min-w-full divide-y divide-base-300 text-sm">
                            <thead>
                                <tr class="text-left text-base-content/60">
                                    <th class="px-3 py-2 font-medium">Nombre</th>
                                    <th class="px-3 py-2 font-medium">Calendar ID</th>
                                    <th class="px-3 py-2 font-medium">Credenciales</th>
                                    <th class="px-3 py-2 font-medium">Timezone</th>
                                    <th class="px-3 py-2 font-medium">Estado</th>
                                    <th class="px-3 py-2 font-medium text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-300">
                                <tr
                                    v-for="row in instances"
                                    :key="row.id"
                                    class="text-base-content"
                                >
                                    <td class="px-3 py-3 font-medium">
                                        {{ row.instance_name }}
                                    </td>
                                    <td
                                        class="max-w-[14rem] truncate px-3 py-3 text-base-content/70"
                                        :title="row.google_calendar_id"
                                    >
                                        {{ row.google_calendar_id || '—' }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                            :class="
                                                row.has_credentials
                                                    ? 'bg-success/15 text-success'
                                                    : 'bg-error/15 text-error'
                                            "
                                            :title="row.google_service_email || ''"
                                        >
                                            {{
                                                row.has_credentials
                                                    ? 'JSON OK'
                                                    : 'Sin JSON'
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        {{ row.timezone || '—' }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                            :class="
                                                row.status === 'active'
                                                    ? 'bg-success/15 text-success'
                                                    : 'bg-base-300 text-base-content/60'
                                            "
                                        >
                                            {{ row.status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div
                                            class="flex flex-wrap justify-end gap-2"
                                        >
                                            <Button
                                                label="QR / Estado"
                                                icon="pi pi-qrcode"
                                                size="small"
                                                severity="secondary"
                                                :loading="isConnecting"
                                                @click="requestConnect(row.id)"
                                            />
                                            <Button
                                                icon="pi pi-pencil"
                                                size="small"
                                                text
                                                rounded
                                                @click="openEditDialog(row.id)"
                                            />
                                            <Button
                                                icon="pi pi-trash"
                                                size="small"
                                                text
                                                rounded
                                                severity="danger"
                                                @click="openDeleteDialog(row.id)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            v-if="!instances.length"
                            class="py-10 text-center text-sm text-base-content/60"
                        >
                            No hay instancias. Crea una para vincular WhatsApp.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crear -->
        <Dialog
            v-model:visible="createDialogVisible"
            modal
            header="Nueva instancia"
            :style="{ width: '36rem' }"
            @hide="closeCreateDialog"
        >
            <form class="space-y-4" @submit.prevent="submitCreate">
                <div>
                    <InputLabel value="Nombre (Evolution)" />
                    <InputText
                        v-model="form.instance_name"
                        class="mt-1 block w-full"
                        placeholder="instancia_local"
                    />
                    <InputError :message="form.errors.instance_name" class="mt-2" />
                </div>
                <div>
                    <InputLabel value="Google Calendar ID" />
                    <InputText
                        v-model="form.google_calendar_id"
                        class="mt-1 block w-full"
                    />
                    <InputError
                        :message="form.errors.google_calendar_id"
                        class="mt-2"
                    />
                </div>
                <div>
                    <InputLabel value="JSON cuenta de servicio (Google)" />
                    <input
                        ref="credentialsFileInput"
                        type="file"
                        accept=".json,application/json"
                        class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-base-200 file:px-3 file:py-2 file:text-sm"
                        @change="onCredentialsSelected($event, 'create')"
                    />
                    <p class="mt-1 text-xs text-base-content/50">
                        Un JSON por instancia. Comparte el calendario con el
                        client_email de ese archivo.
                    </p>
                    <InputError
                        :message="form.errors.credentials_file"
                        class="mt-2"
                    />
                </div>
                <div>
                    <InputLabel value="Timezone" />
                    <InputText v-model="form.timezone" class="mt-1 block w-full" />
                    <InputError :message="form.errors.timezone" class="mt-2" />
                </div>
                <div>
                    <InputLabel value="System prompt (opcional)" />
                    <Textarea
                        v-model="form.system_prompt"
                        class="mt-1 block w-full"
                        rows="4"
                        autoResize
                    />
                    <InputError
                        :message="form.errors.system_prompt"
                        class="mt-2"
                    />
                </div>
                <div>
                    <InputLabel value="Webhook URL (opcional)" />
                    <InputText
                        v-model="form.webhook_url"
                        class="mt-1 block w-full"
                        placeholder="http://laravel.test/api/evolution/webhook"
                    />
                    <InputError :message="form.errors.webhook_url" class="mt-2" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox
                        v-model="form.create_in_evolution"
                        inputId="create_evo"
                        binary
                    />
                    <label for="create_evo" class="text-sm text-base-content">
                        Crear también en Evolution API
                    </label>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text"
                        :disabled="form.processing"
                        @click="closeCreateDialog"
                    />
                    <Button
                        type="submit"
                        label="Guardar"
                        icon="pi pi-check"
                        :disabled="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Editar -->
        <Dialog
            v-model:visible="editDialogVisible"
            modal
            header="Editar instancia"
            :style="{ width: '36rem' }"
            @hide="closeEditDialog"
        >
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <InputLabel value="Nombre" />
                    <InputText
                        :model-value="form.instance_name"
                        class="mt-1 block w-full"
                        disabled
                    />
                </div>
                <div>
                    <InputLabel value="Google Calendar ID" />
                    <InputText
                        v-model="form.google_calendar_id"
                        class="mt-1 block w-full"
                    />
                    <InputError
                        :message="form.errors.google_calendar_id"
                        class="mt-2"
                    />
                </div>
                <div>
                    <InputLabel value="JSON cuenta de servicio (opcional)" />
                    <p
                        v-if="selected?.google_service_email"
                        class="mt-1 text-xs text-base-content/60"
                    >
                        Actual:
                        {{ selected.google_service_email }}
                        <template v-if="selected.has_credentials"> · cargado</template>
                    </p>
                    <input
                        ref="editCredentialsFileInput"
                        type="file"
                        accept=".json,application/json"
                        class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-base-200 file:px-3 file:py-2 file:text-sm"
                        @change="onCredentialsSelected($event, 'edit')"
                    />
                    <p class="mt-1 text-xs text-base-content/50">
                        Déjalo vacío para conservar el JSON actual.
                    </p>
                    <InputError
                        :message="form.errors.credentials_file"
                        class="mt-2"
                    />
                </div>
                <div>
                    <InputLabel value="Timezone" />
                    <InputText v-model="form.timezone" class="mt-1 block w-full" />
                    <InputError :message="form.errors.timezone" class="mt-2" />
                </div>
                <div>
                    <InputLabel value="Estado" />
                    <Select
                        v-model="form.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.status" class="mt-2" />
                </div>
                <div>
                    <InputLabel value="System prompt" />
                    <Textarea
                        v-model="form.system_prompt"
                        class="mt-1 block w-full"
                        rows="4"
                        autoResize
                    />
                    <InputError
                        :message="form.errors.system_prompt"
                        class="mt-2"
                    />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text"
                        :disabled="form.processing"
                        @click="closeEditDialog"
                    />
                    <Button
                        type="submit"
                        label="Guardar"
                        icon="pi pi-check"
                        :disabled="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Eliminar -->
        <Dialog
            v-model:visible="deleteDialogVisible"
            modal
            header="Eliminar instancia"
            :style="{ width: '28rem' }"
            @hide="closeDeleteDialog"
        >
            <div class="space-y-4">
                <p class="text-sm text-base-content/70">
                    ¿Eliminar
                    <strong>{{ selected?.instance_name ?? 'esta instancia' }}</strong>
                    de Mongo? La instancia en Evolution no se elimina.
                </p>
                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text"
                        :disabled="isDeleting"
                        @click="closeDeleteDialog"
                    />
                    <Button
                        type="button"
                        label="Eliminar"
                        icon="pi pi-trash"
                        severity="danger"
                        :disabled="isDeleting"
                        @click="submitDelete"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Connect / QR -->
        <Dialog
            v-model:visible="connectDialogVisible"
            modal
            :header="`Conexión: ${connectResult?.instance_name ?? ''}`"
            :style="{ width: '28rem' }"
            @hide="closeConnectDialog"
        >
            <div class="space-y-4">
                <p class="text-sm text-base-content/70">
                    Estado:
                    <strong class="text-base-content">{{ connectStateLabel }}</strong>
                </p>
                <div v-if="connectResult?.qr_base64" class="flex justify-center">
                    <img
                        :src="connectResult.qr_base64"
                        alt="QR WhatsApp"
                        class="max-h-64 rounded border border-base-300"
                    />
                </div>
                <p
                    v-else
                    class="text-center text-sm text-base-content/60"
                >
                    Sin QR en la respuesta (puede que ya esté conectada).
                </p>
                <div class="flex justify-end">
                    <Button label="Cerrar" @click="closeConnectDialog" />
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
