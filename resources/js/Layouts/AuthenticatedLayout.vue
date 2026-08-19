<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import {
    NOTIFICACION_TO_USER_EVENT,
    PROFILE_HIGHLIGHT_FIELDS_EVENT,
} from '@/composables/useNotificacionToUser';

const showingNavigationDropdown = ref(false);

const page = usePage();
// En visitas parciales o al volver el foco a la pestaña, `page.props` puede ser undefined un instante.
const menu = computed(() => page.props?.auth?.menu ?? []);
const toast = useToast();

function broadcastUrls(payload) {
    const raw = payload.urls;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter((u) => typeof u === 'string' && u !== '');
    }
    if (payload.url) {
        return [String(payload.url)];
    }
    return [];
}

/** Enlaces con etiqueta (payload.links) o solo URLs legadas (url / urls). */
function broadcastLinks(payload) {
    const raw = payload.links;
    if (Array.isArray(raw) && raw.length) {
        return raw.filter(
            (l) => l && typeof l.href === 'string' && l.href !== '',
        );
    }
    const urls = broadcastUrls(payload);
    return urls.map((href, i) => ({
        label: `Enlace ${i + 1}`,
        href,
    }));
}

function openToastLink(message, lnk) {
    if (!lnk?.href) {
        return;
    }
    router.visit(lnk.href, { preserveScroll: true });
    toast.remove(message);
}

function pathnameMatchesCurrentPaths(paths) {
    if (!paths?.length) {
        return true;
    }
    const path = window.location.pathname;
    return paths.some(
        (p) => path === p || path.startsWith(p.endsWith('/') ? p : `${p}/`),
    );
}

onMounted(() => {
    const echo = window.Echo;
    const userId = page.props?.auth?.user?.id;
    if (!echo || userId == null || userId === '') {
        return;
    }

    const uid = String(userId);
    const channelName = `notifications_create_office.${uid}`;

    echo.private(channelName).listen('.notificacion.to.user', (payload) => {
        const authId = String(uid);
        const recipientId = String(payload.meta?.recipientId ?? '');
        if (recipientId !== '' && recipientId !== authId) {
            return;
        }

        const scopedPaths = payload.currentPaths ?? [];
        const scopedContextMatched =
            !scopedPaths.length || pathnameMatchesCurrentPaths(scopedPaths);

        window.dispatchEvent(
            new CustomEvent(NOTIFICACION_TO_USER_EVENT, {
                detail: { ...payload, scopedContextMatched },
            }),
        );

        const toastLinks = broadcastLinks(payload);
        const toastMessage = {
            severity: 'info',
            summary: 'Notificación',
            detail: payload.message,
            life: toastLinks.length ? 20_000 : 8000,
            links: toastLinks,
        };

        toast.add(toastMessage);

        const globalInertia = payload.meta?.inertiaGlobal;
        const scopedInertia = payload.meta?.inertia;
        const highlightKeys = payload.meta?.highlightDisplayKeys;

        const globalOnly = globalInertia?.only?.length ? globalInertia.only : [];
        const scopedOnly =
            scopedContextMatched && scopedInertia?.only?.length
                ? scopedInertia.only
                : [];
        const mergedOnly = [...new Set([...globalOnly, ...scopedOnly])];

        const preserveScroll =
            scopedInertia?.preserveScroll !== false &&
            globalInertia?.preserveScroll !== false;

        if (mergedOnly.length) {
            router.reload({
                only: mergedOnly,
                preserveScroll,
                onSuccess: () => {
                    if (scopedContextMatched && highlightKeys?.length) {
                        window.dispatchEvent(
                            new CustomEvent(PROFILE_HIGHLIGHT_FIELDS_EVENT, {
                                detail: { keys: highlightKeys },
                            }),
                        );
                    }
                },
            });
        } else if (scopedContextMatched && highlightKeys?.length) {
            window.dispatchEvent(
                new CustomEvent(PROFILE_HIGHLIGHT_FIELDS_EVENT, {
                    detail: { keys: highlightKeys },
                }),
            );
        }
    });
});

onUnmounted(() => {
    const echo = window.Echo;
    const userId = page.props?.auth?.user?.id;
    if (!echo || userId == null || userId === '') {
        return;
    }
    echo.leave(`notifications_create_office.${String(userId)}`);
});
</script>

