<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeSelector from '@/Components/ThemeSelector.vue';
import { Link, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';

const showingNavigationDropdown = ref(false);

const page = usePage();
const menu = computed(() => page.props.auth?.menu ?? []);
const can  = computed(() => page.props.auth?.can  ?? []);
</script>

<template>
    <div>
            <Toast />
        <div class="min-h-screen bg-base-200">
            <nav
                class="border-b border-base-300 bg-base-100"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 items-center justify-between">
                        <div class="flex items-center">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-base-content"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex sm:items-center">
                                <template v-for="modulo in menu" :key="modulo.id">
                                    <!-- Dropdown (relation == 0) -->
                                    <Dropdown
                                        v-if="modulo.is_dropdown"
                                        align="left"
                                        width="48"
                                    >
                                        <template #trigger>
                                            <button
                                                class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-base-content/70 transition duration-150 ease-in-out hover:border-base-300 hover:text-base-content focus:outline-hidden"
                                            >
                                                <i v-if="modulo.icon" v-html="modulo.icon" class="mr-2"></i>
                                                {{ modulo.name }}
                                                <svg class="ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink
                                                v-for="child in modulo.children"
                                                :key="child.id"
                                                :href="route(child.route)"
                                            >
                                                <i v-if="child.icon" v-html="child.icon" class="mr-2"></i>
                                                {{ child.name }}
                                            </DropdownLink>
                                            <span
                                                v-if="!modulo.children?.length"
                                                class="block px-4 py-2 text-sm text-base-content/60"
                                            >
                                                Sin opciones
                                            </span>
                                        </template>
                                    </Dropdown>

                                    <!-- Link normal -->
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

                        <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-1">
                            <ThemeSelector />
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-base-100 px-3 py-2 text-sm font-medium leading-4 text-base-content/70 transition duration-150 ease-in-out hover:text-base-content focus:outline-hidden"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
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
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
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
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-base-content/60 transition duration-150 ease-in-out hover:bg-base-200 hover:text-base-content focus:bg-base-200 focus:text-base-content focus:outline-hidden"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                <div class="pt-2 pb-3 space-y-1">
                    <template v-for="modulo in menu" :key="modulo.id">
                        <!-- Dropdown mobile: título no clickeable + hijos indentados -->
                        <template v-if="modulo.is_dropdown">
                            <div class="border-t border-base-300 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-base-content/60">
                                <i v-if="modulo.icon" v-html="modulo.icon" class="mr-1"></i>
                                {{ modulo.name }}
                            </div>
                            <ResponsiveNavLink
                                v-for="child in modulo.children"
                                :key="child.id"
                                :href="route(child.route)"
                                :active="route().current(child.route)"
                                class="pl-8"
                            >
                                <i v-if="child.icon" v-html="child.icon" class="mr-2"></i>
                                {{ child.name }}
                            </ResponsiveNavLink>
                        </template>

                        <!-- Link normal mobile -->
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

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-base-300 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-base-content"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-base-content/70">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-base-100 shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
