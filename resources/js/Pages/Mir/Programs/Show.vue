<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MirBreadcrumb from '@/Components/MirBreadcrumb.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from 'primevue/button';

const props = defineProps({
    program: {
        type: Object,
        required: true,
    },
    global_progress_percent: {
        type: [Number, null],
        default: null,
    },
    global_progress_year: {
        type: Number,
        default: () => new Date().getFullYear(),
    },
});

const y = new Date().getFullYear();
const metricYear = [2025, 2026, 2027].includes(y) ? y : 2026;

const cf = props.program.clasificacion_funcional ?? {};
const ea = props.program.estructura_administrativa ?? {};

const crumbs = computed(() => [
    { label: 'Programas MIR', href: route('programs') },
    {
        label: `${props.program.clave || 'Programa'} · Ficha técnica`,
        current: true,
    },
]);

function exportMirCsv() {
    window.location.assign(
        route('metrics.export', { program: props.program.id, year: metricYear }),
    );
}
</script>

<template>
    <Head :title="`MIR — ${program.clave || program.nombre}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary"
                        >
                            Programa presupuestario · PMD
                        </p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-base-content">
                            {{ program.clave }}
                            <span class="text-base font-semibold text-base-content/40">·</span>
                            <span class="text-lg font-semibold text-base-content/90">
                                Ficha técnica
                            </span>
                        </h2>
                        <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-base-content/65">
                            {{ program.nombre }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-2xl border border-base-300 bg-gradient-to-br from-base-100 to-base-200/40 p-6 shadow-sm"
                >
                    <div class="flex flex-wrap items-start gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-2xl text-primary"
                        >
                            <i class="pi pi-chart-bar" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-base-content">Resumen del programa</h3>
                            <p class="mt-2 text-sm leading-relaxed text-base-content/65">
                                Datos de enlace con el Plan Municipal de Desarrollo: finalidad,
                                función, subfunción y la estructura administrativa de la unidad
                                responsable.
                            </p>
                            <div class="mt-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-base-content/45">
                                    Avance indicador de fin · {{ global_progress_year }}
                                </p>
                                <div v-if="global_progress_percent != null" class="mt-2">
                                    <div class="mb-1 flex items-baseline justify-between gap-2">
                                        <span class="text-2xl font-bold tabular-nums text-base-content">
                                            {{ global_progress_percent }}%
                                        </span>
                                        <span class="text-xs text-base-content/50"
                                            >respecto a metas trimestrales</span
                                        >
                                    </div>
                                    <progress
                                        class="progress progress-primary h-2.5 w-full rounded-full"
                                        :value="Math.min(100, global_progress_percent)"
                                        max="100"
                                    />
                                </div>
                                <p v-else class="mt-2 text-sm text-base-content/55">
                                    Aún no hay seguimiento trimestral suficiente para calcular el
                                    avance global del indicador de fin.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div
                        class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm"
                    >
                        <div class="flex items-center gap-2 border-b border-base-300 pb-3">
                            <i class="pi pi-building text-primary" aria-hidden="true" />
                            <h4 class="text-sm font-bold uppercase tracking-wide text-base-content">
                                Estructura administrativa
                            </h4>
                        </div>
                        <dl class="mt-4 space-y-4 text-sm">
                            <div>
                                <dt class="text-xs font-medium text-base-content/45">
                                    Unidad responsable
                                </dt>
                                <dd class="mt-0.5 leading-snug text-base-content">
                                    {{ ea.unidad_responsable || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-base-content/45">
                                    Unidad administrativa
                                </dt>
                                <dd class="mt-0.5 leading-snug text-base-content">
                                    {{ ea.unidad_administrativa || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-base-content/45">
                                    Actividad institucional
                                </dt>
                                <dd class="mt-0.5 leading-snug text-base-content">
                                    {{ ea.actividad_institucional || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div
                        class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm"
                    >
                        <div class="flex items-center gap-2 border-b border-base-300 pb-3">
                            <i class="pi pi-sitemap text-secondary" aria-hidden="true" />
                            <h4 class="text-sm font-bold uppercase tracking-wide text-base-content">
                                Clasificación funcional
                            </h4>
                        </div>
                        <dl class="mt-4 space-y-4 text-sm">
                            <div>
                                <dt class="text-xs font-medium text-base-content/45">Finalidad</dt>
                                <dd class="mt-0.5 leading-snug text-base-content">
                                    {{ cf.finalidad || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-base-content/45">Función</dt>
                                <dd class="mt-0.5 leading-snug text-base-content">
                                    {{ cf.funcion || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-base-content/45">Subfunción</dt>
                                <dd class="mt-0.5 leading-snug text-base-content">
                                    {{ cf.subfuncion || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm">
                    <div class="flex items-center gap-2 border-b border-base-300 pb-3">
                        <i class="pi pi-compass text-accent" aria-hidden="true" />
                        <h4 class="text-sm font-bold uppercase tracking-wide text-base-content">
                            Objetivo e indicador de fin
                        </h4>
                    </div>
                    <p
                        v-if="program.fin_indicador_nombre"
                        class="mt-4 text-sm font-semibold text-base-content"
                    >
                        {{ program.fin_indicador_nombre }}
                    </p>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-base-content/75">
                        {{ program.fin_objetivo || '—' }}
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2 rounded-xl border border-base-300 bg-base-200/30 p-4"
                >
                    <Button
                        label="Matriz MIR"
                        icon="pi pi-table"
                        size="small"
                        @click="router.visit(route('indicators.index', program.id))"
                    />
                    <Button
                        label="Agregar indicador"
                        icon="pi pi-plus"
                        size="small"
                        outlined
                        @click="router.visit(route('indicators.create', program.id))"
                    />
                    <Button
                        label="Programar metas"
                        icon="pi pi-pencil"
                        size="small"
                        severity="secondary"
                        @click="
                            router.visit(
                                route('metrics.targets', {
                                    program: program.id,
                                    year: metricYear,
                                }),
                            )
                        "
                    />
                    <Button
                        label="Seguimiento trimestral"
                        icon="pi pi-chart-line"
                        size="small"
                        severity="secondary"
                        @click="
                            router.visit(
                                route('metrics.tracking', {
                                    program: program.id,
                                    year: metricYear,
                                }),
                            )
                        "
                    />
                    <Button
                        label="Exportar CSV"
                        icon="pi pi-download"
                        size="small"
                        severity="help"
                        @click="exportMirCsv"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
