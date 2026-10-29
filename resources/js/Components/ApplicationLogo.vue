<script setup>
import { computed, useAttrs } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
const page = usePage();
function resolveBrandingSrc(value) {
    if (!value) return null;
    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:')) return value;
    if (value.startsWith('/')) return value;
    return `/storage/${value}`;
}

const logoUrl = computed(() => resolveBrandingSrc(page.props?.branding?.logo_url || null));
</script>

<template>
    <img
        :src="logoUrl || '/images/logo_gestiondesk.png'"
        v-bind="attrs"
        alt="GestionDesk Logo"
        class="object-contain"
    />
</template>
