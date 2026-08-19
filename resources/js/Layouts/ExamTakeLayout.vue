<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="ps-shell font-sans antialiased">
        <div class="ps-blob ps-blob-a" aria-hidden="true"></div>
        <div class="ps-blob ps-blob-b" aria-hidden="true"></div>
        <div class="ps-blob ps-blob-c" aria-hidden="true"></div>

        <header class="ps-topbar">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-6 py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2">
                        <ApplicationLogo class="h-11 w-auto shrink-0" />
                        <span class="hidden truncate text-lg font-semibold tracking-tight sm:inline">
                            pa-saber
                        </span>
                    </Link>
                    <div class="min-w-0 border-l border-[#eadfd2] pl-3">
                        <p class="truncate text-sm font-semibold">
                            <slot name="title" />
                        </p>
                        <p class="truncate text-xs ps-muted">
                            <slot name="subtitle" />
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>
            <slot name="progress" />
        </header>

        <main class="ps-main mx-auto max-w-6xl px-6 py-8">
            <slot />
        </main>
    </div>
</template>
