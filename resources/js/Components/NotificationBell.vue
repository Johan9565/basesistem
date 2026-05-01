<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Popover from 'primevue/popover';
import VirtualScroller from 'primevue/virtualscroller';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import SelectButton from 'primevue/selectbutton';

const page = usePage();

const popoverRef = ref(null);
const bellBtnRef = ref(null);

const filter = ref('all');
const filterOptions = [
    { label: 'Todas', value: 'all' },
    { label: 'No leídas', value: 'unread' },
];

const total = ref(0);
const rows = ref([]);
const initialLoaded = ref(false);
const rangeLoading = ref(false);
const markingAll = ref(false);
const panelOpen = ref(false);

const unreadCount = computed(
    () => Number(page.props?.auth?.notification_unread_count ?? 0) || 0,
);

const scrollerItems = computed(() => rows.value);

function authHeaders() {
    const token = page.props?.csrf_token;
    return token
        ? { 'X-CSRF-TOKEN': token, Accept: 'application/json' }
        : { Accept: 'application/json' };
}

function togglePopover(event) {
    popoverRef.value?.toggle(event, bellBtnRef.value);
}

function isSkeletonRow(item) {
    return item && item.__skeleton === true;
}

function resetPanelState() {
    rangeLoading.value = false;
    initialLoaded.value = false;
    total.value = 0;
    rows.value = [];
}

async function fetchChunk(offset, limit) {
    const { data } = await window.axios.get(route('notifications.feed'), {
        params: { offset, limit, filter: filter.value },
        headers: authHeaders(),
    });
    return data;
}

/** Primera carga al abrir el panel: total + primer bloque. */
async function loadInitial() {
    if (initialLoaded.value || rangeLoading.value) {
        return;
    }
    rangeLoading.value = true;
    try {
        const chunkLimit = 28;
        const data = await fetchChunk(0, chunkLimit);
        total.value = data.total ?? 0;
        if (total.value === 0) {
            rows.value = [];
            initialLoaded.value = true;
            return;
        }
        const next = Array.from({ length: total.value }, (_, i) =>
            i < (data.items?.length ?? 0) ? data.items[i] : { __skeleton: true },
        );
        rows.value = next;
        initialLoaded.value = true;
    } finally {
        rangeLoading.value = false;
    }
}

function mergeSlice(offset, itemsFromApi) {
    const next = rows.value.slice();
    for (let j = 0; j < itemsFromApi.length; j++) {
        const idx = offset + j;
        if (idx < next.length) {
            next[idx] = itemsFromApi[j];
        }
    }
    rows.value = next;
}

async function onLazyLoad(event) {
    if (!initialLoaded.value || total.value === 0) {
        return;
    }
    const first = typeof event.first === 'number' ? event.first : 0;
    const last = typeof event.last === 'number' ? event.last : first;
    let from = first;
    let to = last;
    for (let i = first; i <= last; i++) {
        const row = rows.value[i];
        if (!row || isSkeletonRow(row)) {
            from = i;
            break;
        }
    }
    for (let i = last; i >= first; i--) {
        const row = rows.value[i];
        if (!row || isSkeletonRow(row)) {
            to = i;
            break;
        }
    }
    let needs = false;
    for (let i = from; i <= to; i++) {
        const row = rows.value[i];
        if (!row || isSkeletonRow(row)) {
            needs = true;
            break;
        }
    }
    if (!needs || rangeLoading.value) {
        return;
    }
    rangeLoading.value = true;
    try {
        const limit = Math.min(50, to - from + 1);
        const data = await fetchChunk(from, limit);
        if (data.items?.length) {
            mergeSlice(from, data.items);
        }
    } finally {
        rangeLoading.value = false;
    }
}

function onPopoverShow() {
    panelOpen.value = true;
    resetPanelState();
    loadInitial();
}

function onPopoverHide() {
    panelOpen.value = false;
}

watch(filter, () => {
    if (panelOpen.value) {
        resetPanelState();
        loadInitial();
    }
});

async function markAllRead() {
    if (markingAll.value) {
        return;
    }
    markingAll.value = true;
    try {
        await window.axios.post(
            route('notifications.mark-all-read'),
            {},
            { headers: authHeaders() },
        );
        router.reload({ only: ['auth'], preserveScroll: true });
        resetPanelState();
        await loadInitial();
    } finally {
        markingAll.value = false;
    }
}

function notificationRowLinks(item) {
    const raw = item?.links;
    if (!Array.isArray(raw) || !raw.length) {
        return [];
    }
    return raw.filter(
        (l) => l && typeof l.href === 'string' && l.href !== '',
    );
}

