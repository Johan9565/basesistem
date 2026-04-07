<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MirBreadcrumb from '@/Components/MirBreadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from 'primevue/button';

const props = defineProps({
    programs: {
        type: Array,
        default: () => [],
    },
});

const crumbs = computed(() => [
    { label: 'Programas presupuestarios', current: true },
]);
</script>

<template>
    <Head title="Programas presupuestarios (MIR)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div>
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary"
                    >
                        MIR · Matriz de indicadores para resultados
                    </p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-base-content">
                        Programas presupuestarios
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-base-content/65">
                        Documentos de la colección con estructura de programa, clasificación
                        funcional, unidades responsables y cadena completa de indicadores (Fin →
                        Actividad).
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div
                    class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-base-300 bg-base-100 px-4 py-3 shadow-sm"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/15 text-primary"
                        >
                            <i class="pi pi-folder-open text-lg" aria-hidden="true" />
                        </span>
                        <div>
                            <p class="text-xs font-medium text-base-content/50">Registros en colección</p>
                            <p class="text-lg font-semibold tabular-nums text-base-content">
                                {{ programs.length }}
                                <span class="text-sm font-normal text-base-content/50">programa(s)</span>
                            </p>
                        </div>
                    </div>
                    <Button
                        label="Nuevo programa"
                        icon="pi pi-plus"
                        size="small"
                        outlined
                        @click="router.visit(route('programs.create'))"
                    />
                </div>

                <ul class="space-y-4">
                    <li
                        v-for="p in programs"
                        :key="p.id"
                        class="group rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm transition-shadow hover:shadow-md"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-md bg-primary/12 px-2 py-0.5 font-mono text-xs font-bold text-primary"
                                    >
                                        {{ p.clave || '—' }}
                                    </span>
                                </div>
                                <h3
                                    class="mt-2 text-base font-semibold leading-snug text-base-content group-hover:text-primary"
                                >
                                    {{ p.nombre || 'Sin nombre' }}
                                </h3>
                                <p class="mt-2 text-xs text-base-content/50">
                                    Ficha técnica · Clasificación funcional · Matriz MIR · Metas y
                                    seguimiento
                                </p>
                            </div>
                            <Button
                                label="Abrir ficha"
                                icon="pi pi-arrow-right"
                                icon-pos="right"
                                size="small"
                                class="shrink-0"
                                @click="router.visit(route('programs.show', p.id))"
                            />
                        </div>
                    </li>
                    <li
                        v-if="!programs.length"
                        class="rounded-2xl border border-dashed border-base-300 bg-base-100/50 px-6 py-14 text-center"
                    >
                        <span
                            class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-base-200 text-base-content/40"
                        >
                            <i class="pi pi-inbox text-xl" aria-hidden="true" />
                        </span>
                        <p class="text-sm font-medium text-base-content/70">No hay programas cargados</p>
                        <p class="mx-auto mt-1 max-w-sm text-xs text-base-content/50">
                            Crea uno con el botón
                            <strong class="text-base-content/70">Nuevo programa</strong>
                            o importa documentos en
                            <code class="rounded bg-base-200 px-1 py-0.5 font-mono text-[11px]">mir</code>.
                        </p>
                        <Button
                            label="Crear programa"
                            icon="pi pi-plus"
                            size="small"
                            class="mt-4"
                            @click="router.visit(route('programs.create'))"
                        />
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
