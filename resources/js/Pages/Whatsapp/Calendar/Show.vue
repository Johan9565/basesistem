<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
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

const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const MONTHS = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

const syncing = ref(false);
const deleting = ref(false);
const selectedApt = ref(null);
const detailOpen = ref(false);
const deleteOpen = ref(false);
const pendingDelete = ref(null);
const deleteFromGoogle = ref(true);

/** Parse ISO datetimes or YYYY-MM-DD without UTC day-shift. */
function parseDate(value) {
    if (!value) return null;
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [y, m, d] = value.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
}

function startOfDay(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function toDateKey(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function monthRange(year, month) {
    const from = new Date(year, month, 1);
    const to = new Date(year, month + 1, 0);
    return {
        from: toDateKey(from),
        to: toDateKey(to),
    };
}

function monthFromFilters() {
    const from = parseDate(props.filters.from);
    if (from) return new Date(from.getFullYear(), from.getMonth(), 1);
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), 1);
}

const cursor = ref(monthFromFilters());
const selectedDay = ref(startOfDay(new Date()));

const monthLabel = computed(
    () => `${MONTHS[cursor.value.getMonth()]} ${cursor.value.getFullYear()}`,
);

const todayKey = computed(() => toDateKey(new Date()));

const appointmentsByDay = computed(() => {
    const map = new Map();
    for (const apt of props.appointments) {
        const start = parseDate(apt.starts_at);
        if (!start) continue;
        const key = toDateKey(start);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(apt);
    }
    for (const list of map.values()) {
        list.sort((a, b) => {
            const da = parseDate(a.starts_at)?.getTime() ?? 0;
            const db = parseDate(b.starts_at)?.getTime() ?? 0;
            return da - db;
        });
    }
    return map;
});

const calendarCells = computed(() => {
    const year = cursor.value.getFullYear();
    const month = cursor.value.getMonth();
    const first = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const startOffset = (first.getDay() + 6) % 7;
    const cells = [];
    const selectedKey = toDateKey(selectedDay.value);

    for (let i = 0; i < startOffset; i++) {
        cells.push({ key: `pad-start-${i}`, type: 'pad' });
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const key = toDateKey(date);
        const events = appointmentsByDay.value.get(key) ?? [];
        cells.push({
            key,
            type: 'day',
            day,
            date,
            events,
            isToday: key === todayKey.value,
            isSelected: key === selectedKey,
        });
    }

    while (cells.length % 7 !== 0) {
        cells.push({ key: `pad-end-${cells.length}`, type: 'pad' });
    }

    return cells;
});

const selectedDayKey = computed(() => toDateKey(selectedDay.value));

const selectedDayEvents = computed(
    () => appointmentsByDay.value.get(selectedDayKey.value) ?? [],
);

