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
            telegram_webhook_base: '',
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
const duplicateDialogVisible = ref(false);
const connectDialogVisible = ref(false);
const selectedId = ref(null);
const isConnecting = ref(false);
const isDeleting = ref(false);
const editTab = ref('negocio');
const createTab = ref('negocio');

const duplicateForm = useForm({
    instance_name: '',
    business_name: '',
    copy_telegram: false,
});

const formTabs = [
    { id: 'negocio', label: 'Negocio', icon: 'pi pi-building', hint: 'Nombre y tono del bot' },
    { id: 'catalogo', label: 'Catálogo', icon: 'pi pi-list', hint: 'Lo que el bot puede ofrecer' },
    { id: 'tecnico', label: 'Conexión', icon: 'pi pi-cog', hint: 'Calendar, IA y estado' },
    { id: 'telegram', label: 'Telegram', icon: 'pi pi-send', hint: 'Bot admin por instancia' },
];

const timezoneOptions = [
    { label: 'Mérida (America/Merida)', value: 'America/Merida' },
    { label: 'Ciudad de México (America/Mexico_City)', value: 'America/Mexico_City' },
    { label: 'Cancún (America/Cancun)', value: 'America/Cancun' },
    { label: 'Monterrey (America/Monterrey)', value: 'America/Monterrey' },
    { label: 'Tijuana (America/Tijuana)', value: 'America/Tijuana' },
];

const statusOptions = [
    { label: 'Activa — el bot responde', value: 'active' },
    { label: 'Inactiva — no procesa mensajes', value: 'inactive' },
];

const aiProviderOptions = [
    { label: 'DeepSeek', value: 'deepseek' },
    { label: 'Xiaomi MiMo Flash', value: 'mimo' },
];

const selected = computed(
    () => props.instances.find((i) => i.id === selectedId.value) ?? null,
);

const catalogFilledCount = computed(() => {
    const fields = [
        form.services,
        form.business_hours,
        form.prices,
        form.promotions,
        form.locations,
    ];
    return fields.filter((v) => String(v || '').trim() !== '').length;
});

