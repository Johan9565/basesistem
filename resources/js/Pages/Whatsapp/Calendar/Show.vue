<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const props = defineProps({
    instance: {
        type: Object,
        required: true,
    },
    appointments: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ from: '', to: '' }),
    },
});

const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const syncing = ref(false);

watch(
    () => props.filters,
    (f) => {
        from.value = f.from ?? '';
        to.value = f.to ?? '';
    },
    { deep: true },
);

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash?.message) return;
        toast.add({
            severity: flash.type === 'error' ? 'error' : 'success',
            summary: flash.type === 'error' ? 'Error' : 'Listo',
            detail: flash.message,
            life: 5000,
        });
    },
    { immediate: true },
);

function applyFilters() {
    router.get(
        route('whatsapp.calendar.show', {
            instance: props.instance.instance_name,
        }),
        {
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function syncFromGoogle() {
    syncing.value = true;
    router.post(
        route('whatsapp.calendar.sync', {
            instance: props.instance.instance_name,
        }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

function syncLabel(status) {
    if (status === 'synced') return 'Google OK';
    if (status === 'google_failed') return 'Google falló';
    return 'Solo sistema';
}

function syncClass(status) {
    if (status === 'synced') return 'bg-success/15 text-success';
    if (status === 'google_failed') return 'bg-error/15 text-error';
    return 'bg-base-300 text-base-content/70';
}

function sourceLabel(source) {
    if (source === 'google') return 'Google';
    if (source === 'bot') return 'Bot';
    return source || '—';
}
</script>

<template>
    <Head :title="`Calendario · ${instance.instance_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-base-content">
                        Calendario · {{ instance.instance_name }}
                    </h2>
                    <p class="text-sm text-base-content/60">
                        Citas en el sistema
                        <template v-if="instance.google_calendar_id">
                            · Google:
                            {{ instance.google_calendar_id }}
                        </template>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('whatsapp.calendar')">
                        <Button label="Instancias" icon="pi pi-arrow-left" text />
                    </Link>
                    <Link
                        :href="
                            route('whatsapp.conversations.show', {
                                instance: instance.instance_name,
                            })
                        "
                    >
                        <Button label="Chats" icon="pi pi-comments" severity="secondary" />
                    </Link>
                    <Button
                        label="Sincronizar Google"
                        icon="pi pi-refresh"
                        :loading="syncing"
                        :disabled="!instance.google_calendar_id || syncing"
                        @click="syncFromGoogle"
                    />
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-base-content/60">
                    Usa <strong>Sincronizar Google</strong> para traer citas que
                    agregaste a mano en Calendar (últimos 7 días → próximos 60).
                    El bot también revisa ocupación local y de Google antes de
                    agendar.
                </p>

                <div
                    class="mb-4 flex flex-col gap-3 border border-base-300 bg-base-100 p-4 sm:rounded-lg sm:flex-row sm:items-end"
                >
                    <div class="flex-1">
                        <label class="text-xs text-base-content/60">Desde</label>
                        <InputText v-model="from" type="date" class="mt-1 block w-full" />
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-base-content/60">Hasta</label>
                        <InputText v-model="to" type="date" class="mt-1 block w-full" />
                    </div>
                    <Button label="Filtrar" icon="pi pi-filter" @click="applyFilters" />
                </div>

                <div class="overflow-hidden border border-base-300 bg-base-100 shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-base-300 text-sm">
                            <thead>
                                <tr class="text-left text-base-content/60">
                                    <th class="px-4 py-3 font-medium">Inicio</th>
                                    <th class="px-4 py-3 font-medium">Fin</th>
                                    <th class="px-4 py-3 font-medium">Asunto</th>
                                    <th class="px-4 py-3 font-medium">Teléfono</th>
                                    <th class="px-4 py-3 font-medium">Origen</th>
                                    <th class="px-4 py-3 font-medium">Sync</th>
                                    <th class="px-4 py-3 font-medium">Google</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-300">
                                <tr v-for="apt in appointments" :key="apt.id">
                                    <td class="whitespace-nowrap px-4 py-3">
                                        {{ apt.starts_at || '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        {{ apt.ends_at || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ apt.summary }}</div>
                                        <div
                                            v-if="apt.description"
                                            class="text-xs text-base-content/50"
                                        >
                                            {{ apt.description }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">{{ apt.user_phone }}</td>
                                    <td class="px-4 py-3">
                                        {{ sourceLabel(apt.source) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                            :class="syncClass(apt.sync_status)"
                                            :title="apt.sync_error || ''"
                                        >
                                            {{ syncLabel(apt.sync_status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            v-if="apt.google_html_link"
                                            :href="apt.google_html_link"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-primary underline"
                                        >
                                            Abrir
                                        </a>
                                        <span v-else class="text-base-content/40">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-if="!appointments.length"
                        class="py-16 text-center text-sm text-base-content/60"
                    >
                        No hay citas. Sincroniza desde Google o agenda por WhatsApp.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
