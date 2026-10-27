<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

// PrimeVue Components (Aura Preset & Neutral palette)
import Card from 'primevue/card';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Checkbox from 'primevue/checkbox';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';

// State
const inputQuery = ref('');
const isLoading = ref(false);
const isFeedLoading = ref(false);

const events = ref([]);
const reminders = ref([]);
const notes = ref([]);

const lastMessage = ref(null);
const lastSuccess = ref(true);
const lastAction = ref('');

// Tabs State
const activeTab = ref('workspace'); // 'workspace' | 'conversations'

// Conversations State
const conversations = ref([]);
const isConversationsLoading = ref(false);
const conversationFilterChannel = ref('all'); // 'all' | 'app' | 'whatsapp'
const conversationSearch = ref('');
const copiedMessageId = ref(null);
let searchDebounceTimeout = null;

// Fetch Conversations History
async function fetchConversations() {
    isConversationsLoading.value = true;
    try {
        const params = {};
        if (conversationFilterChannel.value && conversationFilterChannel.value !== 'all') {
            params.channel = conversationFilterChannel.value;
        }
        if (conversationSearch.value.trim()) {
            params.search = conversationSearch.value.trim();
        }

        const response = await axios.get('/api/v1/assistant/conversations', { params });
        if (response.data && response.data.success) {
            conversations.value = response.data.data || [];
        }
    } catch (error) {
        console.error('Error al cargar conversaciones del asistente:', error);
    } finally {
        isConversationsLoading.value = false;
    }
}

function handleChannelFilter(channel) {
    if (conversationFilterChannel.value === channel) return;
    conversationFilterChannel.value = channel;
    fetchConversations();
}

function handleSearchInput() {
    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(() => {
        fetchConversations();
    }, 350);
}

function clearSearch() {
    conversationSearch.value = '';
    fetchConversations();
}

async function copyToClipboard(text, id) {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copiedMessageId.value = id;
        setTimeout(() => {
            if (copiedMessageId.value === id) {
                copiedMessageId.value = null;
            }
        }, 2000);
    } catch (e) {
        console.error('No se pudo copiar el texto:', e);
    }
}


// Google Calendar Integration State
const isConfigDialogOpen = ref(false);
const isSyncing = ref(false);
const isTesting = ref(false);
const isSavingSettings = ref(false);
const testResult = ref(null);

const calendarSettings = ref({
    calendar_id: 'primary',
    timezone: 'America/Cancun',
    sync_enabled: false,
    auto_sync_on_create: true,
    service_account_json: '',
    has_service_account: false,
    last_synced_at: null,
});

// Manual Event Creation Dialog State
const isNewEventDialogOpen = ref(false);
const isSavingEvent = ref(false);
const newEventForm = ref({
    title: '',
    start_time: '',
    end_time: '',
    location: '',
    description: '',
});

// Suggestions chips (Notion/Linear style)
const quickPrompts = [
    '📅 Agendar cita médica mañana a las 11:00 en la clínica',
    '📅 Pon en mi calendario reunión de equipo el viernes a las 15:00',
    '⏰ Recordarme revisar reportes contables el viernes a las 16:00',
    '📝 Nota: Resumen de requerimientos para el cliente',
    '🔍 ¿Qué citas tengo programadas para esta semana?',
];

// Fetch Feed
async function fetchFeed() {
    isFeedLoading.value = true;
    try {
        const response = await axios.get('/api/v1/assistant/feed');
        if (response.data && response.data.success) {
            events.value = response.data.data.events || [];
            reminders.value = response.data.data.reminders || [];
            notes.value = response.data.data.notes || [];
        }
    } catch (error) {
        console.error('Error al cargar feed del asistente:', error);
    } finally {
        isFeedLoading.value = false;
    }
}

// Fetch Google Calendar Settings
async function fetchCalendarSettings() {
    try {
        const response = await axios.get('/api/v1/assistant/google-calendar/settings');
        if (response.data && response.data.success) {
            const data = response.data.data || {};
            calendarSettings.value.calendar_id = data.calendar_id || 'primary';
            calendarSettings.value.timezone = data.timezone || 'America/Cancun';
            calendarSettings.value.sync_enabled = Boolean(data.sync_enabled);
            calendarSettings.value.auto_sync_on_create = data.auto_sync_on_create !== false;
            calendarSettings.value.has_service_account = Boolean(data.has_service_account);
            calendarSettings.value.last_synced_at = data.last_synced_at || null;
        }
    } catch (error) {
        console.error('Error al obtener configuración de Google Calendar:', error);
    }
}

// Save Google Calendar Settings
async function saveCalendarSettings() {
    isSavingSettings.value = true;
    testResult.value = null;

    try {
        const payload = {
            calendar_id: calendarSettings.value.calendar_id,
            timezone: calendarSettings.value.timezone,
            sync_enabled: calendarSettings.value.sync_enabled,
            auto_sync_on_create: calendarSettings.value.auto_sync_on_create,
        };

        if (calendarSettings.value.service_account_json.trim()) {
            payload.service_account_json = calendarSettings.value.service_account_json.trim();
        }

        const response = await axios.post('/api/v1/assistant/google-calendar/settings', payload);
        if (response.data && response.data.success) {
            calendarSettings.value.has_service_account = Boolean(response.data.data?.has_service_account);
            calendarSettings.value.service_account_json = '';
            testResult.value = {
                success: true,
                message: 'Configuración guardada exitosamente.',
            };
            setTimeout(() => {
                isConfigDialogOpen.value = false;
                testResult.value = null;
            }, 1200);
        }
    } catch (error) {
        console.error('Error al guardar configuración de Google Calendar:', error);
        testResult.value = {
            success: false,
            message: error.response?.data?.message || 'Error al guardar la configuración.',
        };
    } finally {
        isSavingSettings.value = false;
    }
}

