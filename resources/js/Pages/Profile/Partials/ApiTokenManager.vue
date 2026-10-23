<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    tokens: {
        type: Array,
        default: () => [],
    },
    newToken: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const toast = useToast();

// State
const createDialogVisible = ref(false);
const showNewTokenDialog = ref(false);
const confirmRevokeDialogVisible = ref(false);
const confirmRevokeAllDialogVisible = ref(false);
const tokenToRevoke = ref(null);
const latestGeneratedToken = ref(null);
const copiedToken = ref(false);
const copiedCurl = ref(false);

const expirationOptions = [
    { label: 'Sin expiración (permanente)', value: 'never' },
    { label: '7 días', value: '7' },
    { label: '30 días', value: '30' },
    { label: '60 días', value: '60' },
    { label: '90 días', value: '90' },
    { label: '1 año', value: '365' },
];

const form = useForm({
    name: '',
    expiration: 'never',
});

// Watch for newToken from flash/session
watch(
    () => props.newToken,
    (val) => {
        if (val?.plainTextToken) {
            latestGeneratedToken.value = val;
            showNewTokenDialog.value = true;
        }
    },
    { immediate: true },
);

function openCreateDialog() {
    form.reset();
    form.clearErrors();
    form.name = '';
    form.expiration = 'never';
    createDialogVisible.value = true;
}

function closeCreateDialog() {
    createDialogVisible.value = false;
    form.reset();
    form.clearErrors();
}

function submitCreateToken() {
    form.post(route('profile.tokens.store'), {
        preserveScroll: true,
        onSuccess: (pageResp) => {
            closeCreateDialog();
            const flashToken = pageResp.props.newToken || props.newToken;
            if (flashToken?.plainTextToken) {
                latestGeneratedToken.value = flashToken;
                showNewTokenDialog.value = true;
            }
            toast.add({
                severity: 'success',
                summary: 'Token generado',
                detail: 'El token de API se ha creado con éxito.',
                life: 4000,
            });
        },
    });
}

async function copyToClipboard(text, isCurl = false) {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        if (isCurl) {
            copiedCurl.value = true;
            setTimeout(() => (copiedCurl.value = false), 2500);
        } else {
            copiedToken.value = true;
            setTimeout(() => (copiedToken.value = false), 2500);
        }
        toast.add({
            severity: 'info',
            summary: 'Copiado',
            detail: isCurl ? 'Comando cURL copiado' : 'Token copiado al portapapeles',
            life: 2500,
        });
    } catch {
        toast.add({
            severity: 'warn',
            summary: 'Aviso',
            detail: 'Por favor copia el texto manualmente.',
            life: 3000,
        });
    }
}

function dismissNewTokenDialog() {
    showNewTokenDialog.value = false;
    latestGeneratedToken.value = null;
}

function promptRevoke(token) {
    tokenToRevoke.value = token;
    confirmRevokeDialogVisible.value = true;
}

function executeRevoke() {
    if (!tokenToRevoke.value) return;

    router.delete(route('profile.tokens.destroy', tokenToRevoke.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmRevokeDialogVisible.value = false;
            tokenToRevoke.value = null;
            toast.add({
                severity: 'success',
                summary: 'Token revocado',
                detail: 'El token de API ha sido revocado.',
                life: 3500,
            });
        },
    });
}

function promptRevokeAll() {
    confirmRevokeAllDialogVisible.value = true;
}

function executeRevokeAll() {
    router.delete(route('profile.tokens.destroy-all'), {
        preserveScroll: true,
        onSuccess: () => {
            confirmRevokeAllDialogVisible.value = false;
            toast.add({
                severity: 'success',
                summary: 'Tokens revocados',
                detail: 'Todos los tokens de API han sido eliminados.',
                life: 3500,
            });
        },
    });
}

