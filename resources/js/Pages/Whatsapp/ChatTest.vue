<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const props = defineProps({
    instances: {
        type: Array,
        default: () => [],
    },
    selectedInstance: {
        type: Object,
        default: null,
    },
    initialHistory: {
        type: Array,
        default: () => [],
    },
    initialConversations: {
        type: Array,
        default: () => [],
    },
    defaultPhone: {
        type: String,
        default: '+52 999 000 0000',
    },
    companyKnowledge: {
        type: Object,
        default: () => ({}),
    },
});

const activeCompany = computed(() => page.props?.auth?.active_company ?? null);

// Estado de la sesión de prueba
const currentInstanceId = ref(props.selectedInstance?.id || (props.instances[0]?.id ?? ''));
const testPhone = ref(props.defaultPhone);
const provider = ref(props.selectedInstance?.ai_provider || 'deepseek');
const messageInput = ref('');
const sending = ref(false);
const resetting = ref(false);
const showInspector = ref(false);
const showConversationsSidebar = ref(true);

// Historial reactivo de mensajes de la prueba
const messages = ref([...props.initialHistory]);
const lastToolCalls = ref([]);
const lastBookingState = ref({});
const lastPrompts = ref({ static: '', dynamic: '' });
const messagesContainer = ref(null);

// Hilos de conversaciones
const conversations = ref([...props.initialConversations]);
const conversationsFilter = ref('all'); // 'all' | 'sim' | 'real'
const searchQuery = ref('');
const loadingConversations = ref(false);
const activeSource = ref('sim'); // 'sim' | 'real'

const currentInstance = computed(() => {
    return props.instances.find(i => String(i.id) === String(currentInstanceId.value)) || props.selectedInstance || null;
});

const activeThreadKey = computed(() => {
    return (activeSource.value === 'real' ? 'real:' : 'sim:') + testPhone.value;
});

const filteredConversations = computed(() => {
    let list = conversations.value;
    if (conversationsFilter.value !== 'all') {
        list = list.filter(c => c.source === conversationsFilter.value);
    }
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(c =>
            (c.phone && c.phone.toLowerCase().includes(q)) ||
            (c.customer_name && c.customer_name.toLowerCase().includes(q)) ||
            (c.last_message && c.last_message.toLowerCase().includes(q))
        );
    }
    return list;
});

// Píldoras de prueba rápida con 1 clic
const quickPrompts = [
    '¡Hola! ¿Qué servicios ofrecen?',
    '¿Tienen citas disponibles para mañana por la tarde?',
    '¿Qué productos tienen en inventario?',
    '¿Cuáles son sus horarios y ubicación?',
    'Quiero agendar una cita para mí',
    '¿Tienen cemento en existencia y cuánto cuesta?',
];

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

onMounted(() => {
    scrollToBottom();
});

watch(() => messages.value.length, () => {
    scrollToBottom();
});

// Cargar historial de un hilo específico
async function loadHistory(phone, source = 'sim') {
    if (!currentInstanceId.value) return;

    try {
        const url = route('whatsapp.chat-test.history') +
            `?instance_id=${encodeURIComponent(currentInstanceId.value)}&phone=${encodeURIComponent(phone)}&source=${encodeURIComponent(source)}`;
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            messages.value = data.history || [];
            lastBookingState.value = data.booking_state || {};
            scrollToBottom();
        }
    } catch (e) {
        console.error('Error al cargar historial', e);
    }
}

// Seleccionar conversación de la lista
async function selectConversation(conv) {
    testPhone.value = conv.phone;
    activeSource.value = conv.source || 'sim';
    await loadHistory(conv.phone, activeSource.value);
}

// Iniciar nueva conversación con número fresco
function startNewConversation() {
    const randomSuffix = Math.floor(1000 + Math.random() * 9000);
    testPhone.value = `+52 999 ${randomSuffix} 0000`;
    activeSource.value = 'sim';
    messages.value = [];
    lastBookingState.value = {};
    lastToolCalls.value = [];
    toast.add({
        severity: 'info',
        summary: 'Nueva Conversación',
        detail: `Teléfono de prueba asignado: ${testPhone.value}. Envía un mensaje para comenzar.`,
        life: 3000,
    });
}

