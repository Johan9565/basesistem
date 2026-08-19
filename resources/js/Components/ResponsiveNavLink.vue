<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full rounded-xl bg-[#ddd6ff] px-4 py-2 text-start text-sm font-semibold text-[#17141f]'
        : 'block w-full rounded-xl px-4 py-2 text-start text-sm font-medium text-[#5c5668] hover:bg-white hover:text-[#17141f]',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
