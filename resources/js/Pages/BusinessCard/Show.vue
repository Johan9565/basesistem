<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    slug: { type: String, default: '' },
    publicUrl: { type: String, default: null },
    businessCardUrl: { type: String, default: null },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tarjeta de negocio" />

        <div class="max-w-3xl mx-auto px-4 py-6">
            <h1 class="text-2xl font-semibold mb-2">Tarjeta de negocio</h1>
            <p class="text-sm opacity-70 mb-6">
                Este módulo apunta a tu landing pública por <code>slug</code>. Si aún no tienes slug, configúralo en “Mi página”.
            </p>

            <div class="card bg-base-100 shadow">
                <div class="card-body gap-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <div class="text-sm opacity-70">Slug</div>
                            <div class="font-mono break-all">{{ slug || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-sm opacity-70">URL pública</div>
                            <a v-if="publicUrl" class="link break-all" :href="publicUrl" target="_blank" rel="noreferrer">{{ publicUrl }}</a>
                            <div v-else class="opacity-70">—</div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm opacity-70">URL corta (para imprimir / QR)</div>
                        <a v-if="businessCardUrl" class="link break-all" :href="businessCardUrl" target="_blank" rel="noreferrer">{{ businessCardUrl }}</a>
                        <div v-else class="opacity-70">—</div>
                    </div>

                    <div class="alert alert-info">
                        <div class="text-sm">
                            Si quieres, el QR lo podemos generar en el frontend (JS) o en backend (PHP) cuando definas el diseño final de la tarjeta.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

