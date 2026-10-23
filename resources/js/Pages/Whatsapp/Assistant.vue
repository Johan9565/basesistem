<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    instance: {
        type: Object,
        default: null,
    },
    qrCode: {
        type: String,
        default: null,
    },
});

const currentQr = ref(props.qrCode);
const pairingCode = ref('');
const loadingQr = ref(false);
const autoRefreshInterval = ref(null);
const pollingInterval = ref(null);

// Formulario para inicializar
const createForm = useForm({
    system_prompt: 'Eres un asistente personal inteligente y amable en WhatsApp. Ayudas al usuario con recordatorios, notas, agenda y dudas generales con respuestas claras y concisas.',
});

// Formulario para configuración
const settingsForm = useForm({
    system_prompt: props.instance?.system_prompt ?? '',
    enabled: props.instance?.enabled ?? true,
});

// Formulario de mensaje de prueba
const testForm = useForm({
    phone: '',
    message: '¡Hola! Este es un mensaje de prueba enviado desde tu Asistente Personal de WhatsApp.',
});

async function fetchQrCode() {
    loadingQr.value = true;
    try {
        const response = await axios.get(route('whatsapp.qr'));
        if (response.data.state === 'connected') {
            router.reload({ only: ['instance'] });
            stopAutoRefresh();
        } else {
            currentQr.value = response.data.qrcode;
            pairingCode.value = response.data.pairingCode || '';
        }
    } catch (e) {
        console.error('Error al obtener QR:', e);
    } finally {
        loadingQr.value = false;
    }
}

function startPollingStatus() {
    if (props.instance?.status === 'connecting') {
        pollingInterval.value = setInterval(async () => {
            try {
                const response = await axios.get(route('whatsapp.qr'));
                if (response.data.state === 'connected') {
                    clearInterval(pollingInterval.value);
                    router.reload({ only: ['instance'] });
                } else if (response.data.qrcode && response.data.qrcode !== currentQr.value) {
                    currentQr.value = response.data.qrcode;
                }
            } catch (err) {
                // Silencioso en polling
            }
        }, 5000);
    }
}

function startAutoRefresh() {
    if (!currentQr.value && props.instance) {
        fetchQrCode();
    }
    startPollingStatus();
}

function stopAutoRefresh() {
    if (autoRefreshInterval.value) clearInterval(autoRefreshInterval.value);
    if (pollingInterval.value) clearInterval(pollingInterval.value);
}

onMounted(() => {
    if (props.instance && props.instance.status !== 'connected') {
        startAutoRefresh();
    }
});

onUnmounted(() => {
    stopAutoRefresh();
});

function handleCreate() {
    createForm.post(route('whatsapp.instance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            fetchQrCode();
            startPollingStatus();
        },
    });
}

function handleSaveSettings() {
    settingsForm.post(route('whatsapp.settings.update'), {
        preserveScroll: true,
    });
}

function handleDisconnect() {
    if (confirm('¿Estás seguro de que deseas desconectar tu sesión de WhatsApp?')) {
        router.post(route('whatsapp.disconnect'), {}, {
            preserveScroll: true,
            onSuccess: () => {
                currentQr.value = null;
            },
        });
    }
}

function handleDelete() {
    if (confirm('¿Eliminar por completo tu instancia de WhatsApp? Tendrás que vincularla de nuevo con un nuevo QR.')) {
        router.delete(route('whatsapp.instance.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                currentQr.value = null;
            },
        });
    }
}

function handleSendTest() {
    testForm.post(route('whatsapp.test-message'), {
        preserveScroll: true,
        onSuccess: () => {
            testForm.reset('message');
        },
    });
}
</script>

