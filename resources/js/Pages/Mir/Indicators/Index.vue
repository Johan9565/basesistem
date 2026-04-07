<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MirBreadcrumb from '@/Components/MirBreadcrumb.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import Select from 'primevue/select';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    program: { type: Object, required: true },
    indicators: { type: Array, default: () => [] },
    metric_years: { type: Array, default: () => [] },
});

const yearsList = computed(() =>
    props.metric_years?.length ? [...props.metric_years] : [2025, 2026, 2027],
);

const yearOptions = computed(() =>
    yearsList.value.map((yr) => ({ label: String(yr), value: yr })),
);

const selectedYear = ref(2026);

watch(
    yearsList,
    (L) => {
        if (!L.length) {
            return;
        }
        if (L.includes(selectedYear.value)) {
            return;
        }
        const cy = new Date().getFullYear();
        if (L.includes(cy)) {
            selectedYear.value = cy;
        } else if (L.includes(2026)) {
            selectedYear.value = 2026;
        } else {
            selectedYear.value = L[0];
        }
    },
    { immediate: true },
);

const nivelOrder = { Fin: 0, Proposito: 1, Componente: 2, Actividad: 3 };

const page = usePage();
const toast = useToast();

onMounted(() => {
    const f = page.props.flash;
    if (f?.message) {
        toast.add({
            severity: f.type === 'success' ? 'success' : 'info',
            summary: f.message,
            life: 4500,
        });
    }
});

const sorted = computed(() =>
    [...props.indicators].sort((a, b) => {
        const na = nivelOrder[a.nivel] ?? 99;
        const nb = nivelOrder[b.nivel] ?? 99;
        if (na !== nb) return na - nb;
        return (a.codigo || '').localeCompare(b.codigo || '');
    }),
);

const crumbs = computed(() => [
    { label: 'Programas MIR', href: route('programs') },
    { label: props.program.clave || 'Programa', href: route('programs.show', props.program.id) },
    { label: 'Matriz de indicadores', current: true },
]);

function nivelRowClass(nivel) {
    const map = {
        Fin: 'border-l-4 border-l-primary',
        Proposito: 'border-l-4 border-l-secondary',
        Componente: 'border-l-4 border-l-accent',
        Actividad: 'border-l-4 border-l-base-300',
    };
    return map[nivel] ?? 'border-l-4 border-l-base-300';
}

function nivelBadgeClass(nivel) {
    const map = {
        Fin: 'badge-primary',
        Proposito: 'badge-secondary',
        Componente: 'badge-accent',
        Actividad: 'badge-ghost border border-base-300',
    };
    return map[nivel] ?? 'badge-ghost border border-base-300';
}
</script>

<template>
    <Head :title="`MIR — Indicadores ${program.clave}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary"
                        >
                            MIR · {{ program.clave }}
                        </p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-base-content">
                            Matriz de indicadores
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-base-content/65">
                            Cadena Fin, Propósito, Componente y Actividad. Las metas y el seguimiento
                            son comunes a todo el programa: elige el ejercicio y abre la hoja
                            correspondiente.
                        </p>
                    </div>
                    <div
                        class="flex flex-col gap-3 rounded-xl border border-base-300 bg-base-200/25 p-4 sm:flex-row sm:items-end"
                    >
                        <div class="w-full min-w-[9rem] sm:w-36">
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-base-content/45">
                                Ejercicio fiscal
                            </label>
                            <Select
                                v-model="selectedYear"
                                :options="yearOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                label="Agregar indicador"
                                icon="pi pi-plus"
                                size="small"
                                @click="router.visit(route('indicators.create', program.id))"
                            />
                            <Button
                                label="Hoja de metas"
                                icon="pi pi-table"
                                size="small"
                                severity="secondary"
                                @click="
                                    router.visit(
                                        route('metrics.targets', {
                                            program: program.id,
                                            year: selectedYear,
                                        }),
                                    )
                                "
                            />
                            <Button
                                label="Seguimiento"
                                icon="pi pi-chart-line"
                                size="small"
                                severity="help"
                                @click="
                                    router.visit(
                                        route('metrics.tracking', {
                                            program: program.id,
                                            year: selectedYear,
                                        }),
                                    )
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[100rem] sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 bg-base-200/40 px-4 py-3"
                    >
                        <div class="flex items-center gap-2 text-sm text-base-content/70">
                            <i class="pi pi-list-check text-primary" aria-hidden="true" />
                            <span
                                ><span class="font-semibold text-base-content">{{ sorted.length }}</span>
                                indicadores</span
                            >
                        </div>
                        <p class="text-xs text-base-content/45">
                            Columna derecha: solo edición de definición por indicador
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr
                                    class="border-b border-base-300 bg-base-200/60 text-left text-[11px] font-bold uppercase tracking-wide text-base-content/55"
                                >
                                    <th class="w-0 px-3 py-3">Nivel</th>
                                    <th class="px-3 py-3">Código</th>
                                    <th class="min-w-[12rem] px-3 py-3">Indicador</th>
                                    <th class="whitespace-nowrap px-3 py-3">Frecuencia</th>
                                    <th class="px-3 py-3 text-end">Definición MIR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="ind in sorted"
                                    :key="ind.codigo"
                                    :class="[
                                        'border-b border-base-300 transition-colors hover:bg-base-200/25',
                                        nivelRowClass(ind.nivel),
                                    ]"
                                >
                                    <td class="whitespace-nowrap px-3 py-2.5 align-middle">
                                        <span
                                            class="badge badge-sm font-medium"
                                            :class="nivelBadgeClass(ind.nivel)"
                                        >
                                            {{ ind.nivel }}
                                        </span>
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-3 py-2.5 align-middle font-mono text-xs font-bold text-primary"
                                    >
                                        {{ ind.codigo }}
                                    </td>
                                    <td class="max-w-md px-3 py-2.5 align-middle text-sm leading-snug text-base-content">
                                        {{ ind.nombre }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-3 py-2.5 align-middle text-xs text-base-content/60"
                                    >
                                        {{ ind.frecuencia }}
                                    </td>
                                    <td class="px-3 py-2.5 align-middle text-end">
                                        <Button
                                            label="Editar definición"
                                            icon="pi pi-file-edit"
                                            size="small"
                                            @click="
                                                router.visit(
                                                    route('indicators.edit', {
                                                        program: program.id,
                                                        codigo: ind.codigo,
                                                    }),
                                                )
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
