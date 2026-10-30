<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const props = defineProps({
    instance: {
        type: Object,
        required: true,
    },
    threads: {
        type: Array,
        default: () => [],
    },
    messages: {
        type: Array,
        default: () => [],
    },
    booking: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({ phone: '' }),
    },
});

const messagesEl = ref(null);
const clearing = ref(false);
const resuming = ref(false);
const searchQuery = ref('');
const filterTab = ref('all'); // 'all', 'paused', 'active'
const showTechnical = ref(false);

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash?.message) return;
        toast.add({
            severity: flash.type === 'error' ? 'error' : 'success',
            summary: flash.type === 'error' ? 'Error' : 'Listo',
            detail: flash.message,
            life: 4500,
        });
    },
    { immediate: true },
);

const selectedPhone = computed(() => props.filters.phone ?? '');

// Filtrar conversaciones en la barra lateral
const filteredThreads = computed(() => {
    let list = props.threads || [];
    if (filterTab.value === 'paused') {
        list = list.filter((t) => t.booking?.bot_paused);
    } else if (filterTab.value === 'active') {
        list = list.filter((t) => !t.booking?.bot_paused);
    }

    if (!searchQuery.value.trim()) {
        return list;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return list.filter((t) => {
        const name = (t.contact_name || '').toLowerCase();
        const phone = (t.user_phone || '').toLowerCase();
        const content = (t.last_content || '').toLowerCase();
        return name.includes(q) || phone.includes(q) || content.includes(q);
    });
});

// Mensajes visibles en el historial (ocultando llamadas internas a herramientas por defecto)
const visibleMessages = computed(() => {
    if (!props.messages) return [];
    if (showTechnical.value) {
        return props.messages;
    }
    return props.messages.filter((m) => {
        if (m.is_tool) return false;
        // Ocultar mensajes de asistente que no tienen contenido ni imagen (ej. llamadas a tools intermedias)
        if (m.role === 'assistant' && !m.content?.trim() && !m.metadata?.image_url) {
            return false;
        }
        return true;
    });
});

function scrollToBottom(smooth = false) {
    if (messagesEl.value) {
        messagesEl.value.scrollTo({
            top: messagesEl.value.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto',
        });
    }
}

// Auto-scroll al final cuando llegan o cambian los mensajes
watch(
    () => visibleMessages.value,
    async () => {
        await nextTick();
        scrollToBottom(true);
    },
    { immediate: true, deep: true },
);

watch(
    () => selectedPhone.value,
    async () => {
        await nextTick();
        setTimeout(() => scrollToBottom(false), 50);
    },
    { immediate: true },
);

onMounted(() => {
    nextTick(() => {
        setTimeout(() => scrollToBottom(false), 100);
    });
});

const currentThread = computed(() => {
    if (!selectedPhone.value) return null;
    return props.threads.find((t) => t.user_phone === selectedPhone.value) || null;
});

const currentContactName = computed(() => {
    if (props.booking?.name) return props.booking.name;
    if (currentThread.value?.contact_name) return currentThread.value.contact_name;
    return null;
});

const title = computed(() => {
    const contact = currentContactName.value || selectedPhone.value;
    return contact
        ? `${props.instance.instance_name} · ${contact}`
        : `${props.instance.instance_name} · Conversaciones`;
});

function formatPhone(phone) {
    if (!phone) return '';
    const clean = phone.replace(/\D/g, '');
    if (clean.length === 12 && clean.startsWith('52')) {
        return `+52 ${clean.slice(2, 5)} ${clean.slice(5, 8)} ${clean.slice(8)}`;
    }
    if (clean.length === 13 && clean.startsWith('521')) {
        return `+52 1 ${clean.slice(3, 6)} ${clean.slice(6, 9)} ${clean.slice(9)}`;
    }
    if (clean.length === 10) {
        return `${clean.slice(0, 3)} ${clean.slice(3, 6)} ${clean.slice(6)}`;
    }
    return phone;
}

function selectPhone(phone) {
    router.get(
        route('whatsapp.conversations.show', {
            instance: props.instance.instance_name,
        }),
        phone ? { phone } : {},
        { preserveState: true, replace: true },
    );
}

function shouldShowDateDivider(index) {
    if (index === 0) return true;
    const current = visibleMessages.value[index];
    const prev = visibleMessages.value[index - 1];
    return current?.date !== prev?.date;
}

function clearThread(phone) {
    if (!phone) return;
    if (
        !confirm(
            `¿Borrar toda la conversación con ${phone}? Se eliminará el historial y el bot empezará desde cero.`,
        )
    ) {
        return;
    }
    clearing.value = true;
    router.delete(
        route('whatsapp.conversations.thread.destroy', {
            instance: props.instance.instance_name,
            phone,
        }),
        {
            preserveScroll: true,
            onFinish: () => {
                clearing.value = false;
            },
        },
    );
}

function clearAll() {
    if (
        !confirm(
            `¿Borrar TODOS los mensajes de ${props.instance.instance_name}? También se reiniciará el estado de citas y clientes de esta instancia.`,
        )
    ) {
        return;
    }
    clearing.value = true;
    router.delete(
        route('whatsapp.conversations.clear', {
            instance: props.instance.instance_name,
        }),
        {
            preserveScroll: true,
            onFinish: () => {
                clearing.value = false;
            },
        },
    );
}

function resumeBot(phone) {
    if (!phone) return;
    resuming.value = true;
    router.post(
        route('whatsapp.conversations.resume', {
            instance: props.instance.instance_name,
            phone,
        }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                resuming.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="title" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('whatsapp.conversations')">
                        <Button
                            icon="pi pi-arrow-left"
                            severity="secondary"
                            rounded
                            text
                            aria-label="Volver"
                        />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold leading-tight text-base-content">
                                {{ instance.instance_name }}
                            </h2>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    instance.status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-base-200 text-base-content/60'
                                "
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        instance.status === 'active'
                                            ? 'bg-emerald-500 animate-pulse'
                                            : 'bg-base-content/40'
                                    "
                                ></span>
                                {{ instance.status === 'active' ? 'Conectado' : instance.status }}
                            </span>
                        </div>
                        <p class="text-xs text-base-content/60">
                            {{ instance.business_name || 'Bandeja de chats de WhatsApp' }} ·
                            {{ threads.length }} conversaci{{ threads.length === 1 ? 'ón' : 'ones' }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="
                            route('whatsapp.calendar.show', {
                                instance: instance.instance_name,
                            })
                        "
                    >
                        <Button
                            label="Calendario"
                            icon="pi pi-calendar"
                            size="small"
                            severity="secondary"
                            outlined
                        />
                    </Link>
                    <Button
                        label="Borrar todos"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        text
                        :disabled="clearing"
                        @click="clearAll"
                    />
                </div>
            </div>
        </template>

        <div class="py-4 sm:py-6">
            <div class="mx-auto max-w-7xl px-2 sm:px-6 lg:px-8">
                <!-- Marco principal de WhatsApp -->
                <div
                    class="grid h-[calc(100vh-12.5rem)] min-h-[500px] max-h-[860px] overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-md lg:grid-cols-[330px_1fr] xl:grid-cols-[360px_1fr]"
                >
                    <!-- SIDEBAR / BANDEJA DE CHATS -->
                    <aside
                        class="flex flex-col h-full min-h-0 overflow-hidden border-b border-base-300 bg-base-100 lg:border-b-0 lg:border-r"
                        :class="selectedPhone ? 'hidden lg:flex' : 'flex'"
                    >
                        <!-- Buscador y Filtros de Chats -->
                        <div class="shrink-0 border-b border-base-300 p-3 space-y-2.5 bg-base-100/90 backdrop-blur z-10">
                            <div class="relative flex items-center">
                                <i
                                    class="pi pi-search absolute left-3 text-base-content/40 text-sm"
                                ></i>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar por nombre o teléfono..."
                                    class="input input-sm w-full pl-9 pr-8 text-xs rounded-lg border-base-300 bg-base-200/60 focus:bg-base-100 transition"
                                />
                                <button
                                    v-if="searchQuery"
                                    class="absolute right-2.5 text-base-content/40 hover:text-base-content text-xs"
                                    @click="searchQuery = ''"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Filtros rápidos -->
                            <div class="flex items-center gap-1.5 text-xs">
                                <button
                                    type="button"
                                    class="px-2.5 py-1 rounded-full font-medium transition"
                                    :class="
                                        filterTab === 'all'
                                            ? 'bg-primary text-primary-content shadow-xs'
                                            : 'bg-base-200 text-base-content/70 hover:bg-base-300/80'
                                    "
                                    @click="filterTab = 'all'"
                                >
                                    Todos ({{ threads.length }})
                                </button>
                                <button
                                    type="button"
                                    class="px-2.5 py-1 rounded-full font-medium transition flex items-center gap-1"
                                    :class="
                                        filterTab === 'paused'
                                            ? 'bg-warning text-warning-content shadow-xs'
                                            : 'bg-base-200 text-base-content/70 hover:bg-base-300/80'
                                    "
                                    @click="filterTab = 'paused'"
                                >
                                    <span>⏸️ Pausados</span>
                                    <span
                                        class="badge badge-xs"
                                        :class="filterTab === 'paused' ? 'badge-neutral' : 'badge-ghost'"
                                    >
                                        {{ threads.filter((t) => t.booking?.bot_paused).length }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="px-2.5 py-1 rounded-full font-medium transition"
                                    :class="
                                        filterTab === 'active'
                                            ? 'bg-emerald-600 text-white shadow-xs'
                                            : 'bg-base-200 text-base-content/70 hover:bg-base-300/80'
                                    "
                                    @click="filterTab = 'active'"
                                >
                                    🤖 Activos
                                </button>
                            </div>
                        </div>

                        <!-- Lista de Conversaciones con scroll garantizado -->
                        <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-base-200/60 custom-scrollbar overscroll-contain">
                            <button
                                v-for="thread in filteredThreads"
                                :key="thread.user_phone"
                                type="button"
                                class="w-full flex items-center gap-3 px-3.5 py-3 text-left transition relative select-none"
                                :class="
                                    selectedPhone === thread.user_phone
                                        ? 'bg-primary/10 border-l-4 border-primary text-base-content'
                                        : 'hover:bg-base-200/60 text-base-content'
                                "
                                @click="selectPhone(thread.user_phone)"
                            >
                                <!-- Avatar con indicador de estado -->
                                <div class="relative shrink-0">
                                    <div
                                        class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-sm shadow-xs"
                                        :class="
                                            selectedPhone === thread.user_phone
                                                ? 'bg-primary text-primary-content'
                                                : 'bg-base-300 text-base-content/80'
                                        "
                                    >
                                        {{
                                            (thread.contact_name || thread.user_phone)
                                                .slice(0, 2)
                                                .toUpperCase()
                                        }}
                                    </div>
                                    <span
                                        class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-base-100"
                                        :class="
                                            thread.booking?.bot_paused
                                                ? 'bg-amber-500'
                                                : 'bg-emerald-500'
                                        "
                                        :title="
                                            thread.booking?.bot_paused
                                                ? 'Bot pausado'
                                                : 'Bot activo'
                                        "
                                    ></span>
                                </div>

                                <!-- Información del Chat -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline justify-between gap-1 mb-0.5">
                                        <p class="truncate text-sm font-semibold text-base-content">
                                            {{ thread.contact_name || formatPhone(thread.user_phone) }}
                                        </p>
                                        <span class="shrink-0 text-[11px] font-medium text-base-content/50">
                                            {{ thread.last_time || thread.last_date }}
                                        </span>
                                    </div>

                                    <!-- Teléfono si el nombre existe -->
                                    <p
                                        v-if="thread.contact_name"
                                        class="text-[11px] text-base-content/50 truncate -mt-0.5 mb-0.5 font-mono"
                                    >
                                        {{ formatPhone(thread.user_phone) }}
                                    </p>

                                    <!-- Último mensaje y badges -->
                                    <div class="flex items-center justify-between gap-1 text-xs">
                                        <p class="truncate text-base-content/60 flex items-center gap-1">
                                            <span
                                                v-if="thread.is_last_outgoing"
                                                class="text-emerald-600 dark:text-emerald-400 shrink-0 font-medium text-[11px]"
                                            >
                                                Tú:
                                            </span>
                                            <span>{{ thread.last_content || '—' }}</span>
                                        </p>

                                        <!-- Badge de bot pausado / etapa -->
                                        <span
                                            v-if="thread.booking?.bot_paused"
                                            class="shrink-0 badge badge-warning badge-xs py-1.5 px-2 font-medium"
                                        >
                                            Pausado
                                        </span>
                                        <span
                                            v-else-if="thread.booking?.step_label"
                                            class="shrink-0 text-[10px] uppercase font-semibold text-base-content/40 bg-base-200 rounded px-1.5 py-0.5"
                                        >
                                            {{ thread.booking.step_label }}
                                        </span>
                                    </div>
                                </div>
                            </button>

                            <!-- Estado vacío de búsqueda / sin chats -->
                            <div
                                v-if="!filteredThreads.length"
                                class="px-6 py-14 text-center text-xs text-base-content/50 space-y-2"
                            >
                                <div class="text-3xl opacity-40">💬</div>
                                <p class="font-medium text-sm text-base-content/70">
                                    {{
                                        searchQuery
                                            ? 'Sin resultados para la búsqueda'
                                            : 'Sin conversaciones registradas'
                                    }}
                                </p>
                                <p v-if="searchQuery" class="text-xs">
                                    Intenta con otro término o limpia el buscador.
                                </p>
                            </div>
                        </div>
                    </aside>

                    <!-- HISTORIAL Y CHAT EN VIVO -->
                    <section
                        class="flex flex-col h-full min-h-0 overflow-hidden bg-base-200/40 relative"
                        :class="!selectedPhone ? 'hidden lg:flex' : 'flex'"
                    >
                        <!-- HEADER DEL CHAT ACTIVO -->
                        <div
                            v-if="selectedPhone"
                            class="shrink-0 flex flex-wrap items-center justify-between gap-3 border-b border-base-300 bg-base-100 px-4 py-3 shadow-xs z-10"
                        >
                            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                                <!-- Botón volver en mobile -->
                                <button
                                    type="button"
                                    class="lg:hidden btn btn-ghost btn-circle btn-sm -ml-1 text-base-content"
                                    title="Volver a lista de chats"
                                    @click="selectPhone('')"
                                >
                                    <i class="pi pi-arrow-left text-sm"></i>
                                </button>

                                <div
                                    class="w-10 h-10 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center shrink-0 text-sm border border-primary/20"
                                >
                                    {{
                                        (currentContactName || selectedPhone)
                                            .slice(0, 2)
                                            .toUpperCase()
                                    }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="truncate text-sm font-bold text-base-content">
                                            {{ currentContactName || formatPhone(selectedPhone) }}
                                        </h3>
                                        <!-- Badge de Bot Activo / Pausado -->
                                        <span
                                            v-if="booking?.bot_paused"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Bot pausado
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Bot activo
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 text-xs text-base-content/60">
                                        <span class="font-mono">{{ formatPhone(selectedPhone) }}</span>
                                        <span>·</span>
                                        <span>{{ visibleMessages.length }} mensajes</span>
                                        <template v-if="booking?.step_label">
                                            <span>·</span>
                                            <span class="font-medium text-primary">{{ booking.step_label }}</span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones rápidas del Chat -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <!-- Botón Reactivar Bot -->
                                <Button
                                    v-if="booking?.bot_paused"
                                    label="Reactivar bot"
                                    icon="pi pi-play"
                                    size="small"
                                    severity="success"
                                    :loading="resuming"
                                    :disabled="resuming || clearing"
                                    @click="resumeBot(selectedPhone)"
                                />

                                <!-- Botón WhatsApp Web Oficial -->
                                <a
                                    :href="`https://wa.me/${selectedPhone.replace(/\D/g, '')}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Abrir chat en WhatsApp Web"
                                >
                                    <Button
                                        icon="pi pi-whatsapp"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        label="Abrir WhatsApp"
                                    />
                                </a>

                                <!-- Toggle Modo Técnico -->
                                <Button
                                    :icon="showTechnical ? 'pi pi-eye-slash' : 'pi pi-code'"
                                    :label="showTechnical ? 'Ocultar tools' : 'Tools'"
                                    size="small"
                                    text
                                    severity="secondary"
                                    :title="showTechnical ? 'Ocultar llamadas a herramientas del LLM' : 'Mostrar llamadas a herramientas técnicas'"
                                    @click="showTechnical = !showTechnical"
                                />

                                <!-- Borrar conversación -->
                                <Button
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    text
                                    title="Borrar chat completo"
                                    :disabled="clearing"
                                    @click="clearThread(selectedPhone)"
                                />
                            </div>
                        </div>

                        <!-- Barra de Resumen de Cita / Lead (si existe) -->
                        <div
                            v-if="selectedPhone && booking && (booking.name || booking.service || booking.date || booking.notes)"
                            class="shrink-0 flex flex-wrap items-center gap-2 border-b border-base-300 bg-base-100/90 px-4 py-2 text-xs backdrop-blur z-10"
                        >
                            <span class="text-base-content/50 font-medium">Datos detectados:</span>
                            <span v-if="booking.name" class="badge badge-outline badge-sm gap-1">
                                👤 {{ booking.name }}
                            </span>
                            <span v-if="booking.service" class="badge badge-outline badge-sm gap-1">
                                🏷️ {{ booking.service }}
                            </span>
                            <span v-if="booking.date" class="badge badge-outline badge-sm gap-1">
                                📅 {{ booking.date }} {{ booking.time ? `· ${booking.time}` : '' }}
                            </span>
                            <span v-if="booking.location" class="badge badge-outline badge-sm gap-1">
                                📍 {{ booking.location }}
                            </span>
                            <span
                                v-if="booking.notes"
                                class="badge badge-outline badge-sm text-base-content/70 max-w-xs truncate"
                                :title="booking.notes"
                            >
                                📝 {{ booking.notes }}
                            </span>
                        </div>

                        <!-- Notificación de Alerta si el bot está pausado -->
                        <div
                            v-if="selectedPhone && booking?.bot_paused"
                            class="shrink-0 bg-amber-500/10 border-b border-amber-500/20 px-4 py-2 text-xs text-amber-700 dark:text-amber-300 flex items-center justify-between z-10"
                        >
                            <div class="flex items-center gap-2">
                                <i class="pi pi-exclamation-triangle text-amber-500"></i>
                                <span>
                                    <strong>Bot en pausa:</strong>
                                    {{ booking.escalation_reason || 'Intervención humana activa' }}
                                    <template v-if="booking.escalation_detail">
                                        — {{ booking.escalation_detail }}
                                    </template>
                                </span>
                            </div>
                            <button
                                class="btn btn-xs btn-warning"
                                :disabled="resuming"
                                @click="resumeBot(selectedPhone)"
                            >
                                Reactivar respuesta automática
                            </button>
                        </div>

                        <!-- ÁREA DE BURBUJAS DE MENSAJES (Scrollable garantizado) -->
                        <div
                            ref="messagesEl"
                            class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 space-y-3.5 bg-gradient-to-b from-base-200/30 to-base-200/70 custom-scrollbar overscroll-contain"
                        >
                            <!-- Estado sin conversación seleccionada -->
                            <div
                                v-if="!selectedPhone"
                                class="h-full flex flex-col items-center justify-center text-center p-8 space-y-3"
                            >
                                <div
                                    class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center text-2xl"
                                >
                                    <i class="pi pi-comments text-3xl"></i>
                                </div>
                                <h4 class="text-base font-semibold text-base-content">
                                    Selecciona una conversación
                                </h4>
                                <p class="text-xs text-base-content/60 max-w-sm">
                                    Haz clic en cualquiera de las conversaciones de la barra lateral para ver su historial cronológico de mensajes.
                                </p>
                            </div>

                            <!-- Estado sin mensajes en el chat seleccionado -->
                            <div
                                v-else-if="!visibleMessages.length"
                                class="h-full flex flex-col items-center justify-center text-center p-8 text-base-content/50 space-y-2"
                            >
                                <div class="text-3xl opacity-40">📭</div>
                                <p class="text-sm font-medium">No hay mensajes en esta conversación.</p>
                            </div>

                            <!-- BURBUJAS DE MENSAJE -->
                            <template v-else>
                                <template
                                    v-for="(msg, index) in visibleMessages"
                                    :key="msg.id || index"
                                >
                                    <!-- Separador de Fecha Centrado -->
                                    <div
                                        v-if="shouldShowDateDivider(index)"
                                        class="flex justify-center my-3"
                                    >
                                        <span
                                            class="rounded-full bg-base-100/90 dark:bg-base-300 px-3 py-1 text-[11px] font-semibold text-base-content/70 shadow-xs border border-base-300"
                                        >
                                            {{ msg.date }}
                                        </span>
                                    </div>

                                    <!-- MENSAJE CLIENTE (ENTRANTE / INBOUND) -> ALINEADO A LA IZQUIERDA -->
                                    <div
                                        v-if="msg.is_incoming"
                                        class="flex justify-start items-end gap-2 my-1"
                                    >
                                        <div
                                            class="max-w-[85%] sm:max-w-[70%] rounded-2xl rounded-bl-xs px-4 py-2.5 bg-white dark:bg-base-100 text-base-content shadow-sm border border-base-300"
                                        >
                                            <!-- Encabezado de remitente -->
                                            <div
                                                class="flex items-center justify-between gap-3 text-[11px] font-medium text-primary mb-1"
                                            >
                                                <span>{{ currentContactName || 'Cliente' }}</span>
                                                <span class="text-[10px] text-base-content/40 font-normal">
                                                    {{ msg.time }}
                                                </span>
                                            </div>

                                            <!-- Contenido de texto -->
                                            <p
                                                class="whitespace-pre-wrap text-[13.5px] leading-relaxed text-base-content"
                                            >
                                                {{ msg.content || '—' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- MENSAJE BOT / ASISTENTE (SALIENTE / OUTBOUND) -> ALINEADO A LA DERECHA -->
                                    <div
                                        v-else-if="msg.is_outgoing"
                                        class="flex justify-end items-end gap-2 my-1"
                                    >
                                        <div
                                            class="max-w-[85%] sm:max-w-[72%] rounded-2xl rounded-br-xs px-4 py-2.5 bg-emerald-600 text-white shadow-sm"
                                        >
                                            <!-- Encabezado de remitente -->
                                            <div
                                                class="flex items-center justify-between gap-3 text-[11px] font-medium text-emerald-100 mb-1"
                                            >
                                                <span class="flex items-center gap-1 font-semibold">
                                                    <span>🤖</span>
                                                    <span>Bot Asistente</span>
                                                </span>
                                                <span class="text-[10px] text-emerald-100/70 font-normal">
                                                    {{ msg.time }}
                                                </span>
                                            </div>

                                            <!-- Tarjeta de Imagen Multimedia (si se envió por catálogo) -->
                                            <div
                                                v-if="msg.metadata?.image_url"
                                                class="my-2 overflow-hidden rounded-xl border border-black/15 bg-black/10 shadow-inner"
                                            >
                                                <a
                                                    :href="msg.metadata.image_url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="block relative group"
                                                >
                                                    <img
                                                        :src="msg.metadata.image_url"
                                                        :alt="msg.metadata.item_name || 'Imagen del producto'"
                                                        class="w-full max-h-64 object-cover transition duration-300 group-hover:scale-102"
                                                        loading="lazy"
                                                    />
                                                    <div
                                                        class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-semibold"
                                                    >
                                                        <i class="pi pi-external-link mr-1"></i> Ver imagen
                                                    </div>
                                                </a>
                                                <div
                                                    v-if="msg.metadata.item_name"
                                                    class="p-2 text-xs font-medium text-emerald-50 bg-emerald-800/40 flex items-center gap-1.5"
                                                >
                                                    <span>📷</span>
                                                    <span>{{ msg.metadata.item_name }}</span>
                                                </div>
                                            </div>

                                            <!-- Contenido de texto -->
                                            <p
                                                v-if="msg.content"
                                                class="whitespace-pre-wrap text-[13.5px] leading-relaxed text-white"
                                            >
                                                {{ msg.content }}
                                            </p>

                                            <!-- Pie con doble checkmark -->
                                            <div
                                                class="mt-1 flex items-center justify-end gap-1 text-[10px] text-emerald-100/70"
                                            >
                                                <span>{{ msg.time }}</span>
                                                <i class="pi pi-check text-[10px] font-bold"></i>
                                                <i class="pi pi-check text-[10px] -ml-1.5 font-bold"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- REGISTROS TÉCNICOS / HERRAMIENTAS INTERNAS (MODO TÉCNICO) -->
                                    <div
                                        v-else-if="msg.is_tool && showTechnical"
                                        class="mx-auto max-w-lg my-2 rounded-lg border border-warning/30 bg-warning/10 p-3 text-xs text-base-content"
                                    >
                                        <div
                                            class="flex items-center justify-between text-[11px] font-mono font-semibold text-warning-content mb-1"
                                        >
                                            <span>🔧 Tool: {{ msg.tool_name }}</span>
                                            <span>{{ msg.time }}</span>
                                        </div>
                                        <pre
                                            class="overflow-x-auto whitespace-pre-wrap text-[11px] font-mono opacity-80"
                                        >{{ msg.content }}</pre>
                                    </div>
                                </template>
                            </template>
                        </div>

                        <!-- FOOTER DEL CHAT (Supervisión en tiempo real) -->
                        <div
                            v-if="selectedPhone"
                            class="shrink-0 border-t border-base-300 bg-base-100 px-4 py-2.5 text-xs text-base-content/60 flex items-center justify-between z-10"
                        >
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                <span>
                                    Supervisión en tiempo real de WhatsApp. Respuestas sincronizadas con Evolution API.
                                </span>
                            </div>
                            <span class="text-[11px] font-mono opacity-60">
                                Instancia: {{ instance.evolution_instance_name }}
                            </span>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Scrollbar delgado y elegante estilo WhatsApp */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.45) transparent;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.35);
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.7);
}
</style>