// Refrescar lista de conversaciones desde el backend
async function fetchConversations() {
    if (!currentInstanceId.value) return;
    loadingConversations.value = true;
    try {
        const url = route('whatsapp.chat-test.conversations') +
            `?instance_id=${encodeURIComponent(currentInstanceId.value)}&source=${encodeURIComponent(conversationsFilter.value)}&search=${encodeURIComponent(searchQuery.value)}`;
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            conversations.value = data.conversations || [];
        }
    } catch (e) {
        console.error('Error al obtener conversaciones', e);
    } finally {
        loadingConversations.value = false;
    }
}

// Cambiar de instancia: recarga sus conversaciones
async function onInstanceChange() {
    await fetchConversations();
    if (conversations.value.length > 0) {
        await selectConversation(conversations.value[0]);
    } else {
        startNewConversation();
    }
}

// Enviar mensaje de prueba al bot (SIN WhatsApp)
async function sendMessage(customText = null) {
    const textToSend = customText || messageInput.value.trim();
    if (!textToSend || sending.value) return;

    if (!currentInstanceId.value) {
        toast.add({
            severity: 'warn',
            summary: 'Atención',
            detail: 'Selecciona una instancia para probar el bot.',
            life: 3000,
        });
        return;
    }

    // Agregar mensaje del usuario a la interfaz inmediatamente
    const tempUserMsg = {
        id: 'temp-' + Date.now(),
        role: 'user',
        content: textToSend,
        created_at: new Date().toISOString(),
    };
    messages.value.push(tempUserMsg);
    messageInput.value = '';
    sending.value = true;
    scrollToBottom();

    try {
        const response = await fetch(route('whatsapp.chat-test.send'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrf_token || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                message: textToSend,
                instance_id: currentInstanceId.value,
                phone: testPhone.value,
                provider: provider.value,
            }),
        });

        const data = await response.json();

        if (response.ok && data.ok) {
            // Agregar mensaje del asistente
            messages.value.push({
                id: 'bot-' + Date.now(),
                role: 'assistant',
                content: data.reply,
                tool_calls: data.tool_calls || [],
                created_at: new Date().toISOString(),
                execution_time_ms: data.execution_time_ms,
            });

            lastToolCalls.value = data.tool_calls || [];
            lastBookingState.value = data.booking_state || {};
            lastPrompts.value = {
                static: data.static_prompt || '',
                dynamic: data.dynamic_prompt || '',
            };

            // Refrescar conversaciones en segundo plano para actualizar último mensaje
            fetchConversations();
        } else {
            messages.value.push({
                id: 'err-' + Date.now(),
                role: 'system_error',
                content: data.error || 'Ocurrió un error al contactar al motor del bot.',
                created_at: new Date().toISOString(),
            });

            toast.add({
                severity: 'error',
                summary: 'Error del Bot',
                detail: data.error || 'Fallo en la llamada al modelo de IA.',
                life: 5000,
            });
        }
    } catch (err) {
        messages.value.push({
            id: 'err-' + Date.now(),
            role: 'system_error',
            content: 'Error de red o conexión: ' + (err.message || 'No se pudo comunicar con el servidor.'),
            created_at: new Date().toISOString(),
        });
    } finally {
        sending.value = false;
        scrollToBottom();
    }
}

// Reiniciar conversación de prueba activa
async function resetChat() {
    if (!currentInstanceId.value || resetting.value) return;

    resetting.value = true;

    try {
        const response = await fetch(route('whatsapp.chat-test.reset'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrf_token || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                instance_id: currentInstanceId.value,
                phone: testPhone.value,
            }),
        });

        const data = await response.json();

        if (response.ok && data.ok) {
            messages.value = [];
            lastToolCalls.value = [];
            lastBookingState.value = {};
            lastPrompts.value = { static: '', dynamic: '' };

            toast.add({
                severity: 'info',
                summary: 'Sesión Reiniciada',
                detail: 'El historial y estado de este chat se han limpiado.',
                life: 3000,
            });

            fetchConversations();
        } else {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: data.error || 'No se pudo reiniciar la sesión.',
                life: 4000,
            });
        }
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Fallo al reiniciar: ' + err.message,
            life: 4000,
        });
    } finally {
        resetting.value = false;
    }
}