// Test Connection with Google Calendar
async function testCalendarConnection() {
    isTesting.value = true;
    testResult.value = null;

    try {
        if (calendarSettings.value.service_account_json.trim()) {
            await saveCalendarSettings();
        }

        const response = await axios.post('/api/v1/assistant/google-calendar/test');
        testResult.value = {
            success: Boolean(response.data?.success),
            message: response.data?.message || 'Conexión verificada con éxito.',
        };
    } catch (error) {
        testResult.value = {
            success: false,
            message: error.response?.data?.message || 'No se pudo conectar con Google Calendar.',
        };
    } finally {
        isTesting.value = false;
    }
}

// Manual Sync with Google Calendar
async function syncGoogleCalendar() {
    if (isSyncing.value) return;
    isSyncing.value = true;

    try {
        const response = await axios.post('/api/v1/assistant/google-calendar/sync');
        if (response.data && response.data.success) {
            lastMessage.value = response.data.message || 'Sincronización con Google Calendar completada.';
            lastSuccess.value = true;
            lastAction.value = 'google_calendar_sync';
            calendarSettings.value.last_synced_at = response.data.data?.last_synced_at || new Date().toISOString();
            await fetchFeed();
            await fetchConversations();
        }
    } catch (error) {
        console.error('Error al sincronizar con Google Calendar:', error);
        lastMessage.value = error.response?.data?.message || 'Error al sincronizar con Google Calendar. Verifique la configuración.';
        lastSuccess.value = false;
        lastAction.value = 'error';
    } finally {
        isSyncing.value = false;
    }
}

// Open manual event creation modal
function openNewEventDialog() {
    const now = new Date();
    const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    const yyyy = tomorrow.getFullYear();
    const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const dd = String(tomorrow.getDate()).padStart(2, '0');

    newEventForm.value = {
        title: '',
        start_time: `${yyyy}-${mm}-${dd}T10:00`,
        end_time: `${yyyy}-${mm}-${dd}T11:00`,
        location: '',
        description: '',
    };
    isNewEventDialogOpen.value = true;
}

// Save manual event
async function saveManualEvent() {
    const title = newEventForm.value.title.trim();
    if (!title || !newEventForm.value.start_time) return;

    isSavingEvent.value = true;
    try {
        const response = await axios.post('/api/v1/assistant/events', newEventForm.value);
        if (response.data && response.data.success) {
            lastMessage.value = response.data.message || 'Cita agendada correctamente.';
            lastSuccess.value = true;
            lastAction.value = 'create_calendar_event';
            isNewEventDialogOpen.value = false;
            await fetchFeed();
        }
    } catch (error) {
        console.error('Error al guardar cita:', error);
        alert(error.response?.data?.message || 'Error al guardar la cita en el calendario.');
    } finally {
        isSavingEvent.value = false;
    }
}

// Delete calendar event
async function deleteEvent(event) {
    const id = event.id || event._id;
    if (!id) return;

    if (!confirm(`¿Eliminar la cita "${event.title}" del calendario?`)) return;

    try {
        const response = await axios.delete(`/api/v1/assistant/events/${id}`);
        if (response.data && response.data.success) {
            events.value = events.value.filter((e) => (e.id || e._id) !== id);
        }
    } catch (error) {
        console.error('Error al eliminar evento:', error);
    }
}

// Submit prompt to orchestrator (AI)
async function submitPrompt(customPrompt = null) {
    const promptText = (customPrompt || inputQuery.value || '').trim();
    if (!promptText || isLoading.value) return;

    isLoading.value = true;
    try {
        const response = await axios.post('/api/v1/assistant/interact', {
            prompt: promptText,
        });

        if (response.data) {
            lastMessage.value = response.data.message || 'Comando procesado correctamente.';
            lastSuccess.value = response.data.success !== false;
            lastAction.value = response.data.action || 'conversation';
            inputQuery.value = '';
            await fetchFeed();
        }
    } catch (error) {
        console.error('Error en interacción con el asistente:', error);
        lastMessage.value = error.response?.data?.message || 'Error al comunicarse con el motor del asistente.';
        lastSuccess.value = false;
        lastAction.value = 'error';
    } finally {
        isLoading.value = false;
    }
}

// Complete reminder
async function completeReminder(reminder) {
    const id = reminder.id || reminder._id;
    if (!id || reminder.status === 'completed') return;

    // Optimistic UI update
    reminder.status = 'completed';

    try {
        const response = await axios.patch(`/api/v1/reminders/${id}/complete`);
        if (!response.data || !response.data.success) {
            reminder.status = 'pending';
        }
    } catch (error) {
        console.error('Error al completar recordatorio:', error);
        reminder.status = 'pending';
    }
}

// Computed
const pendingRemindersCount = computed(() => {
    return reminders.value.filter((r) => r.status === 'pending').length;
});

