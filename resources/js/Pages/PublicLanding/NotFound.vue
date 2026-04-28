<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    slug: { type: String, default: '' },
});
</script>

<template>
    <GuestLayout>
        <Head title="Portafolio no encontrado" />

        <div class="max-w-3xl mx-auto px-6 py-16">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <h1 class="text-2xl font-semibold">Esta página no existe</h1>
                    <p class="mt-2 opacity-70">
                        No encontramos un usuario con el slug
                        <span class="font-mono">{{ slug || '—' }}</span>.
                    </p>
                    <div class="mt-4">
                        <a class="btn btn-primary" href="/">Ir al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