const form = useForm({
    instance_name: '',
    google_calendar_id: '',
    timezone: '',
    system_prompt: '',
    business_name: '',
    services: '',
    business_hours: '',
    prices: '',
    promotions: '',
    locations: '',
    ai_provider: 'deepseek',
    status: 'active',
    create_in_evolution: true,
    webhook_url: '',
    credentials_file: null,
    telegram_bot_token: '',
    telegram_allowed_user_ids: '',
    register_telegram_webhook: true,
    clear_telegram_bot: false,
    regenerate_telegram_link_code: false,
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
    createTab.value = 'negocio';
    form.reset();
    form.instance_name = '';
    form.google_calendar_id = props.defaults.calendar_id ?? '';
    form.timezone = props.defaults.timezone ?? 'America/Merida';
    form.system_prompt = '';
    form.business_name = '';
    form.services = '';
    form.business_hours = '';
    form.prices = '';
    form.promotions = '';
    form.locations = '';
    form.ai_provider = 'deepseek';
    form.status = 'active';
    form.create_in_evolution = true;
    form.webhook_url = props.defaults.webhook_url ?? '';
    form.credentials_file = null;
    form.telegram_bot_token = '';
    form.telegram_allowed_user_ids = '';
    form.register_telegram_webhook = true;
    form.clear_telegram_bot = false;
    form.regenerate_telegram_link_code = false;
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
    editTab.value = 'negocio';
    form.clearErrors();
    form.instance_name = row.instance_name;
    form.google_calendar_id = row.google_calendar_id;
    form.timezone = row.timezone;
    form.system_prompt = row.system_prompt ?? '';
    form.business_name = row.business_name ?? '';
    form.services = row.services ?? '';
    form.business_hours = row.business_hours ?? '';
    form.prices = row.prices ?? '';
    form.promotions = row.promotions ?? '';
    form.locations = row.locations ?? '';
    form.ai_provider = row.ai_provider || 'deepseek';
    form.status = row.status || 'active';
    form.credentials_file = null;
    form.telegram_bot_token = '';
    form.telegram_allowed_user_ids = row.telegram_allowed_user_ids ?? '';
    form.register_telegram_webhook = false;
    form.clear_telegram_bot = false;
    form.regenerate_telegram_link_code = false;
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

function openDuplicateDialog(id) {
    const row = props.instances.find((i) => i.id === id);
    if (!row) return;
    selectedId.value = id;
    duplicateForm.clearErrors();
    duplicateForm.instance_name = `${row.instance_name}_exp`;
    duplicateForm.business_name = row.business_name
        ? `${row.business_name} (prueba)`
        : '';
    duplicateForm.copy_telegram = false;
    duplicateDialogVisible.value = true;
}

function closeDuplicateDialog() {
    duplicateDialogVisible.value = false;
    duplicateForm.reset();
    duplicateForm.clearErrors();
}

function submitDuplicate() {
    if (!selectedId.value) return;
    duplicateForm.post(route('whatsapp.instances.duplicate', selectedId.value), {
        preserveScroll: true,
        onSuccess: () => closeDuplicateDialog(),
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
                                    <th class="px-3 py-2 font-medium">IA</th>
                                    <th class="px-3 py-2 font-medium">Telegram</th>
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
                                        <div>{{ row.instance_name }}</div>
                                        <div
                                            v-if="row.business_name"
                                            class="mt-0.5 text-xs font-normal text-base-content/50"
                                        >
                                            {{ row.business_name }}
                                        </div>
                                        <div
                                            v-if="row.shares_session"
                                            class="mt-0.5 text-xs font-normal text-info"
                                        >
                                            Sesión WA: {{ row.evolution_instance_name }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                            :class="
                                                row.ai_provider === 'mimo'
                                                    ? 'bg-info/15 text-info'
                                                    : 'bg-base-300 text-base-content/70'
                                            "
                                        >
                                            {{
                                                row.ai_provider === 'mimo'
                                                    ? 'MiMo Flash'
                                                    : 'DeepSeek'
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            v-if="row.has_telegram_bot"
                                            class="inline-flex rounded-full bg-success/15 px-2 py-0.5 text-xs text-success"
                                        >
                                            {{
                                                row.telegram_bot_username
                                                    ? `@${row.telegram_bot_username}`
                                                    : 'Activo'
                                            }}
                                        </span>
                                        <span v-else class="text-xs text-base-content/40">—</span>
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
                                                icon="pi pi-copy"
                                                size="small"
                                                text
                                                rounded
                                                title="Duplicar perfil (misma sesión WA)"
                                                @click="openDuplicateDialog(row.id)"
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
            class="w-[min(96vw,80rem)]"
            :style="{ width: 'min(96vw, 80rem)' }"
            :contentStyle="{ maxHeight: '85vh', overflowY: 'auto', paddingTop: '0.75rem' }"
            @hide="closeCreateDialog"
        >
            <form class="space-y-5" @submit.prevent="submitCreate">
                <div class="grid grid-cols-3 gap-2 rounded-xl bg-base-200/60 p-1">
                    <button
                        v-for="tab in formTabs"
                        :key="`create-${tab.id}`"
                        type="button"
                        class="rounded-lg px-2 py-2.5 text-center transition"
                        :class="
                            createTab === tab.id
                                ? 'bg-base-100 text-base-content shadow-sm'
                                : 'text-base-content/55 hover:text-base-content'
                        "
                        @click="createTab = tab.id"
                    >
                        <i :class="[tab.icon, 'mb-1 block text-sm']" />
                        <span class="block text-xs font-medium sm:text-sm">{{ tab.label }}</span>
                    </button>
                </div>

                <div v-show="createTab === 'negocio'" class="space-y-4">
                    <p class="text-sm text-base-content/60">
                        Cómo se identifica el negocio y cómo habla el asistente.
                    </p>
                    <div>
                        <InputLabel value="Nombre técnico (Evolution)" />
                        <InputText
                            v-model="form.instance_name"
                            class="mt-1 block w-full"
                            placeholder="mi_negocio_wa"
                        />
                        <p class="mt-1 text-xs text-base-content/45">
                            Solo letras, números, guion y guion bajo. No se puede cambiar después.
                        </p>
                        <InputError :message="form.errors.instance_name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Nombre comercial" />
                        <InputText
                            v-model="form.business_name"
                            class="mt-1 block w-full"
                            placeholder="Flying Dress Cancún"
                        />
                        <p class="mt-1 text-xs text-base-content/45">
                            Así se presenta el bot: “asistente oficial de…”.
                        </p>
                        <InputError :message="form.errors.business_name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Instrucciones extra (opcional)" />
                        <Textarea
                            v-model="form.system_prompt"
                            class="mt-1 block w-full"
                            rows="4"
                            autoResize
                            placeholder="Tono, políticas de anticipación, excepciones, qué no debe prometer…"
                        />
                        <InputError :message="form.errors.system_prompt" class="mt-2" />
                    </div>
                </div>

                <div v-show="createTab === 'catalogo'" class="space-y-4">
                    <p class="text-sm text-base-content/60">
                        Catálogo que el bot usa para responder dudas y agendar. Sé concreto.
                    </p>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <InputLabel value="Paquetes / servicios" />
                            <Textarea
                                v-model="form.services"
                                class="mt-1 block w-full"
                                rows="7"
                                autoResize
                                placeholder="Flying Dress — sesión en playa&#10;Flying Dron — sesión con dron&#10;SILVER PACKAGE — incluye vestido + 20 fotos"
                            />
                            <InputError :message="form.errors.services" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel value="Ubicaciones / zonas" />
                            <Textarea
                                v-model="form.locations"
                                class="mt-1 block w-full"
                                rows="7"
                                autoResize
                                placeholder="Playa pública — Cancún&#10;Playa pública — Puerto Morelos&#10;Playa pública — Playa del Carmen"
                            />
                            <p class="mt-1 text-xs text-base-content/45">
                                Incluye ciudad o localidad; el bot la usará en el resumen de la cita.
                            </p>
                            <InputError :message="form.errors.locations" class="mt-2" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <InputLabel value="Horarios de atención" />
                            <Textarea
                                v-model="form.business_hours"
                                class="mt-1 block w-full"
                                rows="4"
                                autoResize
                                placeholder="Lun–Vie 9:00–18:00&#10;Sáb 9:00–14:00"
                            />
                            <InputError :message="form.errors.business_hours" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel value="Precios / tarifas" />
                            <Textarea
                                v-model="form.prices"
                                class="mt-1 block w-full"
                                rows="4"
                                autoResize
                                placeholder="Flying Dress desde $2,500&#10;Flying Dron desde $3,200"
                            />
                            <InputError :message="form.errors.prices" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-1">
                            <InputLabel value="Promociones" />
                            <Textarea
                                v-model="form.promotions"
                                class="mt-1 block w-full"
                                rows="4"
                                autoResize
                                placeholder="10% en reserva anticipada de 7 días…"
                            />
                            <InputError :message="form.errors.promotions" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div v-show="createTab === 'tecnico'" class="space-y-4">
                    <p class="text-sm text-base-content/60">
                        Google Calendar, zona horaria e inteligencia artificial.
                    </p>
                    <div>
                        <InputLabel value="Google Calendar ID" />
                        <InputText
                            v-model="form.google_calendar_id"
                            class="mt-1 block w-full"
                            placeholder="correo@gmail.com o ID del calendario"
                        />
                        <InputError
                            :message="form.errors.google_calendar_id"
                            class="mt-2"
                        />
                    </div>
                    <div>
                        <InputLabel value="JSON de cuenta de servicio (Google)" />
                        <input
                            ref="credentialsFileInput"
                            type="file"
                            accept=".json,application/json"
                            class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-base-200 file:px-3 file:py-2 file:text-sm"
                            @change="onCredentialsSelected($event, 'create')"
                        />
                        <p class="mt-1 text-xs text-base-content/45">
                            Comparte el calendario con el <code>client_email</code> de ese JSON.
                        </p>
                        <InputError
                            :message="form.errors.credentials_file"
                            class="mt-2"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Zona horaria" />
                            <Select
                                v-model="form.timezone"
                                :options="timezoneOptions"
                                optionLabel="label"
                                optionValue="value"
                                editable
                                class="mt-1 block w-full"
                                placeholder="America/Cancun"
                            />
                            <InputError :message="form.errors.timezone" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel value="Proveedor IA" />
                            <Select
                                v-model="form.ai_provider"
                                :options="aiProviderOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.ai_provider" class="mt-2" />
                        </div>
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
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-base-300 bg-base-200/40 p-3">
                        <Checkbox
                            v-model="form.create_in_evolution"
                            inputId="create_evo"
                            binary
                            class="mt-0.5"
                        />
                        <span>
                            <span class="block text-sm font-medium">Crear también en Evolution</span>
                            <span class="mt-0.5 block text-xs text-base-content/50">
                                Si ya existe en Evolution, desmarca esta opción.
                            </span>
                        </span>
                    </label>
                </div>

                <div v-show="createTab === 'telegram'" class="space-y-4">
                    <p class="text-sm text-base-content/60">
                        Bot de Telegram exclusivo de esta instancia para que el admin consulte agenda con IA
                        (citas, ocupación, paquetes, bloquear disponibilidad).
                    </p>
                    <div>
                        <InputLabel value="Token del bot (BotFather)" />
                        <InputText
                            v-model="form.telegram_bot_token"
                            class="mt-1 block w-full"
                            placeholder="123456:AA..."
                            autocomplete="off"
                        />
                        <InputError
                            :message="form.errors.telegram_bot_token"
                            class="mt-2"
                        />
                    </div>
                    <div>
                        <InputLabel value="Telegram user ids permitidos (opcional)" />
                        <InputText
                            v-model="form.telegram_allowed_user_ids"
                            class="mt-1 block w-full"
                            placeholder="123456789, 987654321"
                        />
                        <p class="mt-1 text-xs text-base-content/45">
                            También pueden vincularse con /start y el código que se genera al guardar.
                        </p>
                        <InputError
                            :message="form.errors.telegram_allowed_user_ids"
                            class="mt-2"
                        />
                    </div>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-base-300 bg-base-200/40 p-3">
                        <Checkbox
                            v-model="form.register_telegram_webhook"
                            inputId="create_tg_hook"
                            binary
                            class="mt-0.5"
                        />
                        <span>
                            <span class="block text-sm font-medium">Registrar webhook ahora</span>
                            <span class="mt-0.5 block text-xs text-base-content/50">
                                Requiere URL pública
                                ({{ props.defaults.telegram_webhook_base || 'APP_URL' }}/api/telegram/…/webhook).
                            </span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4">
                    <p class="text-xs text-base-content/45">
                        Catálogo: {{ catalogFilledCount }}/5 secciones con contenido
                    </p>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            label="Cancelar"
                            class="p-button-text"
                            :disabled="form.processing"
                            @click="closeCreateDialog"
                        />
                        <Button
                            type="submit"
                            label="Crear instancia"
                            icon="pi pi-check"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
            </form>
        </Dialog>

        <!-- Editar -->
        <Dialog
            v-model:visible="editDialogVisible"
            modal
            class="w-[min(96vw,80rem)]"
            :style="{ width: 'min(96vw, 80rem)' }"
            :contentStyle="{ maxHeight: '85vh', overflowY: 'auto', paddingTop: '0.5rem' }"
            @hide="closeEditDialog"
        >
            <template #header>
                <div class="min-w-0 pr-6">
                    <p class="text-lg font-semibold text-base-content">
                        Editar · {{ form.instance_name || 'instancia' }}
                    </p>
                    <p class="mt-0.5 truncate text-sm font-normal text-base-content/55">
                        {{ form.business_name || 'Sin nombre comercial' }}
                        ·
                        {{
                            form.status === 'active' ? 'Bot activo' : 'Bot inactivo'
                        }}
                    </p>
                </div>
            </template>

            <form class="space-y-5" @submit.prevent="submitEdit">
                <div class="grid grid-cols-3 gap-2 rounded-xl bg-base-200/60 p-1">
                    <button
                        v-for="tab in formTabs"
                        :key="`edit-${tab.id}`"
                        type="button"
                        class="rounded-lg px-2 py-2.5 text-center transition"
                        :class="
                            editTab === tab.id
                                ? 'bg-base-100 text-base-content shadow-sm'
                                : 'text-base-content/55 hover:text-base-content'
                        "
                        @click="editTab = tab.id"
                    >
                        <i :class="[tab.icon, 'mb-1 block text-sm']" />
                        <span class="block text-xs font-medium sm:text-sm">{{ tab.label }}</span>
                        <span class="mt-0.5 hidden text-[10px] text-base-content/40 sm:block">
                            {{ tab.hint }}
                        </span>
                    </button>
                </div>

                <!-- Negocio -->
                <div v-show="editTab === 'negocio'" class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,16rem)_1fr]">
                        <div class="rounded-xl border border-base-300 bg-base-200/30 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-base-content/45">
                                Instancia Evolution
                            </p>
                            <p class="mt-1 break-all font-mono text-sm text-base-content">
                                {{ form.instance_name }}
                            </p>
                            <p class="mt-1 text-xs text-base-content/45">
                                El nombre técnico no se puede cambiar.
                            </p>
                        </div>
                        <div>
                            <InputLabel value="Nombre comercial" />
                            <InputText
                                v-model="form.business_name"
                                class="mt-1 block w-full"
                                placeholder="Flying Dress Cancún"
                            />
                            <p class="mt-1 text-xs text-base-content/45">
                                Nombre con el que el bot se presenta ante el cliente.
                            </p>
                            <InputError :message="form.errors.business_name" class="mt-2" />
                        </div>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-[1fr_minmax(0,18rem)]">
                        <div>
                            <InputLabel value="Instrucciones extra" />
                            <Textarea
                                v-model="form.system_prompt"
                                class="mt-1 block w-full"
                                rows="6"
                                autoResize
                                placeholder="Ej. No ofrecer descuentos no listados. Pedir anticipo del 50%. Sesiones solo en playa pública…"
                            />
                            <p class="mt-1 text-xs text-base-content/45">
                                Políticas y tono adicionales al flujo por etapas del bot.
                            </p>
                            <InputError
                                :message="form.errors.system_prompt"
                                class="mt-2"
                            />
                        </div>
                        <div>
                            <InputLabel value="Estado del bot" />
                            <Select
                                v-model="form.status"
                                :options="statusOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.status" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Catálogo -->
                <div v-show="editTab === 'catalogo'" class="space-y-4">
                    <div class="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-base-content/70">
                        Esto es lo que el bot puede citar al cliente. Si está vacío o genérico,
                        inventará menos y preguntará más.
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <InputLabel value="Paquetes / servicios" />
                            <Textarea
                                v-model="form.services"
                                class="mt-1 block w-full"
                                rows="8"
                                autoResize
                                placeholder="Flying Dress&#10;Flying Dron&#10;SILVER PACKAGE — vestido + 20 fotos editadas"
                            />
                            <p class="mt-1 text-xs text-base-content/45">
                                Un renglón por paquete. El bot no volverá a preguntar el tipo si ya lo eligió el cliente.
                            </p>
                            <InputError :message="form.errors.services" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel value="Ubicaciones / zonas" />
                            <Textarea
                                v-model="form.locations"
                                class="mt-1 block w-full"
                                rows="8"
                                autoResize
                                placeholder="Playa pública — Cancún&#10;Playa pública — Puerto Morelos&#10;Playa pública — Playa del Carmen&#10;Hotel (bajo solicitud)"
                            />
                            <p class="mt-1 text-xs text-base-content/45">
                                Escribe tipo de lugar <strong>y</strong> ciudad. Así el resumen no queda solo en “Playa pública”.
                            </p>
                            <InputError :message="form.errors.locations" class="mt-2" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <InputLabel value="Horarios de atención" />
                            <Textarea
                                v-model="form.business_hours"
                                class="mt-1 block w-full"
                                rows="5"
                                autoResize
                                placeholder="Lun–Vie 8:00–18:00&#10;Sáb 8:00–14:00&#10;Domingo cerrado"
                            />
                            <InputError :message="form.errors.business_hours" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel value="Precios / tarifas" />
                            <Textarea
                                v-model="form.prices"
                                class="mt-1 block w-full"
                                rows="5"
                                autoResize
                                placeholder="Flying Dress $2,500&#10;Flying Dron $3,200&#10;Traslado fuera de zona +$400"
                            />
                            <InputError :message="form.errors.prices" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-1">
                            <InputLabel value="Promociones" />
                            <Textarea
                                v-model="form.promotions"
                                class="mt-1 block w-full"
                                rows="5"
                                autoResize
                                placeholder="Combo vestido + dron con 10% de descuento…"
                            />
                            <InputError :message="form.errors.promotions" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Técnico -->
                <div v-show="editTab === 'tecnico'" class="space-y-4">
                    <div>
                        <InputLabel value="Google Calendar ID" />
                        <InputText
                            v-model="form.google_calendar_id"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-base-content/45">
                            Correo del calendario o ID compartido con la cuenta de servicio.
                        </p>
                        <InputError
                            :message="form.errors.google_calendar_id"
                            class="mt-2"
                        />
                    </div>
                    <div class="rounded-xl border border-base-300 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-base-content">
                                    Credenciales Google
                                </p>
                                <p
                                    v-if="selected?.google_service_email"
                                    class="mt-1 break-all text-xs text-base-content/60"
                                >
                                    {{ selected.google_service_email }}
                                </p>
                                <p
                                    v-else
                                    class="mt-1 text-xs text-error"
                                >
                                    Sin JSON cargado
                                </p>
                            </div>
                            <span
                                class="badge badge-sm"
                                :class="
                                    selected?.has_credentials
                                        ? 'badge-success'
                                        : 'badge-error'
                                "
                            >
                                {{ selected?.has_credentials ? 'Listo' : 'Falta' }}
                            </span>
                        </div>
                        <InputLabel
                            class="mt-3"
                            value="Reemplazar JSON (opcional)"
                        />
                        <input
                            ref="editCredentialsFileInput"
                            type="file"
                            accept=".json,application/json"
                            class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-base-200 file:px-3 file:py-2 file:text-sm"
                            @change="onCredentialsSelected($event, 'edit')"
                        />
                        <p class="mt-1 text-xs text-base-content/45">
                            Déjalo vacío para conservar el archivo actual.
                        </p>
                        <InputError
                            :message="form.errors.credentials_file"
                            class="mt-2"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Zona horaria" />
                            <Select
                                v-model="form.timezone"
                                :options="timezoneOptions"
                                optionLabel="label"
                                optionValue="value"
                                editable
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.timezone" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel value="Proveedor IA" />
                            <Select
                                v-model="form.ai_provider"
                                :options="aiProviderOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.ai_provider" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div v-show="editTab === 'telegram'" class="space-y-4">
                    <p class="text-sm text-base-content/60">
                        Asistente admin por Telegram (agenda con IA). Un bot por instancia de WhatsApp.
                    </p>
                    <div
                        v-if="selected?.has_telegram_bot"
                        class="rounded-xl border border-base-300 bg-base-200/40 p-3 text-sm"
                    >
                        <p>
                            Bot:
                            <strong v-if="selected.telegram_bot_username">
                                @{{ selected.telegram_bot_username }}
                            </strong>
                            <span v-else>configurado</span>
                            <span class="text-base-content/50">
                                ({{ selected.telegram_bot_token_hint }})
                            </span>
                        </p>
                        <p v-if="selected.telegram_link_code" class="mt-2">
                            Código /start:
                            <code class="rounded bg-base-300 px-1.5 py-0.5">{{
                                selected.telegram_link_code
                            }}</code>
                        </p>
                        <p
                            v-if="selected.telegram_webhook_url"
                            class="mt-2 break-all text-xs text-base-content/50"
                        >
                            {{ selected.telegram_webhook_url }}
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Token del bot (dejar vacío para conservar)" />
                        <InputText
                            v-model="form.telegram_bot_token"
                            class="mt-1 block w-full"
                            placeholder="Pegar nuevo token solo si cambias"
                            autocomplete="off"
                        />
                        <InputError
                            :message="form.errors.telegram_bot_token"
                            class="mt-2"
                        />
                    </div>
                    <div>
                        <InputLabel value="Telegram user ids permitidos" />
                        <InputText
                            v-model="form.telegram_allowed_user_ids"
                            class="mt-1 block w-full"
                            placeholder="123456789, 987654321"
                        />
                        <InputError
                            :message="form.errors.telegram_allowed_user_ids"
                            class="mt-2"
                        />
                    </div>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-base-300 p-3">
                        <Checkbox
                            v-model="form.register_telegram_webhook"
                            inputId="edit_tg_hook"
                            binary
                            class="mt-0.5"
                        />
                        <span>
                            <span class="block text-sm font-medium">Re-registrar webhook</span>
                            <span class="mt-0.5 block text-xs text-base-content/50">
                                También se registra automáticamente si pegas un token nuevo.
                            </span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-base-300 p-3">
                        <Checkbox
                            v-model="form.regenerate_telegram_link_code"
                            inputId="edit_tg_code"
                            binary
                            class="mt-0.5"
                        />
                        <span class="text-sm font-medium">Regenerar código /start</span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-error/40 p-3">
                        <Checkbox
                            v-model="form.clear_telegram_bot"
                            inputId="edit_tg_clear"
                            binary
                            class="mt-0.5"
                        />
                        <span class="text-sm font-medium text-error">Quitar bot de Telegram</span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-300 pt-4">
                    <p class="text-xs text-base-content/45">
                        Catálogo: {{ catalogFilledCount }}/5 ·
                        {{
                            editTab === 'negocio'
                                ? 'Negocio'
                                : editTab === 'catalogo'
                                  ? 'Catálogo'
                                  : editTab === 'telegram'
                                    ? 'Telegram'
                                    : 'Conexión'
                        }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            label="Cancelar"
                            class="p-button-text"
                            :disabled="form.processing"
                            @click="closeEditDialog"
                        />
                        <Button
                            type="submit"
                            label="Guardar cambios"
                            icon="pi pi-check"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />
                    </div>
                </div>
            </form>
        </Dialog>

        <!-- Duplicar -->
        <Dialog
            v-model:visible="duplicateDialogVisible"
            modal
            header="Duplicar perfil de negocio"
            :style="{ width: '28rem' }"
            @hide="closeDuplicateDialog"
        >
            <form class="space-y-4" @submit.prevent="submitDuplicate">
                <p class="text-sm text-base-content/70">
                    Crea una copia para experimentar. Reutiliza la misma sesión de WhatsApp
                    (<strong>{{ selected?.evolution_instance_name || selected?.instance_name }}</strong>),
                    Calendar y credenciales. Solo cambia el nombre del perfil y la info del negocio.
                    Arranca <strong>inactiva</strong>; al activarla se pausa el otro perfil de esa sesión.
                </p>
                <div>
                    <InputLabel value="Nombre del nuevo perfil" />
                    <InputText
                        v-model="duplicateForm.instance_name"
                        class="mt-1 block w-full"
                        placeholder="johan_test_exp"
                    />
                    <InputError
                        :message="duplicateForm.errors.instance_name"
                        class="mt-2"
                    />
                </div>
                <div>
                    <InputLabel value="Nombre del negocio (opcional)" />
                    <InputText
                        v-model="duplicateForm.business_name"
                        class="mt-1 block w-full"
                    />
                    <InputError
                        :message="duplicateForm.errors.business_name"
                        class="mt-2"
                    />
                </div>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-base-300 p-3">
                    <Checkbox
                        v-model="duplicateForm.copy_telegram"
                        inputId="dup_tg"
                        binary
                        class="mt-0.5"
                    />
                    <span>
                        <span class="block text-sm font-medium">Copiar bot de Telegram</span>
                        <span class="mt-0.5 block text-xs text-base-content/50">
                            Normalmente déjalo apagado: un token solo puede apuntar a un webhook.
                        </span>
                    </span>
                </label>
                <div class="flex justify-end gap-2 border-t border-base-300 pt-4">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text"
                        :disabled="duplicateForm.processing"
                        @click="closeDuplicateDialog"
                    />
                    <Button
                        type="submit"
                        label="Duplicar"
                        icon="pi pi-copy"
                        :loading="duplicateForm.processing"
                        :disabled="duplicateForm.processing"
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