<template>
    <div>
        <Toast position="top-right">
            <template #message="{ message }">
                <div
                    class="flex flex-1 flex-col gap-2 pe-6"
                    :class="message.links?.length ? 'pt-0.5' : ''"
                >
                    <div class="font-medium leading-snug">
                        {{ message.summary }}
                    </div>
                    <div
                        v-if="message.detail"
                        class="text-sm leading-snug opacity-90"
                    >
                        {{ message.detail }}
                    </div>
                    <div
                        v-if="message.links?.length"
                        class="flex flex-wrap gap-1"
                    >
                        <Button
                            v-for="(lnk, i) in message.links"
                            :key="i"
                            type="button"
                            :label="lnk.label"
                            size="small"
                            severity="secondary"
                            @click.stop="openToastLink(message, lnk)"
                        />
                    </div>
                </div>
            </template>
        </Toast>
        <div class="ps-shell font-sans antialiased">
            <div class="ps-blob ps-blob-a" aria-hidden="true"></div>
            <div class="ps-blob ps-blob-b" aria-hidden="true"></div>
            <div class="ps-blob ps-blob-c" aria-hidden="true"></div>

            <nav class="ps-topbar">
                <div class="mx-auto max-w-6xl px-6">
                    <div class="flex items-center justify-between gap-4 py-4">
                        <div class="flex min-w-0 items-center gap-6">
                            <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2">
                                <ApplicationLogo class="h-11 w-auto shrink-0" />
                                <span class="truncate text-lg font-semibold tracking-tight">
                                    pa-saber
                                </span>
                            </Link>

                            <div class="hidden items-center gap-6 md:flex">
                                <template v-for="modulo in menu" :key="modulo.id">
                                    <Dropdown
                                        v-if="modulo.is_dropdown"
                                        align="left"
                                        width="48"
                                    >
                                        <template #trigger>
                                            <button type="button" class="ps-nav-link">
                                                <i v-if="modulo.icon" v-html="modulo.icon" class="mr-2"></i>
                                                {{ modulo.name }}
                                                <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink
                                                v-for="child in modulo.children ?? []"
                                                :key="child.id"
                                                :href="route(child.route)"
                                            >
                                                <i v-if="child.icon" v-html="child.icon" class="mr-2"></i>
                                                {{ child.name }}
                                            </DropdownLink>
                                            <span
                                                v-if="!modulo.children?.length"
                                                class="block px-4 py-2 text-sm ps-muted"
                                            >
                                                Sin opciones
                                            </span>
                                        </template>
                                    </Dropdown>

                                    <NavLink
                                        v-else
                                        :href="route(modulo.route)"
                                        :active="route().current(modulo.route)"
                                    >
                                        <i v-if="modulo.icon" v-html="modulo.icon" class="mr-2"></i>
                                        {{ modulo.name }}
                                    </NavLink>
                                </template>
                            </div>
                        </div>

                        <div class="hidden items-center gap-2 md:flex">
                            <NotificationBell />
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-full border border-[#eadfd2] bg-white px-3 py-2 text-sm font-semibold"
                                    >
                                        {{ $page.props?.auth?.user?.name }}
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">
                                        Perfil
                                    </DropdownLink>
                                    <DropdownLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                    >
                                        Cerrar sesión
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <div class="flex items-center gap-2 md:hidden">
                            <NotificationBell />
                            <button
                                type="button"
                                class="ps-icon-btn"
                                :aria-expanded="showingNavigationDropdown"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <span class="sr-only">Abrir menú</span>
                                <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current" stroke-width="1.8">
                                    <path v-if="!showingNavigationDropdown" d="M4 7h16M4 12h16M4 17h16" />
                                    <path v-else d="M6 6l12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="showingNavigationDropdown"
                    class="border-t border-[#eadfd2] px-6 py-4 md:hidden"
                >
                    <div class="flex flex-col gap-1">
                        <template v-for="modulo in menu" :key="modulo.id">
                            <template v-if="modulo.is_dropdown">
                                <div class="px-4 py-2 text-xs font-semibold uppercase tracking-wider ps-muted">
                                    <i v-if="modulo.icon" v-html="modulo.icon" class="mr-1"></i>
                                    {{ modulo.name }}
                                </div>
                                <ResponsiveNavLink
                                    v-for="child in modulo.children ?? []"
                                    :key="child.id"
                                    :href="route(child.route)"
                                    :active="route().current(child.route)"
                                    class="pl-8"
                                >
                                    <i v-if="child.icon" v-html="child.icon" class="mr-2"></i>
                                    {{ child.name }}
                                </ResponsiveNavLink>
                            </template>

                            <ResponsiveNavLink
                                v-else
                                :href="route(modulo.route)"
                                :active="route().current(modulo.route)"
                            >
                                <i v-if="modulo.icon" v-html="modulo.icon" class="mr-2"></i>
                                {{ modulo.name }}
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <div class="mt-4 border-t border-[#eadfd2] pt-4">
                        <div class="px-4">
                            <div class="text-base font-semibold">
                                {{ $page.props?.auth?.user?.name }}
                            </div>
                            <div class="text-sm ps-muted">
                                {{ $page.props?.auth?.user?.email }}
                            </div>
                        </div>
                        <div class="mt-3 flex flex-col gap-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Perfil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Cerrar sesión
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="ps-pagehead">
                <div class="mx-auto max-w-6xl px-6 py-6">
                    <slot name="header" />
                </div>
            </header>

            <main class="ps-main">
                <slot />
            </main>
        </div>
    </div>
</template>
