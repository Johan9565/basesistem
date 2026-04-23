<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import { tApp } from '@/i18n/locales';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Timeline from 'primevue/timeline';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';

function statusToClass(status) {
    const s = (status || '').toLowerCase();
    if (s.includes('cancel')) return 'fc-event-cancelled';
    if (s.includes('confirm')) return 'fc-event-confirmed';
    if (s.includes('pend')) return 'fc-event-pending';
    return 'fc-event-default';
}

const fullCalendarRef = ref(null);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const saving = ref(false);
const deleting = ref(false);
const createForm = ref({
    title: '',
    start: '',
    end: '',
    status: 'pending',
    notes: '',
});
const editId = ref(null);
const editForm = ref({
    title: '',
    start: '',
    end: '',
    status: 'pending',
    notes: '',
});

const appLang = ref(
    typeof document !== 'undefined'
        ? document.documentElement.getAttribute('lang') || 'en'
        : 'en',
);

function t(key) {
    return tApp(appLang.value, key);
}

const toast = useToast();
const createErrors = ref({});
const editErrors = ref({});
const loadedEvents = ref([]);

function toDateOrNull(s) {
    if (!s) return null;
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? null : d;
}

function formatDateTime(d) {
    if (!(d instanceof Date) || Number.isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat(appLang.value === 'es' ? 'es' : 'en', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

function statusSeverity(status) {
    const s = String(status || '').toLowerCase();
    if (s.includes('confirm')) return 'success';
    if (s.includes('cancel')) return 'danger';
    if (s.includes('pend')) return 'warning';
    return 'secondary';
}

const timelineItems = computed(() => {
    const items = (loadedEvents.value || [])
        .map((e) => {
            const start = toDateOrNull(e.start);
            const end = toDateOrNull(e.end);
            return {
                id: e.id,
                title: e.title || '',
                start,
                end,
                status: e?.extendedProps?.status || '',
                notes: e?.extendedProps?.notes || '',
            };
        })
        .filter((x) => x.start);

    items.sort((a, b) => a.start - b.start);
    return items;
});

function openFromTimeline(item) {
    const api = fullCalendarRef.value?.getApi?.();
    const ev = api?.getEventById?.(String(item.id));
    if (ev) {
        openEditDialog(ev);
    }
}

function computeIsMobile() {
    if (typeof window === 'undefined') return false;
    return window.matchMedia?.('(max-width: 640px)')?.matches ?? false;
}

const isMobile = ref(computeIsMobile());

function applyCalendarResponsiveOptions() {
    const api = fullCalendarRef.value?.getApi?.();
    if (!api) return;

    const mobile = isMobile.value;
    api.setOption('headerToolbar', {
        left: mobile ? 'prev,next' : 'prev,next today',
        center: 'title',
        right: mobile ? 'dayGridMonth,timeGridDay,listWeek' : 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    });
    api.setOption('stickyHeaderDates', !mobile);
    api.setOption('dayMaxEventRows', mobile ? 2 : true);
}

const statusOptions = computed(() => [
    { label: t('statusPending'), value: 'pending' },
    { label: t('statusConfirmed'), value: 'confirmed' },
    { label: t('statusCancelled'), value: 'cancelled' },
]);

function openCreateDialog() {
    createErrors.value = {};
    showCreateDialog.value = true;
}

function closeCreateDialog() {
    createErrors.value = {};
    showCreateDialog.value = false;
}

function openEditDialog(event) {
    const id = event?.id ?? null;
    if (!id) {
        return;
    }

    editId.value = String(id);
    editErrors.value = {};
    editForm.value = {
        title: event?.title ?? '',
        start: event?.start ? toLocalDateTimeInput(event.start) : '',
        end: event?.end ? toLocalDateTimeInput(event.end) : '',
        status: event?.extendedProps?.status ?? 'pending',
        notes: event?.extendedProps?.notes ?? '',
    };
    showEditDialog.value = true;
}

function closeEditDialog() {
    editErrors.value = {};
    showEditDialog.value = false;
    editId.value = null;
}

function setFormErrors(targetRef, axiosError) {
    const status = axiosError?.response?.status;
    if (status === 422) {
        targetRef.value = axiosError?.response?.data?.errors ?? {};
        toast.add({
            severity: 'warn',
            summary:
                appLang.value === 'es'
                    ? 'Revisa los campos'
                    : 'Please check the fields',
            life: 5000,
        });
        return;
    }

    targetRef.value = {};
    toast.add({
        severity: 'error',
        summary: appLang.value === 'es' ? 'Error al guardar' : 'Failed to save',
        life: 5000,
    });
}

function serializeBookingPayload(form) {
    return {
        title: form.title,
        start: form.start || null,
        end: form.end || null,
        status: form.status,
        notes: form.notes,
    };
}

function pad2(n) {
    return String(n).padStart(2, '0');
}

function toLocalDateTimeInput(d) {
    const dt = d instanceof Date ? d : new Date(d);
    if (Number.isNaN(dt.getTime())) return '';
    return `${dt.getFullYear()}-${pad2(dt.getMonth() + 1)}-${pad2(dt.getDate())}T${pad2(dt.getHours())}:${pad2(dt.getMinutes())}`;
}

function openNativeDateTimePicker(ev) {
    const el = ev?.currentTarget;
    try {
        el?.showPicker?.();
    } catch {
        // Some browsers require strict user gesture; ignore.
    }
}

async function saveBooking() {
    saving.value = true;
    try {
        createErrors.value = {};
        await axios.post('/bookings', serializeBookingPayload(createForm.value));
        showCreateDialog.value = false;
        createForm.value = {
            title: '',
            start: '',
            end: '',
            status: 'pending',
            notes: '',
        };

        fullCalendarRef.value?.getApi?.().refetchEvents();
        toast.add({
            severity: 'success',
            summary: appLang.value === 'es' ? 'Reserva creada' : 'Booking created',
            life: 3000,
        });
    } catch (e) {
        setFormErrors(createErrors, e);
    } finally {
        saving.value = false;
    }
}

async function updateBooking() {
    if (!editId.value) return;
    saving.value = true;
    try {
        editErrors.value = {};
        await axios.patch(
            `/bookings/${editId.value}`,
            serializeBookingPayload(editForm.value),
        );
        showEditDialog.value = false;
        fullCalendarRef.value?.getApi?.().refetchEvents();
        toast.add({
            severity: 'success',
            summary: appLang.value === 'es' ? 'Reserva actualizada' : 'Booking updated',
            life: 3000,
        });
    } catch (e) {
        setFormErrors(editErrors, e);
    } finally {
        saving.value = false;
    }
}

async function deleteBooking() {
    if (!editId.value) return;
    deleting.value = true;
    try {
        await axios.delete(`/bookings/${editId.value}`);
        showEditDialog.value = false;
        fullCalendarRef.value?.getApi?.().refetchEvents();
        toast.add({
            severity: 'success',
            summary: appLang.value === 'es' ? 'Reserva eliminada' : 'Booking deleted',
            life: 3000,
        });
    } finally {
        deleting.value = false;
    }
}

async function persistEventMove(changeInfo) {
    const id = changeInfo?.event?.id;
    if (!id) return;
    try {
        await axios.patch(`/bookings/${id}`, {
            start: changeInfo.event.startStr,
            end: changeInfo.event.endStr,
        });
    } catch (e) {
        changeInfo?.revert?.();
        toast.add({
            severity: 'error',
            summary:
                appLang.value === 'es'
                    ? 'No se pudo actualizar'
                    : 'Could not update booking',
            life: 4000,
        });
    }
}

const calendarLocale = ref(
    typeof document !== 'undefined' &&
        document.documentElement.getAttribute('lang') === 'es'
        ? esLocale
        : 'en',
);

if (typeof window !== 'undefined') {
    window.addEventListener('app:language-changed', (ev) => {
        const lang = ev?.detail?.lang;
        appLang.value = lang === 'es' ? 'es' : 'en';
        calendarLocale.value = lang === 'es' ? esLocale : 'en';
        fullCalendarRef.value?.getApi?.().setOption('locale', calendarLocale.value);
    });
}

const calendarOptions = {
    // IMPORTANT: @fullcalendar/interaction registers non-passive touch listeners on init.
    // Avoid loading it on mobile to prevent scroll-blocking touchmove warnings.
    plugins: isMobile.value
        ? [dayGridPlugin, timeGridPlugin, listPlugin]
        : [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    height: 'auto',
    firstDay: 1,
    locale: calendarLocale.value,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
    },
    buttonText: appLang.value === 'es' ? { list: 'Agenda' } : undefined,
    navLinks: true,
    nowIndicator: true,
    ...(isMobile.value
        ? {}
        : {
              editable: true,
              eventResizableFromStart: true,
              selectable: true,
              selectMirror: true,
              select: (info) => {
                  createForm.value.start = info.start
                      ? toLocalDateTimeInput(info.start)
                      : '';
                  createForm.value.end = info.end
                      ? toLocalDateTimeInput(info.end)
                      : '';
                  openCreateDialog();
              },
              eventDrop: persistEventMove,
              eventResize: persistEventMove,
          }),
    eventClick: (info) => {
        openEditDialog(info?.event);
    },
    events: async (info, successCallback, failureCallback) => {
        try {
            const { data } = await axios.get('/bookings/feed', {
                params: { start: info.startStr, end: info.endStr },
            });

            loadedEvents.value = data || [];

            successCallback(
                (data || []).map((e) => ({
                    ...e,
                    classNames: [statusToClass(e?.extendedProps?.status)],
                })),
            );
        } catch (e) {
            failureCallback(e);
        }
    },
};

onMounted(() => {
    applyCalendarResponsiveOptions();

    if (typeof window !== 'undefined') {
        const onResize = () => {
            const next = computeIsMobile();
            if (next !== isMobile.value) {
                isMobile.value = next;
            }
            applyCalendarResponsiveOptions();
        };
        window.addEventListener('resize', onResize);
        onUnmounted(() => window.removeEventListener('resize', onResize));
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>


        <div class="">
            <div class="mx-auto">
                <div
                    class="overflow-hidden bg-base-100 shadow-xs sm:rounded-lg"
                >
                    <div class="p-6 text-base-content">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="text-lg font-semibold">{{ t('reservations') }}</div>
                            <Button
                                :label="t('newBooking')"
                                icon="pi pi-plus"
                                @click="openCreateDialog"
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                            <div class="lg:col-span-8">
                                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                    <FullCalendar ref="fullCalendarRef" :options="calendarOptions" />
                                </div>
                            </div>

                            <aside class="lg:col-span-4">
                                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                    <div class="mb-3 flex items-center justify-between">
                                        <div class="text-sm font-semibold opacity-80">
                                            {{ appLang === 'es' ? 'Agenda' : 'Timeline' }}
                                        </div>
                                        <div class="text-xs opacity-60">
                                            {{ timelineItems.length }}
                                        </div>
                                    </div>

                                    <div v-if="!timelineItems.length" class="text-sm opacity-60">
                                        {{ appLang === 'es' ? 'Sin reservas en este rango.' : 'No bookings in this range.' }}
                                    </div>

                                    <Timeline
                                        v-else
                                        :value="timelineItems"
                                        class="timeline-left max-h-[520px] overflow-auto"
                                        align="left"
                                    >
                                        <template #content="{ item }">
                                            <button
                                                type="button"
                                                class="timeline-item w-full text-left"
                                                @click="openFromTimeline(item)"
                                            >
                                                <div class="flex h-full flex-col justify-between gap-1">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <div class="truncate font-medium">
                                                            {{ item.title }}
                                                            </div>
                                                            <div class="mt-0.5 text-xs opacity-70">
                                                                {{ formatDateTime(item.start) }}
                                                                <span v-if="item.end">– {{ formatDateTime(item.end) }}</span>
                                                            </div>
                                                        </div>
                                                        <Tag
                                                            v-if="item.status"
                                                            :value="item.status"
                                                            :severity="statusSeverity(item.status)"
                                                        />
                                                    </div>
                                                    <div class="line-clamp-1 text-xs opacity-70">
                                                        {{ item.notes || ' ' }}
                                                    </div>
                                                </div>
                                            </button>
                                        </template>
                                    </Timeline>
                                </div>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog
            v-model:visible="showCreateDialog"
            modal
            :header="t('newBooking')"
            :style="{ width: 'min(600px, 95vw)' }"
            @hide="closeCreateDialog"
        >
            <div class="space-y-4">
                <div>
                    <div class="mb-1 text-sm font-medium opacity-80">{{ t('title') }}</div>
                    <InputText v-model="createForm.title" class="w-full" />
                    <div
                        v-if="createErrors.title?.length"
                        class="mt-1 text-sm text-error"
                    >
                        {{ createErrors.title[0] }}
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <div class="mb-1 text-sm font-medium opacity-80">{{ t('start') }}</div>
                        <input
                            v-model="createForm.start"
                            type="datetime-local"
                            class="input input-bordered w-full"
                            @click="openNativeDateTimePicker"
                        />
                        <div
                            v-if="createErrors.start?.length"
                            class="mt-1 text-sm text-error"
                        >
                            {{ createErrors.start[0] }}
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 text-sm font-medium opacity-80">{{ t('end') }}</div>
                        <input
                            v-model="createForm.end"
                            type="datetime-local"
                            class="input input-bordered w-full"
                            @click="openNativeDateTimePicker"
                        />
                        <div
                            v-if="createErrors.end?.length"
                            class="mt-1 text-sm text-error"
                        >
                            {{ createErrors.end[0] }}
                        </div>
                    </div>
                </div>
                <div>
                    <div class="mb-1 text-sm font-medium opacity-80">{{ t('status') }}</div>
                    <Select
                        v-model="createForm.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <div class="mb-1 text-sm font-medium opacity-80">{{ t('notes') }}</div>
                    <Textarea v-model="createForm.notes" class="w-full" rows="4" />
                </div>
            </div>

            <template #footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <Button
                        :label="t('cancel')"
                        severity="secondary"
                        :disabled="saving"
                        @click="closeCreateDialog"
                    />
                    <Button
                        :label="t('save')"
                        icon="pi pi-check"
                        :loading="saving"
                        @click="saveBooking"
                    />
                </div>
            </template>
        </Dialog>

        <Dialog
            v-model:visible="showEditDialog"
            modal
            :header="t('editBooking')"
            :style="{ width: 'min(600px, 95vw)' }"
            @hide="closeEditDialog"
        >
            <div class="space-y-4">
                <div>
                    <div class="mb-1 text-sm font-medium opacity-80">{{ t('title') }}</div>
                    <InputText v-model="editForm.title" class="w-full" />
                    <div
                        v-if="editErrors.title?.length"
                        class="mt-1 text-sm text-error"
                    >
                        {{ editErrors.title[0] }}
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <div class="mb-1 text-sm font-medium opacity-80">{{ t('start') }}</div>
                        <input
                            v-model="editForm.start"
                            type="datetime-local"
                            class="input input-bordered w-full"
                            @click="openNativeDateTimePicker"
                        />
                        <div
                            v-if="editErrors.start?.length"
                            class="mt-1 text-sm text-error"
                        >
                            {{ editErrors.start[0] }}
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 text-sm font-medium opacity-80">{{ t('end') }}</div>
                        <input
                            v-model="editForm.end"
                            type="datetime-local"
                            class="input input-bordered w-full"
                            @click="openNativeDateTimePicker"
                        />
                        <div
                            v-if="editErrors.end?.length"
                            class="mt-1 text-sm text-error"
                        >
                            {{ editErrors.end[0] }}
                        </div>
                    </div>
                </div>
                <div>
                    <div class="mb-1 text-sm font-medium opacity-80">{{ t('status') }}</div>
                    <Select
                        v-model="editForm.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <div class="mb-1 text-sm font-medium opacity-80">{{ t('notes') }}</div>
                    <Textarea v-model="editForm.notes" class="w-full" rows="4" />
                </div>
            </div>

            <template #footer>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <Button
                        type="button"
                        severity="danger"
                        icon="pi pi-trash"
                        :label="t('delete')"
                        :loading="deleting"
                        @click="deleteBooking"
                    />
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                        <Button
                            :label="t('cancel')"
                            severity="secondary"
                            :disabled="saving || deleting"
                            @click="closeEditDialog"
                        />
                        <Button
                            :label="t('save')"
                            icon="pi pi-check"
                            :loading="saving"
                            :disabled="deleting"
                            @click="updateBooking"
                        />
                    </div>
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<style>
.fc .fc-toolbar-title {
    font-size: 1.1rem;
}

.fc-event-default {
    background-color: oklch(62% 0.17 260);
    border-color: oklch(62% 0.17 260);
}
.fc-event-confirmed {
    background-color: oklch(66% 0.16 145);
    border-color: oklch(66% 0.16 145);
}
.fc-event-pending {
    background-color: oklch(78% 0.16 85);
    border-color: oklch(78% 0.16 85);
    color: oklch(23% 0.02 250);
}
.fc-event-cancelled {
    background-color: oklch(64% 0.2 25);
    border-color: oklch(64% 0.2 25);
}

.timeline-item {
    height: 86px;
    padding: 10px 10px;
    border-radius: 10px;
    transition: background-color 120ms ease;
}

.timeline-item:hover {
    background: color-mix(in oklch, currentColor 6%, transparent);
}

.timeline-left .p-timeline-event-opposite {
    display: none;
}

.timeline-left .p-timeline-event-content {
    padding-inline-start: 0.75rem;
}
</style>