const selectedDayLabel = computed(() =>
    selectedDay.value.toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const monthStats = computed(() => {
    const year = cursor.value.getFullYear();
    const month = cursor.value.getMonth();
    let total = 0;
    let failed = 0;
    let today = 0;
    for (const apt of props.appointments) {
        const start = parseDate(apt.starts_at);
        if (!start) continue;
        if (start.getFullYear() !== year || start.getMonth() !== month) continue;
        total += 1;
        if (apt.sync_status === 'google_failed') failed += 1;
        if (toDateKey(start) === todayKey.value) today += 1;
    }
    return { total, failed, today };
});

watch(
    () => props.filters.from,
    (fromStr) => {
        const from = parseDate(fromStr);
        if (!from) return;
        const next = new Date(from.getFullYear(), from.getMonth(), 1);
        if (
            next.getFullYear() !== cursor.value.getFullYear() ||
            next.getMonth() !== cursor.value.getMonth()
        ) {
            cursor.value = next;
        }
    },
);

watch(
    () => [cursor.value.getFullYear(), cursor.value.getMonth()],
    ([year, month], previous) => {
        const selected = selectedDay.value;
        if (selected.getFullYear() === year && selected.getMonth() === month) {
            return;
        }

        // Only re-pick a day when the visible month actually changed.
        if (
            previous &&
            previous[0] === year &&
            previous[1] === month
        ) {
            return;
        }

        const today = new Date();
        if (today.getFullYear() === year && today.getMonth() === month) {
            selectedDay.value = startOfDay(today);
            return;
        }

        selectedDay.value = new Date(year, month, 1);
    },
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

function loadMonth(year, month) {
    const range = monthRange(year, month);
    if (props.filters.from === range.from && props.filters.to === range.to) {
        return;
    }
    router.get(
        route('whatsapp.calendar.show', {
            instance: props.instance.instance_name,
        }),
        { from: range.from, to: range.to },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function goToday() {
    const now = new Date();
    cursor.value = new Date(now.getFullYear(), now.getMonth(), 1);
    selectedDay.value = startOfDay(now);
    loadMonth(now.getFullYear(), now.getMonth());
}

function shiftMonth(delta) {
    const next = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth() + delta,
        1,
    );
    cursor.value = next;
    selectedDay.value = new Date(next.getFullYear(), next.getMonth(), 1);
    loadMonth(next.getFullYear(), next.getMonth());
}

function selectDay(cell) {
    if (cell.type !== 'day') return;
    selectedDay.value = startOfDay(cell.date);
}

function openDetail(apt) {
    selectedApt.value = apt;
    detailOpen.value = true;
}

function askDelete(apt, event) {
    event?.stopPropagation?.();
    pendingDelete.value = apt;
    deleteFromGoogle.value = Boolean(apt.has_google_event);
    deleteOpen.value = true;
}

function confirmDelete() {
    const apt = pendingDelete.value;
    if (!apt || deleting.value) return;

    deleting.value = true;
    router.delete(
        route('whatsapp.calendar.destroy', {
            instance: props.instance.instance_name,
            appointment: apt.id,
            from: props.filters.from || undefined,
            to: props.filters.to || undefined,
            delete_google: deleteFromGoogle.value ? 1 : 0,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                detailOpen.value = false;
                deleteOpen.value = false;
                pendingDelete.value = null;
                if (selectedApt.value?.id === apt.id) {
                    selectedApt.value = null;
                }
            },
            onFinish: () => {
                deleting.value = false;
            },
        },
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

function formatTime(value) {
    const d = parseDate(value);
    if (!d) return '—';
    return d.toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatRange(apt) {
    const start = formatTime(apt.starts_at);
    const end = formatTime(apt.ends_at);
    if (start === '—' && end === '—') return 'Sin horario';
    if (end === '—') return start;
    return `${start} – ${end}`;
}

function syncLabel(status) {
    if (status === 'synced') return 'En Google';
    if (status === 'google_failed') return 'Error sync';
    return 'Solo local';
}

function syncTone(status) {
    if (status === 'synced') return 'badge-success';
    if (status === 'google_failed') return 'badge-error';
    return 'badge-ghost';
}

function sourceLabel(source) {
    if (source === 'google') return 'Google';
    if (source === 'bot') return 'Bot WhatsApp';
    return source || 'Desconocido';
}

function sourceTone(source) {
    if (source === 'google') return 'badge-info';
    if (source === 'bot') return 'badge-primary';
    return 'badge-ghost';
}

function eventDotClass(apt) {
    if (apt.sync_status === 'google_failed') return 'bg-error';
    if (apt.source === 'google') return 'bg-info';
    return 'bg-primary';
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
                        Vista mensual de citas
                        <template v-if="instance.google_calendar_id">
                            · Google Calendar conectado
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
                        <Button
                            label="Chats"
                            icon="pi pi-comments"
                            severity="secondary"
                        />
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

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    class="flex flex-col gap-4 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            icon="pi pi-chevron-left"
                            rounded
                            text
                            aria-label="Mes anterior"
                            @click="shiftMonth(-1)"
                        />
                        <h3 class="min-w-[10rem] text-center text-lg font-semibold capitalize">
                            {{ monthLabel }}
                        </h3>
                        <Button
                            icon="pi pi-chevron-right"
                            rounded
                            text
                            aria-label="Mes siguiente"
                            @click="shiftMonth(1)"
                        />
                        <Button
                            label="Hoy"
                            icon="pi pi-calendar"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="goToday"
                        />
                    </div>

                    <div class="flex flex-wrap gap-2 text-sm">
                        <span class="badge badge-outline gap-1 px-3 py-3">
                            <i class="pi pi-calendar text-xs" />
                            {{ monthStats.total }} cita{{ monthStats.total === 1 ? '' : 's' }}
                        </span>
                        <span
                            v-if="monthStats.today"
                            class="badge badge-primary gap-1 px-3 py-3"
                        >
                            {{ monthStats.today }} hoy
                        </span>
                        <span
                            v-if="monthStats.failed"
                            class="badge badge-error gap-1 px-3 py-3"
                        >
                            {{ monthStats.failed }} con error
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div
                        class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm"
                    >
                        <div
                            class="grid grid-cols-7 border-b border-base-300 bg-base-200/60 text-center text-xs font-medium uppercase tracking-wide text-base-content/55"
                        >
                            <div
                                v-for="wd in WEEKDAYS"
                                :key="wd"
                                class="px-1 py-3"
                            >
                                {{ wd }}
                            </div>
                        </div>

                        <div class="grid grid-cols-7">
                            <button
                                v-for="cell in calendarCells"
                                :key="cell.key"
                                type="button"
                                class="relative min-h-[5.5rem] border-b border-r border-base-300 p-2 text-left transition-colors sm:min-h-[6.5rem]"
                                :class="[
                                    cell.type === 'pad'
                                        ? 'cursor-default bg-base-200/30'
                                        : 'hover:bg-base-200/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary',
                                    cell.isSelected ? 'bg-primary/10' : '',
                                    cell.isToday && !cell.isSelected
                                        ? 'bg-base-200/40'
                                        : '',
                                ]"
                                :disabled="cell.type === 'pad'"
                                @click="selectDay(cell)"
                            >
                                <template v-if="cell.type === 'day'">
                                    <span
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-medium"
                                        :class="[
                                            cell.isToday
                                                ? 'bg-primary text-primary-content'
                                                : 'text-base-content/80',
                                            cell.isSelected && !cell.isToday
                                                ? 'ring-2 ring-primary/40'
                                                : '',
                                        ]"
                                    >
                                        {{ cell.day }}
                                    </span>

                                    <div class="mt-1 hidden space-y-1 sm:block">
                                        <div
                                            v-for="apt in cell.events.slice(0, 2)"
                                            :key="apt.id"
                                            class="truncate rounded-md px-1.5 py-0.5 text-[11px] leading-tight"
                                            :class="
                                                apt.sync_status === 'google_failed'
                                                    ? 'bg-error/15 text-error'
                                                    : apt.source === 'google'
                                                      ? 'bg-info/15 text-info'
                                                      : 'bg-primary/15 text-primary'
                                            "
                                            :title="apt.summary"
                                        >
                                            <span class="font-semibold">
                                                {{ formatTime(apt.starts_at) }}
                                            </span>
                                            {{ apt.summary || 'Sin asunto' }}
                                        </div>
                                        <div
                                            v-if="cell.events.length > 2"
                                            class="px-1 text-[11px] text-base-content/50"
                                        >
                                            +{{ cell.events.length - 2 }} más
                                        </div>
                                    </div>

                                    <div
                                        v-if="cell.events.length"
                                        class="absolute bottom-1.5 left-2 flex gap-0.5 sm:hidden"
                                    >
                                        <span
                                            v-for="apt in cell.events.slice(0, 3)"
                                            :key="`dot-${apt.id}`"
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="eventDotClass(apt)"
                                        />
                                        <span
                                            v-if="cell.events.length > 3"
                                            class="text-[9px] leading-none text-base-content/50"
                                        >
                                            +{{ cell.events.length - 3 }}
                                        </span>
                                    </div>
                                </template>
                            </button>
                        </div>
                    </div>

                    <aside
                        class="flex min-h-[24rem] flex-col rounded-2xl border border-base-300 bg-base-100 shadow-sm lg:min-h-0"
                    >
                        <div class="border-b border-base-300 px-4 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-base-content/50">
                                Día seleccionado
                            </p>
                            <h4 class="mt-1 text-base font-semibold capitalize leading-snug">
                                {{ selectedDayLabel }}
                            </h4>
                            <p class="mt-1 text-sm text-base-content/55">
                                {{ selectedDayEvents.length }}
                                cita{{ selectedDayEvents.length === 1 ? '' : 's' }}
                            </p>
                        </div>

                        <div class="flex-1 space-y-3 overflow-y-auto p-4">
                            <div
                                v-for="apt in selectedDayEvents"
                                :key="apt.id"
                                class="rounded-xl border border-base-300 bg-base-100 p-3 transition hover:border-primary/40 hover:bg-base-200/40"
                            >
                                <button
                                    type="button"
                                    class="w-full text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    @click="openDetail(apt)"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-mono text-sm font-semibold text-primary">
                                                {{ formatRange(apt) }}
                                            </p>
                                            <p class="mt-1 truncate font-medium text-base-content">
                                                {{ apt.summary || 'Sin asunto' }}
                                            </p>
                                        </div>
                                        <i class="pi pi-angle-right mt-1 text-base-content/40" />
                                    </div>

                                    <p
                                        v-if="apt.user_phone"
                                        class="mt-2 flex items-center gap-1.5 text-sm text-base-content/70"
                                    >
                                        <i class="pi pi-phone text-xs" />
                                        {{ apt.user_phone }}
                                    </p>

                                    <p
                                        v-if="apt.description"
                                        class="mt-2 line-clamp-2 text-xs text-base-content/50"
                                    >
                                        {{ apt.description }}
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        <span
                                            class="badge badge-sm"
                                            :class="sourceTone(apt.source)"
                                        >
                                            {{ sourceLabel(apt.source) }}
                                        </span>
                                        <span
                                            class="badge badge-sm"
                                            :class="syncTone(apt.sync_status)"
                                            :title="apt.sync_error || ''"
                                        >
                                            {{ syncLabel(apt.sync_status) }}
                                        </span>
                                    </div>
                                </button>

                                <div class="mt-3 flex justify-end border-t border-base-300 pt-2">
                                    <Button
                                        label="Eliminar"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        size="small"
                                        :disabled="deleting"
                                        @click="askDelete(apt, $event)"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="!selectedDayEvents.length"
                                class="flex flex-col items-center justify-center gap-2 py-12 text-center"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full bg-base-200 text-base-content/40"
                                >
                                    <i class="pi pi-calendar text-xl" />
                                </div>
                                <p class="text-sm font-medium text-base-content/70">
                                    Sin citas este día
                                </p>
                                <p class="max-w-[14rem] text-xs text-base-content/50">
                                    Elige otro día en el calendario o sincroniza desde Google.
                                </p>
                            </div>
                        </div>

                        <div
                            class="border-t border-base-300 px-4 py-3 text-xs text-base-content/50"
                        >
                            <p class="flex flex-wrap items-center gap-3">
                                <span class="inline-flex items-center gap-1">
                                    <span class="h-2 w-2 rounded-full bg-primary" /> Bot
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="h-2 w-2 rounded-full bg-info" /> Google
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="h-2 w-2 rounded-full bg-error" /> Error
                                </span>
                            </p>
                        </div>
                    </aside>
                </div>

                <p class="text-xs text-base-content/45">
                    Sincronizar trae citas de Google (últimos 7 días → próximos 60).
                    El bot revisa ocupación local y de Google antes de agendar.
                </p>
            </div>
        </div>

        <Dialog
            v-model:visible="detailOpen"
            modal
            :header="selectedApt?.summary || 'Detalle de cita'"
            class="w-full max-w-lg"
            :dismissable-mask="true"
        >
            <div v-if="selectedApt" class="space-y-4 text-sm">
                <div class="rounded-xl bg-base-200/60 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">
                        Horario
                    </p>
                    <p class="mt-1 text-base font-semibold text-base-content">
                        {{ formatRange(selectedApt) }}
                    </p>
                    <p class="mt-1 capitalize text-base-content/60">
                        {{
                            parseDate(selectedApt.starts_at)?.toLocaleDateString('es-MX', {
                                weekday: 'long',
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })
                        }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-base-content/50">
                            Teléfono
                        </p>
                        <p class="mt-1 font-medium">
                            {{ selectedApt.user_phone || '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-base-content/50">
                            Zona horaria
                        </p>
                        <p class="mt-1 font-medium">
                            {{ selectedApt.timezone || '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-base-content/50">
                            Origen
                        </p>
                        <p class="mt-1">
                            <span
                                class="badge badge-sm"
                                :class="sourceTone(selectedApt.source)"
                            >
                                {{ sourceLabel(selectedApt.source) }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-base-content/50">
                            Sync
                        </p>
                        <p class="mt-1">
                            <span
                                class="badge badge-sm"
                                :class="syncTone(selectedApt.sync_status)"
                            >
                                {{ syncLabel(selectedApt.sync_status) }}
                            </span>
                        </p>
                    </div>
                </div>

                <div v-if="selectedApt.description">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">
                        Descripción
                    </p>
                    <p class="mt-1 whitespace-pre-wrap text-base-content/80">
                        {{ selectedApt.description }}
                    </p>
                </div>

                <div
                    v-if="selectedApt.sync_error"
                    class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-error"
                >
                    {{ selectedApt.sync_error }}
                </div>

                <div v-if="selectedApt.google_html_link" class="pt-1">
                    <a
                        :href="selectedApt.google_html_link"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 text-primary hover:underline"
                    >
                        <i class="pi pi-external-link text-xs" />
                        Abrir en Google Calendar
                    </a>
                </div>
            </div>

            <template #footer>
                <div class="flex w-full flex-wrap justify-end gap-2">
                    <Button
                        label="Cerrar"
                        severity="secondary"
                        text
                        @click="detailOpen = false"
                    />
                    <Button
                        label="Eliminar cita"
                        icon="pi pi-trash"
                        severity="danger"
                        :disabled="deleting"
                        @click="askDelete(selectedApt)"
                    />
                </div>
            </template>
        </Dialog>

        <Dialog
            v-model:visible="deleteOpen"
            modal
            header="Eliminar cita"
            class="w-full max-w-md"
            :dismissable-mask="!deleting"
            :closable="!deleting"
        >
            <div v-if="pendingDelete" class="space-y-4 text-sm">
                <p>
                    ¿Eliminar
                    <strong>{{ pendingDelete.summary || 'esta cita' }}</strong>
                    del
                    {{ formatRange(pendingDelete) }}?
                </p>
                <p class="text-base-content/60">
                    Esta acción no se puede deshacer.
                </p>
                <label
                    v-if="pendingDelete.has_google_event"
                    class="flex cursor-pointer items-start gap-2 rounded-lg border border-base-300 bg-base-200/40 p-3"
                >
                    <input
                        v-model="deleteFromGoogle"
                        type="checkbox"
                        class="checkbox checkbox-sm mt-0.5"
                        :disabled="deleting"
                    />
                    <span>
                        <span class="font-medium">También eliminar de Google Calendar</span>
                        <span class="mt-0.5 block text-xs text-base-content/55">
                            Si lo desmarcas, solo se borra del sistema y puede volver al sincronizar.
                        </span>
                    </span>
                </label>
            </div>

            <template #footer>
                <div class="flex w-full flex-wrap justify-end gap-2">
                    <Button
                        label="Cancelar"
                        severity="secondary"
                        text
                        :disabled="deleting"
                        @click="deleteOpen = false"
                    />
                    <Button
                        label="Eliminar"
                        icon="pi pi-trash"
                        severity="danger"
                        :loading="deleting"
                        @click="confirmDelete"
                    />
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