<template>
    <Head title="Asistente de WhatsApp" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.585 1.961.944 3.013.944 3.183 0 5.769-2.587 5.769-5.766.001-3.182-2.585-5.768-5.767-5.768zm3.393 8.307c-.144.405-.837.774-1.17.824-.312.045-.694.074-2.155-.531-1.874-.775-3.076-2.687-3.17-2.812-.095-.125-.758-1.009-.758-1.925 0-.916.48-1.367.652-1.554.172-.187.375-.234.5-.234.125 0 .25.002.359.006.115.006.269-.044.421.322.157.376.536 1.309.583 1.405.047.095.078.207.016.332-.063.125-.094.203-.188.312-.094.109-.198.244-.282.328-.094.094-.192.196-.083.383.109.187.485.8 1.042 1.296.719.64 1.325.839 1.512.932.187.094.297.078.406-.047.109-.125.469-.547.594-.734.125-.187.25-.156.422-.094.172.062 1.094.516 1.281.609.188.094.313.141.359.219.047.078.047.453-.097.858z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-base-content flex items-center gap-2">
                            Asistente de WhatsApp
                            <span class="text-xs font-normal text-base-content/50 bg-base-200 px-2 py-0.5 rounded-full border border-base-300">Evolution API</span>
                        </h2>
                        <p class="text-xs text-base-content/60 mt-0.5">
                            Tu asistente personal de IA conectado directamente a tu cuenta de WhatsApp
                        </p>
                    </div>
                </div>

                <!-- Estado de conexión -->
                <div v-if="instance">
                    <div v-if="instance.status === 'connected'" class="badge badge-success gap-2 py-3 px-4 text-xs font-semibold shadow-sm">
                        <span class="inline-block w-2 h-2 rounded-full bg-white animate-pulse"></span>
                        Conectado: {{ instance.phone || instance.instance_name }}
                    </div>
                    <div v-else-if="instance.status === 'connecting'" class="badge badge-warning gap-2 py-3 px-4 text-xs font-semibold shadow-sm">
                        <span class="inline-block w-2 h-2 rounded-full bg-amber-900 animate-ping"></span>
                        Esperando Escaneo QR
                    </div>
                    <div v-else class="badge badge-ghost gap-2 py-3 px-4 text-xs font-semibold shadow-sm">
                        <span class="inline-block w-2 h-2 rounded-full bg-base-content/30"></span>
                        Desconectado
                    </div>
                </div>
            </div>
        </template>

        <div class="py-10 bg-base-200/50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- CASO 1: NO TIENE INSTANCIA CREADA -->
                <div v-if="!instance" class="card bg-base-100 shadow-sm border border-base-300 overflow-hidden">
                    <div class="card-body p-8 sm:p-12 text-center max-w-2xl mx-auto">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 flex items-center justify-center mx-auto mb-4">
                            <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.585 1.961.944 3.013.944 3.183 0 5.769-2.587 5.769-5.766.001-3.182-2.585-5.768-5.767-5.768zm3.393 8.307c-.144.405-.837.774-1.17.824-.312.045-.694.074-2.155-.531-1.874-.775-3.076-2.687-3.17-2.812-.095-.125-.758-1.009-.758-1.925 0-.916.48-1.367.652-1.554.172-.187.375-.234.5-.234.125 0 .25.002.359.006.115.006.269-.044.421.322.157.376.536 1.309.583 1.405.047.095.078.207.016.332-.063.125-.094.203-.188.312-.094.109-.198.244-.282.328-.094.094-.192.196-.083.383.109.187.485.8 1.042 1.296.719.64 1.325.839 1.512.932.187.094.297.078.406-.047.109-.125.469-.547.594-.734.125-.187.25-.156.422-.094.172.062 1.094.516 1.281.609.188.094.313.141.359.219.047.078.047.453-.097.858z" />
                            </svg>
                        </div>

                        <h3 class="text-2xl font-bold text-base-content mb-2">Conecta tu Asistente Personal a WhatsApp</h3>
                        <p class="text-sm text-base-content/70 mb-6">
                            Cada usuario dispone de su propia instancia independiente de WhatsApp. Al vincular tu cuenta, podrás interactuar con tu asistente mediante notas de voz y mensajes de texto para gestionar tu agenda, notas y recordatorios.
                        </p>

                        <form @submit.prevent="handleCreate" class="space-y-5 text-left">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold text-xs uppercase tracking-wider text-base-content/70">Instrucciones Iniciales del Bot (Prompt)</span>
                                </label>
                                <textarea
                                    v-model="createForm.system_prompt"
                                    class="textarea textarea-bordered w-full h-28 text-sm focus:border-emerald-500 rounded-xl"
                                    placeholder="Indica las instrucciones de comportamiento de tu asistente..."
                                ></textarea>
                            </div>

                            <button
                                type="submit"
                                class="btn bg-emerald-600 hover:bg-emerald-700 text-white w-full gap-2 rounded-xl border-none shadow-sm font-semibold"
                                :disabled="createForm.processing"
                            >
                                <span v-if="createForm.processing" class="loading loading-spinner loading-sm"></span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Inicializar Instancia de WhatsApp
                            </button>
                        </form>
                    </div>
                </div>

                <!-- CASO 2: INSTANCIA EXISTE -->
                <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- Columna Izquierda: Tarjeta de Conexión & QR -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="card bg-base-100 shadow-sm border border-base-300">
                            <div class="card-body p-6">
                                <h3 class="text-base font-bold text-base-content flex items-center justify-between border-b border-base-300 pb-3">
                                    <span>Estado del Enlace</span>
                                    <span v-if="instance.status === 'connected'" class="text-xs text-emerald-500 font-semibold flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> En línea
                                    </span>
                                    <span v-else class="text-xs text-amber-500 font-semibold flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> Requiere acción
                                    </span>
                                </h3>

                                <!-- Vista cuando está Conectado -->
                                <div v-if="instance.status === 'connected'" class="py-6 text-center space-y-4">
                                    <div class="avatar mx-auto">
                                        <div class="w-20 rounded-full ring-2 ring-emerald-500 ring-offset-base-100 ring-offset-2">
                                            <img
                                                :src="instance.profile_pic_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(instance.profile_name || 'WA') + '&background=10b981&color=fff'"
                                                alt="WhatsApp Profile"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-lg text-base-content">{{ instance.profile_name || 'WhatsApp Conectado' }}</h4>
                                        <p class="text-xs text-base-content/60 font-mono mt-0.5">{{ instance.phone || 'Sin número detectado' }}</p>
                                    </div>
                                    <div class="p-3 bg-emerald-500/10 rounded-xl border border-emerald-500/20 text-xs text-emerald-600 font-medium">
                                        Tu asistente está escuchando mensajes y listo para responder automáticamente.
                                    </div>

                                    <button
                                        type="button"
                                        @click="handleDisconnect"
                                        class="btn btn-outline btn-error btn-sm w-full gap-2 rounded-lg"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        Desconectar Sesión
                                    </button>
                                </div>

                                <!-- Vista cuando está Desconectado o Esperando QR -->
                                <div v-else class="py-4 text-center space-y-4">
                                    <!-- Imagen del Código QR -->
                                    <div v-if="currentQr" class="p-4 bg-white rounded-2xl inline-block border border-base-300 shadow-xs">
                                        <img :src="currentQr" alt="Código QR WhatsApp" class="w-56 h-56 mx-auto object-contain" />
                                    </div>
                                    <div v-else class="w-56 h-56 mx-auto bg-base-200/80 rounded-2xl flex flex-col items-center justify-center p-4 border border-dashed border-base-300">
                                        <span class="loading loading-spinner loading-md text-emerald-500 mb-2"></span>
                                        <span class="text-xs text-base-content/60">Generando código QR...</span>
                                    </div>

                                    <!-- Pairing Code alternativo si existe -->
                                    <div v-if="pairingCode" class="p-2.5 bg-base-200 rounded-xl border border-base-300 text-xs">
                                        <span class="text-base-content/60 block mb-1">Código de emparejamiento:</span>
                                        <span class="font-mono font-bold text-sm text-primary tracking-widest">{{ pairingCode }}</span>
                                    </div>

                                    <div class="text-left text-xs text-base-content/70 space-y-1.5 bg-base-200/50 p-3.5 rounded-xl border border-base-300">
                                        <p class="font-semibold text-base-content">Pasos para vincular:</p>
                                        <p>1. Abre WhatsApp en tu celular.</p>
                                        <p>2. Ve a <strong>Ajustes / Menú &gt; Dispositivos vinculados</strong>.</p>
                                        <p>3. Toca en <strong>Vincular un dispositivo</strong> y escanea el código.</p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="fetchQrCode"
                                        class="btn btn-outline btn-sm w-full gap-2 rounded-lg"
                                        :disabled="loadingQr"
                                    >
                                        <svg class="h-4 w-4" :class="loadingQr ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Refrescar Código QR
                                    </button>
                                </div>

                                <div class="border-t border-base-300 pt-3 text-[11px] text-base-content/50 flex justify-between items-center">
                                    <span>Instancia: {{ instance.instance_name }}</span>
                                    <button @click="handleDelete" class="text-error hover:underline text-[11px]">Eliminar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Configuración del Bot y Mensaje de Prueba -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Tarjeta de Configuración del Asistente -->
                        <div class="card bg-base-100 shadow-sm border border-base-300">
                            <div class="card-body p-6">
                                <h3 class="text-base font-bold text-base-content border-b border-base-300 pb-3 flex items-center gap-2">
                                    <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Configuración de tu Asistente de WhatsApp
                                </h3>

                                <form @submit.prevent="handleSaveSettings" class="space-y-5 pt-2">
                                    <!-- Toggle de activación -->
                                    <div class="flex items-center justify-between p-4 bg-base-200/40 rounded-xl border border-base-300">
                                        <div>
                                            <h4 class="font-semibold text-sm text-base-content">Respuesta Automática Activa</h4>
                                            <p class="text-xs text-base-content/60 mt-0.5">
                                                Si está desactivado, los mensajes recibidos no serán respondidos por la IA.
                                            </p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            v-model="settingsForm.enabled"
                                            class="toggle toggle-success"
                                        />
                                    </div>

                                    <!-- Prompt del sistema -->
                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text font-semibold text-xs uppercase tracking-wider text-base-content/70">
                                                Personalidad & Instrucciones del Asistente
                                            </span>
                                            <span class="label-text-alt text-xs text-base-content/50">Personalizado para tu usuario</span>
                                        </label>
                                        <textarea
                                            v-model="settingsForm.system_prompt"
                                            class="textarea textarea-bordered w-full h-36 text-sm focus:border-primary rounded-xl font-mono text-xs leading-relaxed"
                                            placeholder="Define cómo debe responder tu asistente en WhatsApp..."
                                        ></textarea>
                                        <span class="text-[11px] text-base-content/50 mt-1">
                                            Este prompt se concatena con el motor de IA para dirigir tus respuestas en WhatsApp.
                                        </span>
                                    </div>

                                    <div class="flex justify-end">
                                        <button
                                            type="submit"
                                            class="btn btn-primary gap-2 rounded-xl px-6 font-semibold"
                                            :disabled="settingsForm.processing"
                                        >
                                            <span v-if="settingsForm.processing" class="loading loading-spinner loading-xs"></span>
                                            Guardar Configuración
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Tarjeta de Mensaje de Prueba -->
                        <div v-if="instance.status === 'connected'" class="card bg-base-100 shadow-sm border border-base-300">
                            <div class="card-body p-6">
                                <h3 class="text-base font-bold text-base-content border-b border-base-300 pb-3 flex items-center gap-2">
                                    <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Probar Envío de Mensaje
                                </h3>

                                <form @submit.prevent="handleSendTest" class="space-y-4 pt-2">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div class="form-control sm:col-span-1">
                                            <label class="label">
                                                <span class="label-text text-xs font-semibold text-base-content/70">Número con código de país</span>
                                            </label>
                                            <input
                                                type="text"
                                                v-model="testForm.phone"
                                                placeholder="Ej. 529981234567"
                                                class="input input-bordered w-full text-sm rounded-xl focus:border-emerald-500"
                                                required
                                            />
                                        </div>
                                        <div class="form-control sm:col-span-2">
                                            <label class="label">
                                                <span class="label-text text-xs font-semibold text-base-content/70">Texto del mensaje</span>
                                            </label>
                                            <div class="flex gap-2">
                                                <input
                                                    type="text"
                                                    v-model="testForm.message"
                                                    placeholder="Escribe tu mensaje..."
                                                    class="input input-bordered w-full text-sm rounded-xl focus:border-emerald-500"
                                                    required
                                                />
                                                <button
                                                    type="submit"
                                                    class="btn bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl gap-2 font-semibold"
                                                    :disabled="testForm.processing"
                                                >
                                                    <span v-if="testForm.processing" class="loading loading-spinner loading-xs"></span>
                                                    Enviar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
