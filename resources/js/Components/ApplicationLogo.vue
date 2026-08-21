<script setup>
import { computed, useAttrs } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: {
        type: String,
        default: 'mark',
        validator: (value) => ['mark', 'wordmark'].includes(value),
    },
});

const attrs = useAttrs();
const page = usePage();

const DEFAULT_MARK = '/images/brand/pa-saber-mark.png';
const DEFAULT_WORDMARK = '/images/brand/pa-saber-wordmark.png';

function resolveBrandingSrc(value) {
    if (!value) return null;
    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:')) return value;
    if (value.startsWith('/')) return value;
    return `/storage/${value}`;
}

const logoUrl = computed(() => {
    const branded = resolveBrandingSrc(page.props?.branding?.logo_url || null);
    if (branded) {
        return branded;
    }

    return props.variant === 'wordmark' ? DEFAULT_WORDMARK : DEFAULT_MARK;
});
</script>

<template>
    <img
        v-bind="attrs"
        :src="logoUrl"
        alt="pa-saber"
        class="object-contain"
    />
</template>