function formatTime(isoStr) {
    if (!isoStr) return '';
    try {
        const d = new Date(isoStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
}
</script>

<template>
    <Head title="Chat Test - Simulador e Historial de Bot" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-bold tracking-tight text-base-content">
                            Chat Test
                        </h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-500/15 px-3 py-0.5 text-xs font-bold text-purple-600 dark:text-purple-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-purple-500 animate-pulse"></span>
                            Simulador Offline e Historial
                        </span>
                    </div>
                    <p class="text-xs text-base-content/60 mt-0.5">
                        Prueba respuestas, citas y pedidos del bot con IA, y navega por el historial de conversaciones simuladas y reales.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Botón para alternar barra lateral de conversaciones -->
                    <button
                        type="button"
                        @click="showConversationsSidebar = !showConversationsSidebar"
                        class="btn btn-sm gap-2 rounded-xl border border-base-300 bg-base-100 hover:bg-base-200"
                        :class="{ 'btn-primary text-primary-content': showConversationsSidebar }"
                        title="Mostrar u ocultar lista de chats"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>{{ showConversationsSidebar ? 'Ocultar Chats' : 'Ver Chats (' + conversations.length + ')' }}</span>
                    </button>

                    <!-- Botón de Inspector lateral -->
                    <button
                        type="button"
                        @click="showInspector = !showInspector"
                        class="btn btn-sm gap-2 rounded-xl border border-base-300 bg-base-100 hover:bg-base-200"
                        :class="{ 'btn-primary text-primary-content': showInspector }"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                            <path d="M9 3v18"/>
                        </svg>
                        <span>{{ showInspector ? 'Ocultar Inspector' : 'Inspector' }}</span>
                    </button>

                    <!-- Botón Reiniciar Sesión -->
                    <button
                        type="button"
                        @click="resetChat"
                        :disabled="resetting || sending"
                        class="btn btn-sm btn-ghost gap-2 text-error hover:bg-error/10 rounded-xl"
                        title="Borrar historial del chat actual"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" :class="{ 'animate-spin': resetting }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                            <path d="M21 3v5h-5"/>
                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                            <path d="M3 21v-5h5"/>
                        </svg>
                        <span>Limpiar Chat</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

                <!-- BARRA DE CONFIGURACIÓN DE LA PRUEBA -->
                <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 items-center">
                        
                        <!-- Selector de Instancia -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-base-content/50 mb-1">
                                Instancia / Bot a Probar
                            </label>
                            <select
                                v-model="currentInstanceId"
                                @change="onInstanceChange"
                                class="select select-bordered select-sm w-full rounded-xl bg-base-200/50 text-xs font-semibold"
                            >
                                <option
                                    v-for="inst in instances"
                                    :key="inst.id"
                                    :value="inst.id"
                                >
                                    {{ inst.instance_name }} ({{ inst.business_name || 'Sin negocio' }}){{ inst.company_name ? ' — ' + inst.company_name : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Selector de Modelo IA -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-base-content/50 mb-1">
                                Motor de IA
                            </label>
                            <select
                                v-model="provider"
                                class="select select-bordered select-sm w-full rounded-xl bg-base-200/50 text-xs font-semibold font-mono"
                            >
                                <option value="deepseek">DeepSeek (deepseek-chat) ✨</option>
                                <option value="mimo">MiMo Cloud (Xiaomi)</option>
                            </select>
                        </div>

                        <!-- Teléfono Simulado Actual -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-base-content/50 mb-1">
                                Teléfono Activo (Test User)
                            </label>
                            <div class="flex items-center gap-1.5">
                                <input
                                    v-model="testPhone"
                                    @change="loadHistory(testPhone, activeSource)"
                                    type="text"
                                    placeholder="+52 999 000 0000"
                                    class="input input-bordered input-sm w-full rounded-xl bg-base-200/50 text-xs font-mono"
                                />
                                <button
                                    type="button"
                                    @click="startNewConversation"
                                    class="btn btn-sm btn-square btn-ghost text-primary"
                                    title="Nuevo número de prueba"
                                >
                                    ➕
                                </button>
                            </div>
                        </div>

                        <!-- Métricas del Negocio Activo -->
                        <div class="flex items-center gap-2 rounded-xl bg-base-200/60 px-3 py-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary text-xs font-bold shrink-0">
                                🧠
                            </span>
                            <div class="min-w-0 text-left">
                                <div class="text-[10px] font-bold uppercase text-base-content/40 truncate">
                                    Conocimiento Bot
                                </div>
                                <div class="text-xs font-semibold text-base-content truncate">
                                    {{ companyKnowledge.products_count || 0 }} productos · {{ companyKnowledge.services_count || 0 }} servicios
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- CONTENEDOR PRINCIPAL MULTI-PANEL -->
                <div class="flex flex-col lg:flex-row gap-4 items-start">
                    
                    <!-- PANEL IZQUIERDO: HISTORIAL DE CONVERSACIONES -->
                    <div
                        v-show="showConversationsSidebar"
                        class="w-full lg:w-80 shrink-0 rounded-2xl border border-base-300 bg-base-100 flex flex-col shadow-xs overflow-hidden"
                        style="height: 680px;"
                    >
                        <!-- Header de Conversaciones -->
                        <div class="p-3 border-b border-base-300 bg-base-200/40 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-base-content">
                                        Conversaciones
                                    </span>
                                    <span class="badge badge-xs badge-neutral font-mono">
                                        {{ filteredConversations.length }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click="fetchConversations"
                                        :disabled="loadingConversations"
                                        class="btn btn-ghost btn-xs btn-circle text-base-content/60"
                                        title="Recargar conversaciones"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" :class="{ 'animate-spin': loadingConversations }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                            <path d="M3 3v5h5"/>
                                            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
                                            <path d="M21 21v-5h-5"/>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="startNewConversation"
                                        class="btn btn-primary btn-xs rounded-lg gap-1 font-semibold"
                                        title="Crear un nuevo chat de prueba"
                                    >
                                        <span>+ Nuevo</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Barra de búsqueda -->
                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    @input="fetchConversations"
                                    type="text"
                                    placeholder="Buscar por teléfono o texto..."
                                    class="input input-bordered input-xs w-full rounded-lg bg-base-100 text-xs pl-7"
                                />
                                <span class="absolute left-2 top-1.5 text-base-content/40 text-xs">🔍</span>
                            </div>

                            <!-- Filtros (Todos, Simulador, WhatsApp) -->
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="conversationsFilter = 'all'"
                                    class="btn btn-xs rounded-lg flex-1 text-[10px]"
                                    :class="conversationsFilter === 'all' ? 'btn-neutral' : 'btn-ghost border border-base-300'"
                                >
                                    Todos
                                </button>
                                <button
                                    type="button"
                                    @click="conversationsFilter = 'sim'"
                                    class="btn btn-xs rounded-lg flex-1 text-[10px]"
                                    :class="conversationsFilter === 'sim' ? 'btn-primary' : 'btn-ghost border border-base-300'"
                                >
                                    Simulador
                                </button>
                                <button
                                    type="button"
                                    @click="conversationsFilter = 'real'"
                                    class="btn btn-xs rounded-lg flex-1 text-[10px]"
                                    :class="conversationsFilter === 'real' ? 'btn-success text-success-content' : 'btn-ghost border border-base-300'"
                                >
                                    WhatsApp
                                </button>
                            </div>
                        </div>

                        <!-- Lista de hilos scrollable -->
                        <div class="flex-1 overflow-y-auto divide-y divide-base-200 bg-base-100">
                            
                            <!-- Estado vacío -->
                            <div
                                v-if="!filteredConversations.length"
                                class="p-6 text-center text-base-content/50 space-y-2"
                            >
                                <div class="text-2xl">📭</div>
                                <div class="text-xs font-semibold text-base-content">Sin conversaciones</div>
                                <p class="text-[11px]">
                                    Aún no hay chats registrados para esta instancia. Escribe un mensaje para generar la primera conversación.
                                </p>
                            </div>

                            <!-- Items de Conversación -->
                            <button
                                v-for="conv in filteredConversations"
                                :key="conv.id"
                                type="button"
                                @click="selectConversation(conv)"
                                class="w-full text-left p-3 hover:bg-base-200/60 transition-colors flex items-start gap-2.5 relative"
                                :class="{
                                    'bg-primary/10 border-l-4 border-primary': conv.phone === testPhone && conv.source === activeSource
                                }"
                            >
                                <div
                                    class="h-8 w-8 rounded-xl flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
                                    :class="conv.source === 'sim' ? 'bg-purple-500/15 text-purple-600 dark:text-purple-400' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'"
                                >
                                    {{ conv.source === 'sim' ? '🤖' : '💬' }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-1 mb-0.5">
                                        <div class="text-xs font-bold text-base-content truncate">
                                            {{ conv.customer_name || conv.phone }}
                                        </div>
                                        <span class="text-[10px] text-base-content/50 shrink-0 font-mono">
                                            {{ conv.last_at_formatted || formatTime(conv.last_at) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span
                                            class="badge badge-xs text-[9px] font-bold"
                                            :class="conv.source === 'sim' ? 'badge-primary' : 'badge-success text-success-content'"
                                        >
                                            {{ conv.source_label }}
                                        </span>
                                        <span v-if="conv.customer_name" class="text-[10px] font-mono text-base-content/50 truncate">
                                            {{ conv.phone }}
                                        </span>
                                        <span v-if="conv.booking_stage" class="badge badge-xs badge-ghost text-[9px] font-mono">
                                            {{ conv.booking_stage }}
                                        </span>
                                    </div>

                                    <p class="text-[11px] text-base-content/60 truncate">
                                        {{ conv.last_message || 'Sin mensajes' }}
                                    </p>
                                </div>

                                <div class="shrink-0 flex flex-col items-end gap-1">
                                    <span class="badge badge-xs badge-neutral font-mono text-[9px]">
                                        {{ conv.message_count }} msgs
                                    </span>
                                </div>
                            </button>

                        </div>
                    </div>

                    <!-- PANEL CENTRAL: CHAT INTERACTIVO -->
                    <div
                        class="flex-1 min-w-0 rounded-2xl border border-base-300 bg-base-100 flex flex-col shadow-xs overflow-hidden"
                        style="height: 680px;"
                    >
                        
                        <!-- Header del Chat -->
                        <div class="flex items-center justify-between border-b border-base-300 px-4 py-3 bg-base-200/30">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="relative shrink-0">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-primary to-accent text-primary-content text-sm font-bold shadow-xs">
                                        🤖
                                    </div>
                                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-success ring-2 ring-base-100"></span>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-xs font-bold text-base-content flex items-center gap-1.5 truncate">
                                        <span>{{ currentInstance?.business_name || 'Bot Asistente' }}</span>
                                        <span class="badge badge-xs badge-neutral font-mono">{{ provider }}</span>
                                        <span
                                            class="badge badge-xs font-semibold"
                                            :class="activeSource === 'sim' ? 'badge-primary' : 'badge-success text-success-content'"
                                        >
                                            {{ activeSource === 'sim' ? 'Simulador' : 'WhatsApp Real' }}
                                        </span>
                                    </h3>
                                    <p class="text-[10px] text-base-content/60 font-mono truncate">
                                        Cliente: {{ testPhone }} &bull; Zona: {{ companyKnowledge.timezone || 'America/Mexico_City' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    @click="loadHistory(testPhone, activeSource)"
                                    class="btn btn-ghost btn-xs rounded-lg gap-1 text-base-content/70 hover:bg-base-200"
                                    title="Recargar historial de este chat"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                        <path d="M3 3v5h5"/>
                                        <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
                                        <path d="M21 21v-5h-5"/>
                                    </svg>
                                    <span class="hidden sm:inline">Historial</span>
                                </button>
                                <span class="text-[11px] font-mono text-base-content/50">
                                    {{ messages.length }} msgs
                                </span>
                            </div>
                        </div>

                        <!-- Mensajes del Chat -->
                        <div
                            ref="messagesContainer"
                            class="flex-1 p-4 space-y-4 overflow-y-auto bg-base-200/20"
                        >
                            <!-- Estado inicial vacío -->
                            <div
                                v-if="!messages.length"
                                class="h-full flex flex-col items-center justify-center text-center p-6 text-base-content/50 space-y-3"
                            >
                                <div class="h-14 w-14 rounded-2xl bg-primary/10 flex items-center justify-center text-primary text-2xl mb-1 shadow-inner">
                                    💬
                                </div>
                                <h4 class="text-sm font-bold text-base-content">Conversación Lista</h4>
                                <p class="text-xs max-w-md">
                                    Escribe un mensaje o usa las sugerencias rápidas para interactuar con el bot. Puedes cambiar de chat o ver conversaciones previas en el panel lateral.
                                </p>
                            </div>

                            <!-- Lista de Burbujas -->
                            <template v-for="(msg, idx) in messages" :key="msg.id || idx">
                                
                                <!-- Mensaje del Usuario -->
                                <div v-if="msg.role === 'user'" class="flex justify-end items-end gap-2">
                                    <div class="max-w-[80%] rounded-2xl rounded-br-xs bg-primary text-primary-content px-4 py-2.5 shadow-xs text-xs whitespace-pre-wrap leading-relaxed">
                                        <p>{{ msg.content }}</p>
                                        <div class="text-[10px] opacity-70 text-right mt-1 font-mono">
                                            {{ formatTime(msg.created_at) }}
                                        </div>
                                    </div>
                                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-base-300 text-xs font-bold shrink-0 text-base-content">
                                        👤
                                    </div>
                                </div>

                                <!-- Mensaje del Bot (Asistente) -->
                                <div v-else-if="msg.role === 'assistant'" class="flex justify-start items-start gap-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/15 text-primary text-xs font-bold shrink-0 mt-0.5">
                                        🤖
                                    </div>
                                    <div class="max-w-[80%] space-y-1.5">
                                        <!-- Insignia de Herramientas si ejecutó alguna -->
                                        <div
                                            v-if="msg.tool_calls && msg.tool_calls.length"
                                            class="flex items-center gap-1.5 flex-wrap"
                                        >
                                            <span
                                                v-for="(t, tIdx) in msg.tool_calls"
                                                :key="tIdx"
                                                class="badge badge-xs badge-warning gap-1 font-mono text-[9px]"
                                            >
                                                ⚙️ {{ t.name || t.function?.name }}
                                            </span>
                                            <span v-if="msg.execution_time_ms" class="text-[10px] text-base-content/40 font-mono">
                                                {{ msg.execution_time_ms }}ms
                                            </span>
                                        </div>

                                        <!-- Burbuja del Asistente -->
                                        <div class="rounded-2xl rounded-tl-xs bg-base-100 border border-base-300 px-4 py-3 shadow-xs text-xs text-base-content whitespace-pre-wrap leading-relaxed">
                                            <p>{{ msg.content }}</p>
                                            <div class="text-[10px] text-base-content/40 text-right mt-1.5 font-mono">
                                                {{ formatTime(msg.created_at) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mensaje de Ejecución de Tool (registro de tool) -->
                                <div v-else-if="msg.role === 'tool'" class="flex justify-center my-1.5">
                                    <div class="rounded-xl border border-warning/30 bg-warning/5 px-3 py-1.5 text-[11px] font-mono text-base-content/70 max-w-lg truncate">
                                        🛠️ Tool <strong>{{ msg.tool_name }}</strong> ejecutada
                                    </div>
                                </div>

                                <!-- Mensaje de Error del Sistema -->
                                <div v-else-if="msg.role === 'system_error'" class="flex justify-center my-2">
                                    <div class="rounded-xl border border-error/30 bg-error/10 px-4 py-2 text-xs text-error text-center max-w-md">
                                        ⚠️ {{ msg.content }}
                                    </div>
                                </div>

                            </template>

                            <!-- Indicador de tipeo / pensando -->
                            <div v-if="sending" class="flex justify-start items-center gap-2.5 text-xs text-base-content/50">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/15 text-primary text-xs font-bold shrink-0">
                                    🤖
                                </div>
                                <div class="rounded-2xl rounded-tl-xs bg-base-100 border border-base-300 px-4 py-2.5 shadow-xs flex items-center gap-2">
                                    <span class="loading loading-dots loading-xs text-primary"></span>
                                    <span class="text-[11px] font-medium text-base-content/70">Consultando motor de IA & herramientas...</span>
                                </div>
                            </div>

                        </div>

                        <!-- Píldoras de sugerencias rápidas -->
                        <div class="border-t border-base-300/60 bg-base-100 px-3 py-2 flex items-center gap-2 overflow-x-auto no-scrollbar">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/40 shrink-0">
                                Pruebas rápidas:
                            </span>
                            <button
                                v-for="(pText, pIdx) in quickPrompts"
                                :key="pIdx"
                                type="button"
                                @click="sendMessage(pText)"
                                :disabled="sending"
                                class="btn btn-xs rounded-lg border border-base-300 bg-base-200/50 hover:bg-base-200 hover:border-primary/40 font-normal shrink-0 text-[11px]"
                            >
                                {{ pText }}
                            </button>
                        </div>

                        <!-- Barra de Entrada de Texto -->
                        <div class="border-t border-base-300 p-3 bg-base-100">
                            <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
                                <input
                                    v-model="messageInput"
                                    type="text"
                                    placeholder="Escribe un mensaje para probar la respuesta del bot..."
                                    :disabled="sending"
                                    class="input input-bordered w-full rounded-xl text-xs bg-base-200/40 focus:bg-base-100"
                                />
                                <button
                                    type="submit"
                                    :disabled="sending || !messageInput.trim()"
                                    class="btn btn-primary rounded-xl px-5 text-xs font-semibold gap-1.5 shrink-0"
                                >
                                    <span v-if="!sending">Enviar</span>
                                    <span v-else class="loading loading-spinner loading-xs"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="22" y1="2" x2="11" y2="13"/>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                    </svg>
                                </button>
                            </form>
                        </div>

                    </div>

                    <!-- PANEL DERECHO: INSPECTOR DE BOT (Depuración en vivo) -->
                    <div
                        v-if="showInspector"
                        class="w-full lg:w-80 shrink-0 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-xs flex flex-col space-y-4 overflow-y-auto"
                        style="height: 680px;"
                    >
                        
                        <!-- Header del Inspector -->
                        <div class="flex items-center justify-between pb-3 border-b border-base-300">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary text-xs font-bold">
                                    🔍
                                </span>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-base-content">
                                    Inspector del Bot
                                </h3>
                            </div>
                            <button
                                type="button"
                                @click="showInspector = false"
                                class="btn btn-ghost btn-xs btn-circle text-base-content/50"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Estado Actual de la Reserva / Cita -->
                        <div class="rounded-xl border border-base-300/80 bg-base-200/30 p-3 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-base-content/70">Fase / Step</span>
                                <span class="badge badge-sm badge-primary font-mono text-[10px]">
                                    {{ lastBookingState.step || 'INICIO' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-base-300/50 text-[11px]">
                                <div>
                                    <span class="text-base-content/50 block text-[10px]">Servicio</span>
                                    <span class="font-semibold text-base-content truncate block">
                                        {{ lastBookingState.service || 'No seleccionado' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-base-content/50 block text-[10px]">Fecha</span>
                                    <span class="font-semibold text-base-content truncate block">
                                        {{ lastBookingState.date || 'Pendiente' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-base-content/50 block text-[10px]">Hora</span>
                                    <span class="font-semibold text-base-content truncate block">
                                        {{ lastBookingState.time || 'Pendiente' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-base-content/50 block text-[10px]">Cliente</span>
                                    <span class="font-semibold text-base-content truncate block">
                                        {{ lastBookingState.name || 'Sin identificar' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Últimas Herramientas Ejecutadas -->
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50 mb-2">
                                Herramientas Ejecutadas (Último Turno)
                            </div>
                            
                            <div v-if="lastToolCalls && lastToolCalls.length" class="space-y-2">
                                <div
                                    v-for="(t, idx) in lastToolCalls"
                                    :key="idx"
                                    class="rounded-xl border border-warning/30 bg-warning/5 p-2.5 text-xs font-mono"
                                >
                                    <div class="font-bold text-warning-content flex items-center justify-between">
                                        <span>🛠️ {{ t.name }}</span>
                                    </div>
                                    <div class="mt-1 text-[10px] text-base-content/70 overflow-x-auto">
                                        <div class="text-base-content/40 uppercase text-[9px]">Argumentos:</div>
                                        <pre class="bg-base-200/60 p-1.5 rounded-lg mt-0.5 whitespace-pre-wrap">{{ t.arguments }}</pre>
                                        <div class="text-base-content/40 uppercase text-[9px] mt-1.5">Resultado:</div>
                                        <pre class="bg-base-200/60 p-1.5 rounded-lg mt-0.5 whitespace-pre-wrap">{{ JSON.stringify(t.result, null, 2) }}</pre>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="py-4 text-center text-xs text-base-content/40 border border-dashed border-base-300 rounded-xl"
                            >
                                Ninguna herramienta invocada en el último turno.
                            </div>
                        </div>

                        <!-- System Prompt en Efecto -->
                        <div class="flex-1 space-y-2">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">
                                Prompt del Sistema (Instrucciones)
                            </div>
                            <div class="rounded-xl border border-base-300 bg-base-200/40 p-2.5 text-[11px] font-mono max-h-48 overflow-y-auto whitespace-pre-wrap text-base-content/70">
                                <span class="text-primary font-bold">--- PROMPT DINÁMICO ---</span>
                                <p>{{ lastPrompts.dynamic || 'Esperando primer turno de conversación...' }}</p>
                                <span class="text-secondary font-bold mt-2 block">--- PROMPT ESTÁTICO ---</span>
                                <p>{{ lastPrompts.static || currentInstance?.system_prompt || 'Políticas base del negocio...' }}</p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
