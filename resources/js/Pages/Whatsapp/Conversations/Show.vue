<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
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

watch(
    () => props.messages,
    async () => {
        await nextTick();
        if (messagesEl.value) {
            messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
        }
    },
    { immediate: true },
);

const selectedPhone = computed(() => props.filters.phone ?? '');

const title = computed(() =>
    selectedPhone.value
        ? `${props.instance.instance_name} · ${selectedPhone.value}`
        : `${props.instance.instance_name} · Todos los mensajes`,
);

function selectPhone(phone) {
    router.get(
        route('whatsapp.conversations.show', {
            instance: props.instance.instance_name,
        }),
        phone ? { phone } : {},
        { preserveState: true, replace: true },
    );
}

function clearThread(phone) {
    if (
        !confirm(
            `¿Borrar toda la conversación con ${phone}? El bot empezará de cero con ese número.`,
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
            `¿Borrar TODOS los mensajes de ${props.instance.instance_name}? También se reinicia el estado de citas (nombre, mascota, etapa).`,
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
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-base-content">
                        {{ instance.instance_name }}
                    </h2>
                    <p class="text-sm text-base-content/60">Chats de la instancia</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('whatsapp.conversations')">
                        <Button label="Instancias" icon="pi pi-arrow-left" text />
                    </Link>
                    <Link
                        :href="
                            route('whatsapp.calendar.show', {
                                instance: instance.instance_name,
                            })
                        "
                    >
                        <Button label="Calendario" icon="pi pi-calendar" severity="secondary" />
                    </Link>
                    <Button
                        label="Borrar todos"
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        :disabled="clearing"
                        @click="clearAll"
                    />
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="grid min-h-[32rem] overflow-hidden border border-base-300 bg-base-100 shadow-sm sm:rounded-lg lg:grid-cols-[16rem_1fr]"
                >
                    <aside class="border-b border-base-300 lg:border-b-0 lg:border-r">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between px-4 py-3 text-left text-sm transition"
                            :class="
                                !selectedPhone
                                    ? 'bg-primary/10 font-medium text-primary'
                                    : 'hover:bg-base-200'
                            "
                            @click="selectPhone('')"
                        >
                            <span>Todos</span>
                            <span class="text-xs opacity-60">{{ messages.length }}</span>
                        </button>
                        <button
                            v-for="thread in threads"
                            :key="thread.user_phone"
                            type="button"
                            class="flex w-full flex-col gap-0.5 border-t border-base-300 px-4 py-3 text-left text-sm transition"
                            :class="
                                selectedPhone === thread.user_phone
                                    ? 'bg-primary/10 font-medium text-primary'
                                    : 'hover:bg-base-200'
                            "
                            @click="selectPhone(thread.user_phone)"
                        >
                            <span class="font-medium">{{ thread.user_phone }}</span>
                            <span
                                v-if="thread.booking?.bot_paused"
                                class="badge badge-warning badge-sm w-fit"
                            >
                                Bot pausado
                            </span>
                            <span
                                v-else-if="thread.booking?.step_label"
                                class="text-[10px] uppercase tracking-wide text-base-content/40"
                            >
                                {{ thread.booking.step_label }}
                            </span>
                            <span class="line-clamp-1 text-xs text-base-content/50">
                                {{ thread.last_content || '—' }}
                            </span>
                        </button>
                        <div
                            v-if="!threads.length"
                            class="px-4 py-8 text-center text-xs text-base-content/50"
                        >
                            Sin conversaciones aún.
                        </div>
                    </aside>

                    <section class="flex min-h-[24rem] flex-col">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-base-content">
                                    {{
                                        selectedPhone
                                            ? selectedPhone
                                            : 'Todos los mensajes'
                                    }}
                                </p>
                                <p class="text-xs text-base-content/50">
                                    {{ messages.length }} mensaje{{
                                        messages.length !== 1 ? 's' : ''
                                    }}
                                    <template v-if="booking?.step_label">
                                        · {{ booking.step_label }}
                                    </template>
                                </p>
                                <p
                                    v-if="booking?.bot_paused"
                                    class="mt-1 text-xs text-warning"
                                >
                                    Bot pausado
                                    <template v-if="booking.escalation_reason">
                                        · {{ booking.escalation_reason }}
                                    </template>
                                    <template v-if="booking.escalation_detail">
                                        — {{ booking.escalation_detail }}
                                    </template>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <Button
                                    v-if="selectedPhone && booking?.bot_paused"
                                    label="Reactivar bot"
                                    icon="pi pi-play"
                                    size="small"
                                    severity="success"
                                    :loading="resuming"
                                    :disabled="resuming || clearing"
                                    @click="resumeBot(selectedPhone)"
                                />
                                <Button
                                    v-if="selectedPhone"
                                    label="Borrar chat"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    text
                                    :disabled="clearing"
                                    @click="clearThread(selectedPhone)"
                                />
                            </div>
                        </div>

                        <div
                            v-if="selectedPhone && booking"
                            class="flex flex-wrap gap-2 border-b border-base-300 bg-base-200/40 px-4 py-2 text-xs"
                        >
                            <span class="badge badge-ghost badge-sm">
                                {{ booking.name || 'Sin nombre' }}
                            </span>
                            <span class="badge badge-ghost badge-sm">
                                {{ booking.service || 'Sin servicio' }}
                            </span>
                            <span class="badge badge-ghost badge-sm">
                                {{ booking.date || 'Sin fecha' }}
                                <template v-if="booking.time"> · {{ booking.time }}</template>
                            </span>
                            <span
                                v-if="booking.location"
                                class="badge badge-ghost badge-sm"
                            >
                                {{ booking.location }}
                            </span>
                        </div>

                        <div
                            ref="messagesEl"
                            class="flex-1 space-y-3 overflow-y-auto p-4"
                        >
                            <div
                                v-for="msg in messages"
                                :key="msg.id"
                                class="rounded-lg border border-base-300 px-3 py-2"
                                :class="{
                                    'bg-primary/5': msg.role === 'user',
                                    'bg-base-200': msg.role === 'assistant',
                                    'bg-warning/10': msg.role === 'tool',
                                }"
                            >
                                <div
                                    class="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-base-content/50"
                                >
                                    <span>
                                        <span class="uppercase">{{ msg.role }}</span>
                                        <template v-if="msg.tool_name">
                                            · {{ msg.tool_name }}
                                        </template>
                                        <template v-if="!selectedPhone && msg.user_phone">
                                            · {{ msg.user_phone }}
                                        </template>
                                    </span>
                                    <span>{{ msg.created_at }}</span>
                                </div>
                                <p class="whitespace-pre-wrap text-sm text-base-content">
                                    {{ msg.content || '—' }}
                                </p>
                            </div>
                            <div
                                v-if="!messages.length"
                                class="py-16 text-center text-sm text-base-content/50"
                            >
                                No hay mensajes.
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
