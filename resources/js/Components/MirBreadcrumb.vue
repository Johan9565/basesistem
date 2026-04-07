<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav aria-label="Migas de pan">
        <ol class="flex flex-wrap items-center gap-x-0.5 gap-y-1 p-0">
            <template v-for="(item, i) in items" :key="i">
                <li
                    v-if="i > 0"
                    class="flex items-center px-0.5 text-base-content/30"
                    aria-hidden="true"
                >
                    <i class="pi pi-angle-right text-[10px]" />
                </li>
                <li class="flex min-w-0 items-center">
                    <Link
                        v-if="item.href && !item.current"
                        :href="item.href"
                        class="inline-flex max-w-full items-center rounded-lg px-2.5 py-1 text-xs font-medium text-base-content/65 transition-colors hover:bg-primary/10 hover:text-primary"
                    >
                        <span class="truncate">{{ item.label }}</span>
                    </Link>
                    <span
                        v-else
                        class="inline-flex max-w-full items-center rounded-lg bg-base-200/80 px-2.5 py-1 text-xs font-semibold leading-snug text-base-content"
                        :aria-current="item.current ? 'page' : undefined"
                    >
                        <span class="line-clamp-2">{{ item.label }}</span>
                    </span>
                </li>
            </template>
        </ol>
    </nav>
</template>