async function visitNotificationLink(item, href) {
    if (!item?.id || isSkeletonRow(item) || !href) {
        return;
    }
    if (!item.is_read) {
        try {
            await window.axios.patch(
                route('notifications.read', { notification: item.id }),
                {},
                { headers: authHeaders() },
            );
            const idx = rows.value.findIndex((r) => r?.id === item.id);
            if (idx !== -1) {
                const next = rows.value.slice();
                next[idx] = {
                    ...next[idx],
                    is_read: true,
                    read_at: new Date().toISOString(),
                };
                rows.value = next;
            }
            router.reload({ only: ['auth'], preserveScroll: true });
        } catch {
            /* la navegación sigue si falla el PATCH */
        }
    }
    popoverRef.value?.hide();
    router.visit(href, { preserveScroll: true });
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat('es', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch {
        return '';
    }
}
</script>

<template>
    <div class="relative flex items-center">
        <button
            ref="bellBtnRef"
            type="button"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-base-content/70 transition hover:bg-base-200 hover:text-base-content focus:outline-hidden focus:ring-2 focus:ring-primary/30"
            aria-label="Notificaciones"
            @click="togglePopover"
        >
            <i class="pi pi-bell text-lg"></i>
            <span
                v-if="unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-0.5 text-[10px] font-semibold leading-none text-error-content"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <Popover
            ref="popoverRef"
            class="w-[min(100vw-1.5rem,22rem)]"
            @show="onPopoverShow"
            @hide="onPopoverHide"
        >
            <div class="flex flex-col gap-3 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-base-content">
                        Notificaciones
                    </span>
                    <Button
                        label="Marcar todas leídas"
                        size="small"
                        text
                        severity="secondary"
                        :loading="markingAll"
                        :disabled="unreadCount === 0 || markingAll"
                        @click="markAllRead"
                    />
                </div>
                <p class="text-xs text-base-content/60">
                    Mes en curso (todas las notificaciones de este mes).
                </p>
                <SelectButton
                    v-model="filter"
                    :options="filterOptions"
                    option-label="label"
                    option-value="value"
                    :allow-empty="false"
                    class="w-full text-xs"
                />

                <div
                    v-if="initialLoaded && total === 0"
                    class="rounded-lg border border-base-300 bg-base-200/40 px-3 py-6 text-center text-sm text-base-content/60"
                >
                    No hay notificaciones este mes.
                </div>

                <VirtualScroller
                    v-else-if="total > 0"
                    :items="scrollerItems"
                    :item-size="168"
                    scroll-height="280px"
                    class="rounded-lg border border-base-300 bg-base-100 shadow-inner"
                    :lazy="true"
                    :show-loader="true"
                    @lazy-load="onLazyLoad"
                >
                    <template #item="{ item }">
                        <div
                            v-if="isSkeletonRow(item)"
                            class="box-border flex min-h-[168px] flex-col justify-center gap-2 border-b border-base-200 px-3 py-2"
                        >
                            <Skeleton width="85%" height="0.75rem" class="rounded" />
                            <Skeleton width="60%" height="0.65rem" class="rounded" />
                            <Skeleton width="40%" height="0.6rem" class="rounded" />
                        </div>
                        <div
                            v-else
                            class="box-border flex min-h-[168px] flex-col gap-1 border-b border-base-200 px-3 py-2"
                            :class="
                                item.is_read
                                    ? 'opacity-80'
                                    : 'border-s-2 border-s-primary bg-primary/5'
                            "
                        >
                            <span
                                class="line-clamp-2 text-sm leading-snug text-base-content"
                            >
                                {{ item.message }}
                            </span>
                            <span class="text-xs text-base-content/50">
                                {{ formatWhen(item.created_at) }}
                            </span>

                            <div
                                class="mt-auto flex flex-wrap gap-1 border-t border-base-200/80 pt-2"
                            >
                                <template
                                    v-if="notificationRowLinks(item).length"
                                >
                                    <Button
                                        v-for="(lnk, i) in notificationRowLinks(
                                            item,
                                        )"
                                        :key="i"
                                        type="button"
                                        size="small"
                                        :label="lnk.label"
                                        severity="secondary"
                                        outlined
                                        class="!py-1 text-xs"
                                        @click.stop="
                                            visitNotificationLink(item, lnk.href)
                                        "
                                    />
                                </template>
                                <span
                                    v-else
                                    class="self-center text-xs text-base-content/50"
                                >
                                    Sin enlace
                                </span>
                            </div>
                        </div>
                    </template>
                    <template #loader>
                        <div
                            class="flex min-h-[168px] flex-col justify-center gap-2 border-b border-base-200 px-3 py-2"
                        >
                            <Skeleton
                                width="85%"
                                height="0.75rem"
                                class="rounded"
                            />
                            <Skeleton
                                width="60%"
                                height="0.65rem"
                                class="rounded"
                            />
                            <Skeleton
                                width="40%"
                                height="0.6rem"
                                class="rounded"
                            />
                        </div>
                    </template>
                </VirtualScroller>

                <div
                    v-else-if="!initialLoaded"
                    class="space-y-2 rounded-lg border border-base-300 p-2"
                >
                    <Skeleton height="5rem" class="rounded-lg" />
                    <Skeleton height="5rem" class="rounded-lg" />
                    <Skeleton height="5rem" class="rounded-lg" />
                </div>
            </div>
        </Popover>
    </div>
</template>