const exampleCurl = computed(() => {
    const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
    const token = latestGeneratedToken.value?.plainTextToken || 'TU_TOKEN_AQUI';
    return `curl -X GET "${origin}/api/v1/assistant/feed" \\\n  -H "Authorization: Bearer ${token}" \\\n  -H "Accept: application/json"`;
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-200/80 pb-5 dark:border-gray-700/60">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-indigo-500 to-blue-600 text-white shadow-md shadow-indigo-500/20">
                    <i class="pi pi-key text-xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                            Tokens de API
                        </h2>
                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                            {{ tokens.length }} {{ tokens.length === 1 ? 'activo' : 'activos' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Genera tokens de acceso personal para autenticar integraciones externas, apps móviles o el asistente virtual.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                <Button
                    v-if="tokens.length > 1"
                    type="button"
                    label="Revocar todos"
                    icon="pi pi-trash"
                    severity="danger"
                    variant="outlined"
                    size="small"
                    @click="promptRevokeAll"
                />
                <Button
                    type="button"
                    label="Generar nuevo token"
                    icon="pi pi-plus"
                    size="small"
                    class="shadow-sm"
                    @click="openCreateDialog"
                />
            </div>
        </div>

        <!-- Token list or empty state -->
        <div v-if="tokens.length > 0" class="overflow-hidden rounded-xl border border-gray-200/80 bg-white/50 shadow-xs dark:border-gray-700/60 dark:bg-gray-800/30">
            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50/90 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b border-gray-200/80 dark:bg-gray-800/80 dark:text-gray-300 dark:border-gray-700/60">
                        <tr>
                            <th scope="col" class="px-5 py-3.5">Nombre</th>
                            <th scope="col" class="px-5 py-3.5">Estado</th>
                            <th scope="col" class="px-5 py-3.5">Último uso</th>
                            <th scope="col" class="px-5 py-3.5">Expiración</th>
                            <th scope="col" class="px-5 py-3.5">Creado el</th>
                            <th scope="col" class="px-5 py-3.5 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/50 font-normal">
                        <tr
                            v-for="token in tokens"
                            :key="token.id"
                            class="hover:bg-gray-50/60 dark:hover:bg-gray-800/50 transition-colors"
                        >
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        <i class="pi pi-shield text-xs"></i>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-gray-900 dark:text-white">
                                            {{ token.name }}
                                        </span>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                            ID: {{ token.id.slice(0, 8) }}...
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    v-if="token.is_expired"
                                    class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-rose-600/20 ring-inset dark:bg-rose-950/40 dark:text-rose-400 dark:ring-rose-500/30"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    Expirado
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20 ring-inset dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-500/30"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Activo
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span class="text-xs text-gray-600 dark:text-gray-300">
                                    {{ token.last_used_at }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span class="text-xs" :class="token.is_expired ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-gray-600 dark:text-gray-400'">
                                    {{ token.expires_at }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-xs text-gray-500 dark:text-gray-400">
                                {{ token.created_at || '—' }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <Button
                                    type="button"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    variant="text"
                                    rounded
                                    size="small"
                                    title="Revocar token"
                                    aria-label="Revocar token"
                                    @click="promptRevoke(token)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Stacked Cards -->
            <div class="divide-y divide-gray-200/70 md:hidden dark:divide-gray-700/60">
                <div
                    v-for="token in tokens"
                    :key="token.id"
                    class="p-4 space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-shield text-indigo-500"></i>
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ token.name }}
                            </span>
                        </div>
                        <span
                            v-if="token.is_expired"
                            class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-400"
                        >
                            Expirado
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                        >
                            Activo
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <div>
                            <span class="block font-medium text-gray-700 dark:text-gray-300">Último uso:</span>
                            {{ token.last_used_at }}
                        </div>
                        <div>
                            <span class="block font-medium text-gray-700 dark:text-gray-300">Expiración:</span>
                            {{ token.expires_at }}
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <span class="text-xs text-gray-400">
                            Creado: {{ token.created_at || '—' }}
                        </span>
                        <Button
                            type="button"
                            label="Revocar"
                            icon="pi pi-trash"
                            severity="danger"
                            variant="text"
                            size="small"
                            @click="promptRevoke(token)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300/80 bg-gray-50/50 py-10 px-6 text-center dark:border-gray-700/60 dark:bg-gray-800/20"
        >
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 mb-3 shadow-inner">
                <i class="pi pi-lock-open text-2xl"></i>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                No tienes tokens de API generados
            </h3>
            <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Crea un token de acceso personal para conectar clientes externos, automatizaciones o el asistente virtual de forma segura.
            </p>
            <div class="mt-5">
                <Button
                    type="button"
                    label="Generar mi primer token"
                    icon="pi pi-plus"
                    @click="openCreateDialog"
                />
            </div>
        </div>

        <!-- Dialog: Generar Token -->
        <Dialog
            v-model:visible="createDialogVisible"
            modal
            header="Generar Token de API"
            :style="{ width: '32rem' }"
            @hide="closeCreateDialog"
        >
            <form @submit.prevent="submitCreateToken" class="space-y-4 pt-2">
                <div>
                    <InputLabel for="token_name" value="Nombre del token *" />
                    <InputText
                        id="token_name"
                        v-model="form.name"
                        class="mt-1 block w-full"
                        placeholder="Ej. MacBook Pro, Postman, Integración Asistente"
                        autofocus
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.name" class="mt-1.5" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Asígnale un nombre descriptivo para identificar el dispositivo o propósito.
                    </p>
                </div>

                <div>
                    <InputLabel for="token_expiration" value="Tiempo de expiración" />
                    <Select
                        id="token_expiration"
                        v-model="form.expiration"
                        :options="expirationOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 block w-full"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.expiration" class="mt-1.5" />
                </div>

                <div class="rounded-lg border border-blue-200/80 bg-blue-50/70 p-3 text-xs text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-200">
                    <div class="flex items-center gap-1.5 font-semibold">
                        <i class="pi pi-info-circle"></i>
                        <span>Permisos completos</span>
                    </div>
                    <p class="mt-1 text-blue-800/90 dark:text-blue-200/90">
                        El token otorgará acceso a todos los endpoints autorizados para tu usuario (asistente, calendario, recordatorios). Podrás revocarlo en cualquier momento.
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-3">
                    <Button
                        type="button"
                        label="Cancelar"
                        variant="text"
                        :disabled="form.processing"
                        @click="closeCreateDialog"
                    />
                    <Button
                        type="submit"
                        label="Generar token"
                        icon="pi pi-check"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Dialog: Token Generado (Modal Seguro) -->
        <Dialog
            v-model:visible="showNewTokenDialog"
            modal
            :closable="false"
            header="¡Token Generado con Éxito!"
            :style="{ width: '38rem' }"
        >
            <div class="space-y-4 pt-2">
                <!-- Warning Banner -->
                <div class="rounded-xl border border-amber-300/80 bg-amber-50/90 p-4 text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
                    <div class="flex items-center gap-2 font-bold text-sm">
                        <i class="pi pi-exclamation-triangle text-amber-600 dark:text-amber-400"></i>
                        <span>Importante: Guarda tu token ahora</span>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                        Por razones de seguridad, <strong>este token no se volverá a mostrar</strong> una vez que cierres esta ventana. Cópialo y guárdalo en un gestor de contraseñas o en las variables de tu aplicación.
                    </p>
                </div>

                <!-- Token Plaintext Display & Copy -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                        Token personal para {{ latestGeneratedToken?.name || 'tu aplicación' }}
                    </label>
                    <div class="relative flex items-center">
                        <input
                            type="text"
                            readonly
                            :value="latestGeneratedToken?.plainTextToken"
                            class="w-full font-mono text-sm pr-28 py-3 px-3.5 bg-gray-900 text-emerald-400 rounded-lg border border-gray-700 shadow-inner select-all focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                        />
                        <button
                            type="button"
                            @click="copyToClipboard(latestGeneratedToken?.plainTextToken, false)"
                            class="absolute right-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-colors flex items-center gap-1.5 shadow-sm active:scale-95 cursor-pointer"
                        >
                            <i :class="copiedToken ? 'pi pi-check' : 'pi pi-copy'"></i>
                            <span>{{ copiedToken ? '¡Copiado!' : 'Copiar' }}</span>
                        </button>
                    </div>
                    <div v-if="latestGeneratedToken?.expires_at" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                        <i class="pi pi-calendar text-[11px]"></i>
                        <span>Vence: {{ latestGeneratedToken.expires_at }}</span>
                    </div>
                </div>

                <!-- Integration snippet -->
                <div class="space-y-2 pt-2 border-t border-gray-200/80 dark:border-gray-700/60">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Ejemplo de uso en cabecera HTTP:
                    </span>
                    <div class="rounded-md bg-gray-100 p-2.5 font-mono text-xs text-gray-800 dark:bg-gray-900/90 dark:text-gray-300 border border-gray-200 dark:border-gray-800">
                        Authorization: Bearer {{ latestGeneratedToken?.plainTextToken }}
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Comando cURL de prueba rápida:
                        </span>
                        <button
                            type="button"
                            @click="copyToClipboard(exampleCurl, true)"
                            class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 flex items-center gap-1 font-medium cursor-pointer"
                        >
                            <i :class="copiedCurl ? 'pi pi-check' : 'pi pi-copy'"></i>
                            <span>{{ copiedCurl ? '¡cURL copiado!' : 'Copiar cURL' }}</span>
                        </button>
                    </div>
                    <pre class="rounded-md bg-gray-900 p-2.5 font-mono text-[11px] leading-relaxed text-gray-300 overflow-x-auto whitespace-pre">{{ exampleCurl }}</pre>
                </div>

                <div class="flex justify-end pt-3">
                    <Button
                        type="button"
                        label="Entendido, ya guardé mi token"
                        icon="pi pi-check"
                        @click="dismissNewTokenDialog"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Dialog: Confirm Revoke Token -->
        <Dialog
            v-model:visible="confirmRevokeDialogVisible"
            modal
            header="Revocar Token de API"
            :style="{ width: '28rem' }"
        >
            <div class="space-y-3 pt-2">
                <div class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                        <i class="pi pi-exclamation-triangle text-lg"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            ¿Estás seguro de que deseas revocar este token?
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            El token <strong class="text-gray-800 dark:text-gray-200">"{{ tokenToRevoke?.name }}"</strong> será invalidado permanentemente. Las aplicaciones que lo utilicen perderán acceso a la API.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        label="Cancelar"
                        variant="text"
                        @click="confirmRevokeDialogVisible = false"
                    />
                    <Button
                        type="button"
                        label="Sí, revocar token"
                        icon="pi pi-trash"
                        severity="danger"
                        @click="executeRevoke"
                    />
                </div>
            </div>
        </Dialog>

        <!-- Dialog: Confirm Revoke All -->
        <Dialog
            v-model:visible="confirmRevokeAllDialogVisible"
            modal
            header="Revocar Todos los Tokens"
            :style="{ width: '30rem' }"
        >
            <div class="space-y-3 pt-2">
                <div class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400">
                        <i class="pi pi-exclamation-triangle text-lg"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            ¿Revocar todos los tokens de API ({{ tokens.length }})?
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Esta acción eliminará todos tus tokens de acceso personal de forma irreversible. Todas las integraciones conectadas dejarán de funcionar.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        label="Cancelar"
                        variant="text"
                        @click="confirmRevokeAllDialogVisible = false"
                    />
                    <Button
                        type="button"
                        label="Revocar todos los tokens"
                        icon="pi pi-trash"
                        severity="danger"
                        @click="executeRevokeAll"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>