// Formatters (America/Cancun)
function formatEventTimeRange(startVal, endVal) {
    if (!startVal) return '';
    try {
        const start = new Date(startVal);
        const datePart = new Intl.DateTimeFormat('es-MX', {
            timeZone: 'America/Cancun',
            month: 'short',
            day: 'numeric',
        }).format(start);

        const startTime = new Intl.DateTimeFormat('es-MX', {
            timeZone: 'America/Cancun',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(start);

        if (!endVal) {
            return `${datePart}, ${startTime}`;
        }

        const end = new Date(endVal);
        const endTime = new Intl.DateTimeFormat('es-MX', {
            timeZone: 'America/Cancun',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(end);

        return `${datePart}, ${startTime} - ${endTime}`;
    } catch {
        return startVal;
    }
}

function formatReminderDate(val) {
    if (!val) return '';
    try {
        const date = new Date(val);
        return new Intl.DateTimeFormat('es-MX', {
            timeZone: 'America/Cancun',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(date);
    } catch {
        return val;
    }
}

function getPriorityTag(priority) {
    switch (priority) {
        case 'high':
            return { label: 'Alta', severity: 'danger' };
        case 'low':
            return { label: 'Baja', severity: 'info' };
        default:
            return { label: 'Media', severity: 'warn' };
    }
}

function getActionName(action) {
    switch (action) {
        case 'create_calendar_event':
            return 'Cita Agendada';
        case 'create_reminder':
            return 'Recordatorio Creado';
        case 'create_workspace_note':
            return 'Nota Guardada';
        case 'get_agenda':
            return 'Agenda Consultada';
        case 'search_calendar_events':
            return '🔍 Citas Consultadas';
        case 'search_notes':
            return '📝 Notas Consultadas';
        case 'get_pending_reminders':
            return '⏰ Recordatorios Consultados';
        case 'google_calendar_sync':
            return 'Google Calendar Sincronizado';
        case 'conversation':
            return 'Respuesta del Asistente';
        default:
            return 'Acción Ejecutada';
    }
}

onMounted(() => {
    fetchFeed();
    fetchCalendarSettings();
    fetchConversations();
});
</script>

<template>
    <Head title="Workspace Personal" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-surface-50 dark:bg-surface-950 py-6 px-4 sm:px-6 lg:px-8 font-sans">
            <div class="mx-auto max-w-7xl space-y-6">

                <!-- 1. HEADER & STATUS -->
                <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-2 border-b border-surface-200 dark:border-surface-800">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                                Workspace Personal
                            </h1>
                            <Tag
                                value="Online"
                                severity="success"
                                rounded
                                class="text-[11px] font-medium tracking-wide px-2 py-0.5"
                            />
                            <Tag
                                v-if="calendarSettings.sync_enabled"
                                value="G-Cal Activo"
                                severity="info"
                                rounded
                                class="text-[10px] font-mono px-2 py-0.5"
                            />
                        </div>
                        <p class="mt-1 flex items-center gap-1.5 text-xs text-surface-500 dark:text-surface-400">
                            <i class="pi pi-clock text-[10px]"></i>
                            <span>Zona Horaria: <strong class="font-mono text-surface-700 dark:text-surface-300">America/Cancun (UTC-5)</strong></span>
                            <span v-if="calendarSettings.last_synced_at" class="text-surface-400">
                                • Sincronizado: {{ formatReminderDate(calendarSettings.last_synced_at) }}
                            </span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <!-- Sincronizar con Google Calendar -->
                        <Button
                            label="Sincronizar"
                            icon="pi pi-sync"
                            size="small"
                            severity="secondary"
                            variant="outlined"
                            :loading="isSyncing"
                            @click="syncGoogleCalendar"
                            class="!text-xs !font-medium !border-surface-300 dark:!border-surface-700 !text-surface-700 dark:!text-surface-200"
                        />

                        <!-- Configurar Opciones de Google Calendar -->
                        <Button
                            icon="pi pi-cog"
                            size="small"
                            severity="secondary"
                            variant="outlined"
                            aria-label="Configurar Google Calendar"
                            @click="isConfigDialogOpen = true"
                            class="!border-surface-300 dark:!border-surface-700 !text-surface-600 dark:!text-surface-300"
                        />

                        <!-- Refrescar Feed -->
                        <Button
                            icon="pi pi-refresh"
                            severity="secondary"
                            variant="outlined"
                            size="small"
                            :loading="isFeedLoading"
                            @click="fetchFeed"
                            aria-label="Actualizar datos"
                            class="!border-surface-300 dark:!border-surface-700 !text-surface-600 dark:!text-surface-300"
                        />
                    </div>
                </header>

                <!-- 2. TABS DE NAVEGACIÓN -->
                <nav class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-surface-200 dark:border-surface-800 pb-2">
                    <div class="inline-flex items-center p-1 rounded-xl bg-surface-100 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 text-xs shadow-xs">
                        <button
                            type="button"
                            @click="activeTab = 'workspace'"
                            :class="[
                                'flex items-center gap-2 px-3.5 py-1.5 rounded-lg font-medium transition-all duration-150 cursor-pointer active:scale-95',
                                activeTab === 'workspace'
                                    ? 'bg-surface-0 dark:bg-surface-800 text-surface-900 dark:text-surface-0 shadow-xs'
                                    : 'text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100'
                            ]"
                        >
                            <i class="pi pi-th-large text-xs"></i>
                            <span>Mi Espacio</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'conversations'"
                            :class="[
                                'flex items-center gap-2 px-3.5 py-1.5 rounded-lg font-medium transition-all duration-150 cursor-pointer active:scale-95',
                                activeTab === 'conversations'
                                    ? 'bg-surface-0 dark:bg-surface-800 text-surface-900 dark:text-surface-0 shadow-xs'
                                    : 'text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100'
                            ]"
                        >
                            <i class="pi pi-comments text-xs"></i>
                            <span>Conversaciones</span>
                            <Tag
                                v-if="conversations.length"
                                :value="conversations.length"
                                severity="secondary"
                                rounded
                                class="text-[10px] font-mono px-1.5 py-0.2"
                            />
                        </button>
                    </div>

                    <!-- Controles rápidos del tab de conversaciones -->
                    <div v-if="activeTab === 'conversations'" class="flex items-center gap-2">
                        <span class="text-xs text-surface-500 dark:text-surface-400 font-mono hidden sm:inline">
                            {{ conversations.length }} {{ conversations.length === 1 ? 'conversación' : 'conversaciones' }}
                        </span>
                        <Button
                            icon="pi pi-refresh"
                            size="small"
                            severity="secondary"
                            variant="outlined"
                            label="Actualizar"
                            :loading="isConversationsLoading"
                            @click="fetchConversations"
                            class="!text-xs !border-surface-300 dark:!border-surface-700 !text-surface-600 dark:!text-surface-300"
                        />
                    </div>
                </nav>

                <!-- TAB 1: MI ESPACIO -->
                <div v-show="activeTab === 'workspace'" class="space-y-6">


                <!-- 2. OMNIBAR DE COMANDOS -->
                <section class="space-y-3">
                    <div class="relative rounded-xl bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-2 shadow-xs transition-shadow duration-150 focus-within:border-surface-400 dark:focus-within:border-surface-600 focus-within:shadow-sm">
                        <div class="flex items-center gap-2">
                            <IconField class="flex-1">
                                <InputIcon class="pi pi-sparkles text-surface-400 dark:text-surface-500 text-sm" />
                                <InputText
                                    v-model="inputQuery"
                                    type="text"
                                    :disabled="isLoading"
                                    placeholder="Escribe un comando... ej. 'Pon en mi calendario cita con el dentista el viernes a las 4pm'"
                                    class="w-full !border-0 !bg-transparent !shadow-none !text-sm text-surface-900 dark:text-surface-100 placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:!ring-0"
                                    @keydown.enter.prevent="submitPrompt()"
                                />
                            </IconField>

                            <Button
                                label="Enviar"
                                icon="pi pi-arrow-right"
                                iconPos="right"
                                size="small"
                                :loading="isLoading"
                                :disabled="isLoading || !inputQuery.trim()"
                                @click="submitPrompt()"
                                class="!px-3.5 !py-1.5 !text-xs !font-medium"
                            />
                        </div>
                    </div>

                    <!-- Quick Prompts Chips -->
                    <div class="flex flex-wrap items-center gap-1.5 px-1">
                        <span class="text-[11px] font-medium text-surface-400 dark:text-surface-500 uppercase tracking-wider mr-1">
                            Sugerencias:
                        </span>
                        <button
                            v-for="(prompt, idx) in quickPrompts"
                            :key="idx"
                            type="button"
                            :disabled="isLoading"
                            @click="submitPrompt(prompt)"
                            class="rounded-md border border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 px-2.5 py-1 text-xs text-surface-600 dark:text-surface-300 hover:border-surface-400 dark:hover:border-surface-600 hover:text-surface-900 dark:hover:text-surface-100 transition-colors cursor-pointer"
                        >
                            {{ prompt }}
                        </button>
                    </div>

                    <!-- LLM Feedback Message Area -->
                    <transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <Message
                            v-if="lastMessage"
                            :severity="lastSuccess ? 'secondary' : 'error'"
                            :closable="true"
                            @close="lastMessage = null"
                            class="!border !border-surface-200 dark:!border-surface-800 !bg-surface-0 dark:!bg-surface-900 !text-surface-800 dark:!text-surface-200"
                        >
                            <template #messageicon>
                                <i :class="lastSuccess ? 'pi pi-check-circle text-emerald-500' : 'pi pi-exclamation-circle text-rose-500'"></i>
                            </template>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <Tag
                                        :value="getActionName(lastAction)"
                                        severity="secondary"
                                        class="text-[10px] uppercase font-mono px-1.5 py-0.5"
                                    />
                                </div>
                                <p class="text-sm font-normal leading-relaxed whitespace-pre-line text-surface-700 dark:text-surface-300">
                                    {{ lastMessage }}
                                </p>
                            </div>
                        </Message>
                    </transition>
                </section>

                <!-- 3. LAYOUT BENTO GRID (3 COLUMNAS NOTION/LINEAR CON CARD DE PRIMEVUE) -->
                <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

                    <!-- COLUMNA 1: CITAS & EVENTOS -->
                    <Card class="!border !border-surface-200 dark:!border-surface-800 !bg-surface-0 dark:!bg-surface-900 !shadow-xs !rounded-xl overflow-hidden">
                        <template #title>
                            <div class="flex items-center justify-between pb-3 border-b border-surface-100 dark:border-surface-800">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-calendar text-surface-500 dark:text-surface-400 text-sm"></i>
                                    <h2 class="text-sm font-semibold tracking-tight text-surface-900 dark:text-surface-100 m-0">
                                        Citas & Eventos
                                    </h2>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Button
                                        icon="pi pi-plus"
                                        size="small"
                                        severity="secondary"
                                        variant="outlined"
                                        label="Nueva"
                                        @click="openNewEventDialog"
                                        class="!h-6 !text-[11px] !px-2 !py-0 !border-surface-300 dark:!border-surface-700"
                                        aria-label="Agregar cita manualmente"
                                    />
                                    <Button
                                        icon="pi pi-sync"
                                        size="small"
                                        severity="secondary"
                                        text
                                        rounded
                                        :loading="isSyncing"
                                        @click="syncGoogleCalendar"
                                        class="!h-6 !w-6 !p-0 text-surface-500"
                                        aria-label="Sincronizar eventos"
                                    />
                                    <Tag
                                        :value="events.length"
                                        severity="secondary"
                                        rounded
                                        class="font-mono text-xs px-2 py-0.5"
                                    />
                                </div>
                            </div>
                        </template>

                        <template #content>
                            <div class="space-y-2.5 mt-1 max-h-[520px] overflow-y-auto pr-1">
                                <div
                                    v-for="event in events"
                                    :key="event.id || event._id"
                                    class="group rounded-lg border border-surface-200/80 dark:border-surface-800/80 bg-surface-50/50 dark:bg-surface-950/40 p-3 hover:border-surface-300 dark:hover:border-surface-700 transition-colors relative"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-1.5">
                                            <h3 class="text-xs font-medium text-surface-900 dark:text-surface-100 leading-snug">
                                                {{ event.title }}
                                            </h3>
                                            <span
                                                v-if="event.google_event_id"
                                                class="inline-flex items-center text-[10px] text-blue-500"
                                                title="Sincronizado con Google Calendar"
                                            >
                                                <i class="pi pi-google text-[10px]"></i>
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <Tag
                                                :value="formatEventTimeRange(event.start_time, event.end_time)"
                                                severity="secondary"
                                                class="font-mono text-[11px] shrink-0 font-normal px-1.5 py-0.5"
                                            />
                                            <button
                                                type="button"
                                                @click="deleteEvent(event)"
                                                class="opacity-0 group-hover:opacity-100 text-surface-400 hover:text-red-500 transition-opacity ml-1 cursor-pointer p-0.5"
                                                title="Eliminar del calendario"
                                            >
                                                <i class="pi pi-trash text-[11px]"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <p v-if="event.description" class="mt-1.5 text-xs text-surface-500 dark:text-surface-400 line-clamp-2 leading-relaxed">
                                        {{ event.description }}
                                    </p>

                                    <div v-if="event.location" class="mt-2 flex items-center gap-1.5 text-[11px] text-surface-400 dark:text-surface-500">
                                        <i class="pi pi-map-marker text-[10px]"></i>
                                        <span class="truncate">{{ event.location }}</span>
                                    </div>
                                </div>

                                <!-- Estado Vacío -->
                                <div
                                    v-if="!events.length"
                                    class="py-12 flex flex-col items-center justify-center text-center text-surface-400 dark:text-surface-500"
                                >
                                    <i class="pi pi-calendar-times text-2xl mb-2 opacity-60"></i>
                                    <p class="text-xs font-medium">No hay eventos pendientes</p>
                                    <p class="text-[11px] text-surface-400">Pide al asistente "Agendar cita..." o presiona "+ Nueva".</p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- COLUMNA 2: RECORDATORIOS & TAREAS -->
                    <Card class="!border !border-surface-200 dark:!border-surface-800 !bg-surface-0 dark:!bg-surface-900 !shadow-xs !rounded-xl overflow-hidden">
                        <template #title>
                            <div class="flex items-center justify-between pb-3 border-b border-surface-100 dark:border-surface-800">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-bell text-surface-500 dark:text-surface-400 text-sm"></i>
                                    <h2 class="text-sm font-semibold tracking-tight text-surface-900 dark:text-surface-100 m-0">
                                        Recordatorios & Tareas
                                    </h2>
                                </div>
                                <Tag
                                    :value="pendingRemindersCount"
                                    :severity="pendingRemindersCount > 0 ? 'warn' : 'secondary'"
                                    rounded
                                    class="font-mono text-xs px-2 py-0.5"
                                />
                            </div>
                        </template>

                        <template #content>
                            <div class="space-y-2.5 mt-1 max-h-[520px] overflow-y-auto pr-1">
                                <div
                                    v-for="reminder in reminders"
                                    :key="reminder.id || reminder._id"
                                    class="rounded-lg border border-surface-200/80 dark:border-surface-800/80 bg-surface-50/50 dark:bg-surface-950/40 p-3 hover:border-surface-300 dark:hover:border-surface-700 transition-colors"
                                    :class="{ 'opacity-50': reminder.status === 'completed' }"
                                >
                                    <div class="flex items-start gap-2.5">
                                        <div class="pt-0.5">
                                            <Checkbox
                                                :modelValue="reminder.status === 'completed'"
                                                :binary="true"
                                                :disabled="reminder.status === 'completed'"
                                                @update:modelValue="completeReminder(reminder)"
                                                aria-label="Completar recordatorio"
                                                class="scale-90"
                                            />
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-1.5">
                                                <span
                                                    class="text-xs font-medium leading-snug break-words transition-all"
                                                    :class="reminder.status === 'completed' ? 'line-through text-surface-400 dark:text-surface-500' : 'text-surface-900 dark:text-surface-100'"
                                                >
                                                    {{ reminder.title }}
                                                </span>
                                                <Tag
                                                    v-if="reminder.priority"
                                                    :value="getPriorityTag(reminder.priority).label"
                                                    :severity="getPriorityTag(reminder.priority).severity"
                                                    class="text-[10px] font-normal px-1 py-0.5 shrink-0"
                                                />
                                            </div>

                                            <div class="mt-1.5 flex items-center justify-between text-[11px] text-surface-400 dark:text-surface-500 font-mono">
                                                <span>{{ formatReminderDate(reminder.scheduled_at) }}</span>
                                                <span v-if="reminder.status === 'completed'" class="text-emerald-500 font-sans text-[10px] font-medium">
                                                    ✓ Completado
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Estado Vacío -->
                                <div
                                    v-if="!reminders.length"
                                    class="py-12 flex flex-col items-center justify-center text-center text-surface-400 dark:text-surface-500"
                                >
                                    <i class="pi pi-check-circle text-2xl mb-2 opacity-60"></i>
                                    <p class="text-xs font-medium">No hay recordatorios pendientes</p>
                                    <p class="text-[11px] text-surface-400">Crea recordatorios con alertas en el Omnibar.</p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- COLUMNA 3: NOTAS & BITÁCORA -->
                    <Card class="!border !border-surface-200 dark:!border-surface-800 !bg-surface-0 dark:!bg-surface-900 !shadow-xs !rounded-xl overflow-hidden">
                        <template #title>
                            <div class="flex items-center justify-between pb-3 border-b border-surface-100 dark:border-surface-800">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-file-edit text-surface-500 dark:text-surface-400 text-sm"></i>
                                    <h2 class="text-sm font-semibold tracking-tight text-surface-900 dark:text-surface-100 m-0">
                                        Notas & Bitácora
                                    </h2>
                                </div>
                                <Tag
                                    :value="notes.length"
                                    severity="secondary"
                                    rounded
                                    class="font-mono text-xs px-2 py-0.5"
                                />
                            </div>
                        </template>

                        <template #content>
                            <div class="space-y-2.5 mt-1 max-h-[520px] overflow-y-auto pr-1">
                                <div
                                    v-for="note in notes"
                                    :key="note.id || note._id"
                                    class="rounded-lg border border-surface-200/80 dark:border-surface-800/80 bg-surface-50/50 dark:bg-surface-950/40 p-3 hover:border-surface-400 dark:hover:border-surface-600 hover:shadow-xs transition-all cursor-pointer group"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="text-xs font-medium text-surface-900 dark:text-surface-100 group-hover:text-surface-700 dark:group-hover:text-surface-300 transition-colors">
                                            {{ note.title }}
                                        </h3>
                                        <i v-if="note.is_pinned" class="pi pi-bookmark-fill text-amber-500 text-xs shrink-0" title="Nota fijada"></i>
                                    </div>

                                    <p class="mt-1.5 text-xs text-surface-600 dark:text-surface-300 line-clamp-3 leading-relaxed whitespace-pre-line">
                                        {{ note.content }}
                                    </p>

                                    <!-- Tags Temáticos (#tag) -->
                                    <div v-if="note.tags && note.tags.length" class="mt-2.5 flex flex-wrap gap-1">
                                        <span
                                            v-for="tag in note.tags"
                                            :key="tag"
                                            class="text-[10px] font-mono text-surface-500 dark:text-surface-400 bg-surface-200/60 dark:bg-surface-800/60 rounded px-1.5 py-0.5 hover:text-surface-800 dark:hover:text-surface-200 transition-colors"
                                        >
                                            #{{ tag }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Estado Vacío -->
                                <div
                                    v-if="!notes.length"
                                    class="py-12 flex flex-col items-center justify-center text-center text-surface-400 dark:text-surface-500"
                                >
                                    <i class="pi pi-book text-2xl mb-2 opacity-60"></i>
                                    <p class="text-xs font-medium">No hay notas guardadas</p>
                                    <p class="text-[11px] text-surface-400">Guarda apuntes y referencias rápidas con el asistente.</p>
                                </div>
                            </div>
                        </template>
                    </Card>

                </main>

                </div>
                <!-- FIN TAB 1: MI ESPACIO -->

                <!-- TAB 2: CONVERSACIONES -->
                <div v-show="activeTab === 'conversations'" class="space-y-6">

                    <!-- Filtros de Canal y Búsqueda -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3 rounded-xl bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 shadow-xs">
                        <!-- Filtros por Canal -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[11px] font-medium text-surface-400 uppercase tracking-wider mr-1">Canal:</span>
                            <button
                                type="button"
                                @click="handleChannelFilter('all')"
                                :class="[
                                    'px-2.5 py-1 rounded-md text-xs font-medium transition-all cursor-pointer',
                                    conversationFilterChannel === 'all'
                                        ? 'bg-surface-900 text-surface-0 dark:bg-surface-100 dark:text-surface-950 shadow-xs'
                                        : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100'
                                ]"
                            >
                                Todas
                            </button>

                            <button
                                type="button"
                                @click="handleChannelFilter('app')"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-all cursor-pointer',
                                    conversationFilterChannel === 'app'
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100'
                                ]"
                            >
                                <i class="pi pi-desktop text-[11px]"></i>
                                <span>Web / App</span>
                            </button>

                            <button
                                type="button"
                                @click="handleChannelFilter('whatsapp')"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-all cursor-pointer',
                                    conversationFilterChannel === 'whatsapp'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100'
                                ]"
                            >
                                <i class="pi pi-whatsapp text-[11px]"></i>
                                <span>WhatsApp</span>
                            </button>
                        </div>

                        <!-- Buscador -->
                        <div class="relative flex-1 sm:max-w-xs">
                            <IconField class="w-full">
                                <InputIcon class="pi pi-search text-surface-400 text-xs" />
                                <InputText
                                    v-model="conversationSearch"
                                    type="text"
                                    placeholder="Buscar en preguntas o respuestas..."
                                    class="w-full !text-xs !py-1.5 !pl-8 !pr-8"
                                    @input="handleSearchInput"
                                    @keydown.enter.prevent="fetchConversations"
                                />
                            </IconField>
                            <button
                                v-if="conversationSearch"
                                type="button"
                                @click="clearSearch"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 dark:hover:text-surface-200 cursor-pointer"
                                aria-label="Limpiar búsqueda"
                            >
                                <i class="pi pi-times text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Omnibar Rápido dentro del Tab de Conversaciones -->
                    <div class="relative rounded-xl bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-800 p-2 shadow-xs transition-shadow duration-150 focus-within:border-surface-400 dark:focus-within:border-surface-600 focus-within:shadow-sm">
                        <div class="flex items-center gap-2">
                            <IconField class="flex-1">
                                <InputIcon class="pi pi-sparkles text-surface-400 dark:text-surface-500 text-sm" />
                                <InputText
                                    v-model="inputQuery"
                                    type="text"
                                    :disabled="isLoading"
                                    placeholder="Conversa con tu asistente o envía una orden aquí..."
                                    class="w-full !border-0 !bg-transparent !shadow-none !text-sm text-surface-900 dark:text-surface-100 placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:!ring-0"
                                    @keydown.enter.prevent="submitPrompt()"
                                />
                            </IconField>

                            <Button
                                label="Enviar"
                                icon="pi pi-arrow-right"
                                iconPos="right"
                                size="small"
                                :loading="isLoading"
                                :disabled="isLoading || !inputQuery.trim()"
                                @click="submitPrompt()"
                                class="!px-3.5 !py-1.5 !text-xs !font-medium"
                            />
                        </div>
                    </div>

                    <!-- Estado de Carga -->
                    <div v-if="isConversationsLoading" class="py-12 flex flex-col items-center justify-center text-center text-surface-400 dark:text-surface-500 space-y-3">
                        <i class="pi pi-spin pi-spinner text-2xl text-surface-500"></i>
                        <p class="text-xs font-medium">Cargando historial de conversaciones...</p>
                    </div>

                    <!-- Listado de Conversaciones -->
                    <div v-else-if="conversations.length" class="space-y-4">
                        <article
                            v-for="conv in conversations"
                            :key="conv.id"
                            class="rounded-xl border border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 p-4 shadow-xs hover:border-surface-300 dark:hover:border-surface-700 transition-all duration-150 space-y-3.5"
                        >
                            <!-- Cabecera de la Conversación -->
                            <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-surface-100 dark:border-surface-800/80">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- Badge de Canal -->
                                    <span
                                        v-if="conv.channel === 'whatsapp'"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                                    >
                                        <i class="pi pi-whatsapp text-[11px]"></i>
                                        <span>WhatsApp</span>
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20"
                                    >
                                        <i class="pi pi-desktop text-[11px]"></i>
                                        <span>Web / App</span>
                                    </span>

                                    <!-- Tag de Acción Ejecutada -->
                                    <Tag
                                        :value="getActionName(conv.action)"
                                        severity="secondary"
                                        class="text-[10px] font-mono px-2 py-0.5"
                                    />

                                    <!-- Teléfono de WhatsApp si existe -->
                                    <span v-if="conv.metadata?.phone" class="text-[11px] text-surface-400 font-mono">
                                        • {{ conv.metadata.phone }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- Status -->
                                    <Tag
                                        v-if="conv.status === 'failed'"
                                        value="Fallido"
                                        severity="danger"
                                        rounded
                                        class="text-[10px] px-2 py-0.5"
                                    />
                                    <Tag
                                        v-else-if="conv.status === 'processing' || conv.status === 'received'"
                                        value="En proceso"
                                        severity="warn"
                                        rounded
                                        class="text-[10px] px-2 py-0.5"
                                    />

                                    <!-- Fecha / Hora -->
                                    <span class="text-[11px] text-surface-400 dark:text-surface-500 font-mono">
                                        {{ formatReminderDate(conv.created_at) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Mensaje del Usuario (Prompt) -->
                            <div class="flex items-start gap-3">
                                <div class="w-7 h-7 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center shrink-0 text-surface-600 dark:text-surface-300">
                                    <i class="pi pi-user text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-surface-500 dark:text-surface-400 mb-0.5">Tú</p>
                                    <p class="text-sm font-normal text-surface-900 dark:text-surface-100 leading-relaxed break-words whitespace-pre-line">
                                        {{ conv.prompt }}
                                    </p>
                                </div>
                            </div>

                            <!-- Respuesta del Asistente (Reply) -->
                            <div class="rounded-lg bg-surface-50/80 dark:bg-surface-950/60 border border-surface-200/60 dark:border-surface-800/60 p-3.5 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full bg-surface-200 dark:bg-surface-800 flex items-center justify-center text-surface-700 dark:text-surface-300">
                                            <i class="pi pi-sparkles text-[10px] text-emerald-500"></i>
                                        </div>
                                        <span class="text-xs font-semibold text-surface-800 dark:text-surface-200">
                                            Asistente
                                        </span>
                                    </div>

                                    <!-- Botón de Copiar Respuesta -->
                                    <button
                                        v-if="conv.reply"
                                        type="button"
                                        @click="copyToClipboard(conv.reply, conv.id)"
                                        class="inline-flex items-center gap-1 text-[11px] text-surface-400 hover:text-surface-700 dark:hover:text-surface-200 transition-colors cursor-pointer px-1.5 py-0.5 rounded hover:bg-surface-200/50 dark:hover:bg-surface-800/50"
                                        title="Copiar respuesta"
                                    >
                                        <i :class="copiedMessageId === conv.id ? 'pi pi-check text-emerald-500' : 'pi pi-copy'"></i>
                                        <span>{{ copiedMessageId === conv.id ? 'Copiado' : 'Copiar' }}</span>
                                    </button>
                                </div>

                                <!-- Texto de la Respuesta -->
                                <p v-if="conv.reply" class="text-xs sm:text-sm font-normal leading-relaxed text-surface-700 dark:text-surface-300 whitespace-pre-line break-words pl-7">
                                    {{ conv.reply }}
                                </p>

                                <!-- Error si falló -->
                                <div v-if="conv.error_message" class="mt-2 text-xs text-rose-500 flex items-center gap-1.5 pl-7">
                                    <i class="pi pi-exclamation-triangle text-xs"></i>
                                    <span>{{ conv.error_message }}</span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- Estado Vacío -->
                    <div
                        v-else
                        class="py-16 rounded-xl border border-dashed border-surface-200 dark:border-surface-800 flex flex-col items-center justify-center text-center text-surface-400 dark:text-surface-500 space-y-2"
                    >
                        <i class="pi pi-comments text-3xl mb-1 opacity-60"></i>
                        <p class="text-sm font-medium text-surface-700 dark:text-surface-300">
                            {{ conversationSearch ? 'No se encontraron conversaciones con ese criterio' : 'No hay conversaciones registradas aún' }}
                        </p>
                        <p class="text-xs text-surface-400 max-w-md">
                            {{ conversationSearch ? 'Intenta buscar con otros términos o limpia el filtro.' : 'Envía un comando desde el Omnibar o interactúa a través de tu asistente de WhatsApp para ver tu historial aquí.' }}
                        </p>
                        <Button
                            v-if="conversationSearch"
                            label="Limpiar búsqueda"
                            size="small"
                            variant="outlined"
                            severity="secondary"
                            @click="clearSearch"
                            class="!text-xs mt-2"
                        />
                    </div>

                </div>
                <!-- FIN TAB 2: CONVERSACIONES -->


            </div>
        </div>

        <!-- MODAL PARA AGREGAR CITA MANUALMENTE -->
        <Dialog
            v-model:visible="isNewEventDialogOpen"
            modal
            header="Agendar Nueva Cita"
            :style="{ width: '90vw', maxWidth: '480px' }"
            class="!rounded-xl"
        >
            <form @submit.prevent="saveManualEvent" class="space-y-3.5 py-1 text-xs">
                <div class="space-y-1">
                    <label class="font-medium text-surface-800 dark:text-surface-200 block">
                        Título de la Cita *
                    </label>
                    <InputText
                        v-model="newEventForm.title"
                        required
                        placeholder="ej. Reunión con cliente, Cita dental..."
                        class="w-full text-xs"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="font-medium text-surface-800 dark:text-surface-200 block">
                            Fecha y Hora Inicio *
                        </label>
                        <input
                            v-model="newEventForm.start_time"
                            type="datetime-local"
                            required
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-800 dark:text-surface-200 focus:outline-hidden"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-surface-800 dark:text-surface-200 block">
                            Fecha y Hora Fin
                        </label>
                        <input
                            v-model="newEventForm.end_time"
                            type="datetime-local"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-800 dark:text-surface-200 focus:outline-hidden"
                        />
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-surface-800 dark:text-surface-200 block">
                        Ubicación o Enlace Virtual
                    </label>
                    <InputText
                        v-model="newEventForm.location"
                        placeholder="ej. Sala de juntas, Google Meet..."
                        class="w-full text-xs"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-surface-800 dark:text-surface-200 block">
                        Descripción o Notas
                    </label>
                    <Textarea
                        v-model="newEventForm.description"
                        rows="3"
                        placeholder="Detalles sobre los temas a tratar..."
                        class="w-full text-xs"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-surface-200 dark:border-surface-800">
                    <Button
                        label="Cancelar"
                        severity="secondary"
                        text
                        size="small"
                        type="button"
                        @click="isNewEventDialogOpen = false"
                        class="!text-xs"
                    />
                    <Button
                        label="Agendar Cita"
                        icon="pi pi-check"
                        size="small"
                        type="submit"
                        :loading="isSavingEvent"
                        class="!text-xs"
                    />
                </div>
            </form>
        </Dialog>

        <!-- MODAL DE CONFIGURACIÓN DE GOOGLE CALENDAR -->
        <Dialog
            v-model:visible="isConfigDialogOpen"
            modal
            header="Configuración de Google Calendar"
            :style="{ width: '90vw', maxWidth: '580px' }"
            class="!rounded-xl"
        >
            <div class="space-y-4 py-1 text-sm text-surface-700 dark:text-surface-300">
                <p class="text-xs text-surface-500 dark:text-surface-400 leading-relaxed">
                    Sincroniza tus citas del asistente con tu cuenta de Google Calendar mediante una Cuenta de Servicio (Service Account) de Google Cloud.
                </p>

                <!-- Feedback Message -->
                <Message
                    v-if="testResult"
                    :severity="testResult.success ? 'success' : 'error'"
                    :closable="true"
                    @close="testResult = null"
                    class="text-xs"
                >
                    {{ testResult.message }}
                </Message>

                <!-- Sincronización Automática Checkbox -->
                <div class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-800 bg-surface-50/50 dark:bg-surface-900/50">
                    <Checkbox
                        v-model="calendarSettings.sync_enabled"
                        :binary="true"
                        inputId="cal_sync_enabled"
                    />
                    <label for="cal_sync_enabled" class="cursor-pointer">
                        <span class="font-medium text-surface-900 dark:text-surface-100 text-xs block">
                            Habilitar Sincronización con Google Calendar
                        </span>
                        <span class="text-[11px] text-surface-500 block">
                            Permite la sincronización bidireccional de eventos y citas.
                        </span>
                    </label>
                </div>

                <!-- Auto-sync on create -->
                <div class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-800 bg-surface-50/50 dark:bg-surface-900/50">
                    <Checkbox
                        v-model="calendarSettings.auto_sync_on_create"
                        :binary="true"
                        inputId="cal_auto_sync"
                    />
                    <label for="cal_auto_sync" class="cursor-pointer">
                        <span class="font-medium text-surface-900 dark:text-surface-100 text-xs block">
                            Auto-subir al crear citas con IA
                        </span>
                        <span class="text-[11px] text-surface-500 block">
                            Al agendar una cita mediante el Omnibar, se enviará de inmediato a Google.
                        </span>
                    </label>
                </div>

                <!-- Calendar ID -->
                <div class="space-y-1">
                    <label class="text-xs font-medium text-surface-800 dark:text-surface-200">
                        ID del Calendario (Calendar ID)
                    </label>
                    <InputText
                        v-model="calendarSettings.calendar_id"
                        placeholder="ej. primary o usuario@gmail.com"
                        class="w-full text-xs"
                    />
                    <span class="text-[11px] text-surface-400">
                        Usa <strong class="font-mono">primary</strong> para el calendario principal o la dirección de calendario compartido.
                    </span>
                </div>

                <!-- Timezone -->
                <div class="space-y-1">
                    <label class="text-xs font-medium text-surface-800 dark:text-surface-200">
                        Zona Horaria
                    </label>
                    <InputText
                        v-model="calendarSettings.timezone"
                        placeholder="America/Cancun"
                        class="w-full text-xs"
                    />
                </div>

                <!-- Service Account JSON -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium text-surface-800 dark:text-surface-200">
                            Credenciales Service Account (JSON)
                        </label>
                        <Tag
                            v-if="calendarSettings.has_service_account"
                            value="Credenciales Activas"
                            severity="success"
                            class="text-[10px] px-1.5"
                        />
                        <Tag
                            v-else
                            value="Sin credenciales"
                            severity="warn"
                            class="text-[10px] px-1.5"
                        />
                    </div>
                    <Textarea
                        v-model="calendarSettings.service_account_json"
                        rows="4"
                        placeholder='{"type": "service_account", "project_id": "...", "private_key": "...", "client_email": "..."}'
                        class="w-full font-mono text-[11px]"
                    />
                    <span class="text-[11px] text-surface-400 block">
                        Pega aquí el contenido del archivo JSON generado en Google Cloud Console para tu cuenta de servicio. Recuerda dar acceso al correo de servicio a tu calendario.
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between w-full pt-2">
                    <Button
                        label="Probar Conexión"
                        icon="pi pi-check"
                        severity="secondary"
                        variant="outlined"
                        size="small"
                        :loading="isTesting"
                        @click="testCalendarConnection"
                        class="!text-xs"
                    />

                    <div class="flex items-center gap-2">
                        <Button
                            label="Cancelar"
                            severity="secondary"
                            text
                            size="small"
                            @click="isConfigDialogOpen = false"
                            class="!text-xs"
                        />
                        <Button
                            label="Guardar"
                            icon="pi pi-save"
                            size="small"
                            :loading="isSavingSettings"
                            @click="saveCalendarSettings"
                            class="!text-xs"
                        />
                    </div>
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Scoped micro-refinements for minimalist Linear/Notion layout */
:deep(.p-card-body) {
    padding: 1.125rem !important;
}

:deep(.p-card-caption) {
    gap: 0 !important;
}

:deep(.p-message) {
    margin: 0 !important;
}

:deep(.p-message-content) {
    padding: 0.625rem 0.875rem !important;
    gap: 0.5rem !important;
}
</style>
