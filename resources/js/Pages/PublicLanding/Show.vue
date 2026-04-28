<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    portfolio: { type: Object, required: true },
    owner: { type: Object, required: true },
});

function renderHtml() {
    const st = props.portfolio?.builder_state;
    const comps = Array.isArray(st?.components) ? st.components : [];
    return comps.map((c) => c?.html_code ?? '').join('');
}
</script>

<template>
    <GuestLayout>
        <Head :title="portfolio?.slug ? `Portafolio - ${portfolio.slug}` : 'Portafolio'" />
        <div id="pagebuilder" class="bg-base-100">
            <div v-html="renderHtml()" />
        </div>
    </GuestLayout>
</template>

