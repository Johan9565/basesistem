<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MirBreadcrumb from '@/Components/MirBreadcrumb.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    program: { type: Object, required: true },
    year: { type: Number, required: true },
    allowed_years: { type: Array, default: () => [2025, 2026, 2027] },
    rows: { type: Array, default: () => [] },
});

function cellStr(v) {
    if (v === null || v === undefined) return '';
    return String(v);
}

const form = useForm({
    year: props.year,
    rows: props.rows.map((r) => ({
        codigo: r.codigo,
        t1: cellStr(r.alcanzado?.t1),
        t2: cellStr(r.alcanzado?.t2),
        t3: cellStr(r.alcanzado?.t3),
        t4: cellStr(r.alcanzado?.t4),
    })),
});

watch(
    () => props.rows,
    (next) => {
        form.year = props.year;
        form.rows = next.map((r) => ({
            codigo: r.codigo,
            t1: cellStr(r.alcanzado?.t1),
            t2: cellStr(r.alcanzado?.t2),
            t3: cellStr(r.alcanzado?.t3),
            t4: cellStr(r.alcanzado?.t4),
        }));
    },
    { deep: true },
);

const yearOptions = props.allowed_years.map((y) => ({ label: String(y), value: y }));

function onYearChange(v) {
    if (v === props.year) return;
    router.get(
        route('metrics.tracking', { program: props.program.id, year: v }),
        {},
        { preserveScroll: true },
    );
}

const toast = useToast();
const page = usePage();

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

function dotClass(s) {
    if (s === 'green') return 'bg-success';
    if (s === 'yellow') return 'bg-warning';
    if (s === 'red') return 'bg-error';

    return 'bg-base-300';
}

function submit() {
    form.patch(route('metrics.tracking.update', { program: props.program.id }), {
        preserveScroll: true,
    });
}

function downloadMirCsv() {
    window.location.assign(
        route('metrics.export', { program: props.program.id, year: props.year }),
    );
}

const crumbs = computed(() => [
    { label: 'Programas MIR', href: route('programs') },
    { label: props.program.clave || 'Programa', href: route('programs.show', props.program.id) },
    { label: 'Matriz MIR', href: route('indicators.index', props.program.id) },
    { label: `Seguimiento ${props.year}`, current: true },
]);
</script>

<template>
    <Head :title="`Seguimiento MIR ${year}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent"
                        >
                            Avance real vs programado
                        </p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-base-content">
                            Seguimiento trimestral · {{ program.clave }}
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-base-content/65">
                            Registra el valor alcanzado por trimestre. El semáforo compara alcanzado
                            frente a lo programado (mismas celdas que en la hoja de metas o en
                            <code class="rounded bg-base-200 px-1 font-mono text-[11px]"
                                >seguimiento_{{ year }}</code
                            >).
                        </p>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-40">
                            <label
                                class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-base-content/45"
                            >
                                Ejercicio
                            </label>
                            <Select
                                :model-value="year"
                                :options="yearOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                @update:model-value="onYearChange"
                            />
                        </div>
                        <Button
                            type="button"
                            label="Guardar avances"
                            icon="pi pi-save"
                            :loading="form.processing"
                            :disabled="form.processing"
                            @click="submit"
                        />
                        <Button
                            label="CSV"
                            icon="pi pi-download"
                            size="small"
                            outlined
                            type="button"
                            @click="downloadMirCsv"
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[100rem] sm:px-6 lg:px-8">
                <div
                    class="mb-4 grid gap-3 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 h-3 w-3 shrink-0 rounded-full bg-success" />
                        <div>
                            <p class="text-xs font-bold text-base-content">Verde</p>
                            <p class="text-[11px] leading-snug text-base-content/55">≥ 90 % cumplimiento</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 h-3 w-3 shrink-0 rounded-full bg-warning" />
                        <div>
                            <p class="text-xs font-bold text-base-content">Amarillo</p>
                            <p class="text-[11px] leading-snug text-base-content/55">≥ 70 % y &lt; 90 %</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 h-3 w-3 shrink-0 rounded-full bg-error" />
                        <div>
                            <p class="text-xs font-bold text-base-content">Rojo</p>
                            <p class="text-[11px] leading-snug text-base-content/55">&lt; 70 %</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 h-3 w-3 shrink-0 rounded-full bg-base-300" />
                        <div>
                            <p class="text-xs font-bold text-base-content">Gris</p>
                            <p class="text-[11px] leading-snug text-base-content/55">
                                Sin dato o programado en cero
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm"
                >
                    <div
                        class="border-b border-base-300 bg-base-200/50 px-4 py-2 text-[11px] font-bold uppercase tracking-wide text-base-content/50"
                    >
                        Seguimiento · {{ rows.length }} indicadores · puntos T1→T4
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr
                                    class="border-b border-base-300 bg-base-200/70 text-left text-[11px] font-bold uppercase tracking-wide text-base-content/55"
                                >
                                    <th
                                        class="sticky left-0 z-10 min-w-28 bg-base-200/90 px-2 py-2 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]"
                                    >
                                        Código
                                    </th>
                                    <th class="min-w-48 px-2 py-2">Indicador</th>
                                    <th class="min-w-20 px-2 py-2 text-center">Semáforo</th>
                                    <th class="bg-base-300/25 px-2 py-2 text-center" colspan="4">
                                        Programado
                                    </th>
                                    <th class="bg-primary/10 px-2 py-2 text-center text-primary" colspan="4">
                                        Alcanzado (editable)
                                    </th>
                                </tr>
                                <tr
                                    class="border-b border-base-300 bg-base-200/40 text-[10px] font-semibold uppercase text-base-content/45"
                                >
                                    <th class="sticky left-0 z-10 bg-base-200/90" />
                                    <th />
                                    <th class="text-center">T1–T4</th>
                                    <th class="px-1 py-1">T1</th>
                                    <th class="px-1 py-1">T2</th>
                                    <th class="px-1 py-1">T3</th>
                                    <th class="px-1 py-1">T4</th>
                                    <th class="bg-primary/5 px-1 py-1">T1</th>
                                    <th class="bg-primary/5 px-1 py-1">T2</th>
                                    <th class="bg-primary/5 px-1 py-1">T3</th>
                                    <th class="bg-primary/5 px-1 py-1">T4</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(fr, i) in form.rows"
                                    :key="fr.codigo"
                                    class="border-b border-base-300 hover:bg-base-200/15"
                                >
                                    <td
                                        class="sticky left-0 z-10 bg-base-100 px-2 py-2 font-mono text-xs font-bold text-primary shadow-[2px_0_4px_-2px_rgba(0,0,0,0.06)]"
                                    >
                                        {{ fr.codigo }}
                                    </td>
                                    <td class="max-w-xs px-2 py-2 text-xs leading-snug text-base-content/80">
                                        {{ rows[i]?.nombre }}
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="flex justify-center gap-1">
                                            <span
                                                class="inline-block h-2.5 w-2.5 rounded-full"
                                                :class="dotClass(rows[i]?.semaforos?.t1)"
                                                title="T1"
                                            />
                                            <span
                                                class="inline-block h-2.5 w-2.5 rounded-full"
                                                :class="dotClass(rows[i]?.semaforos?.t2)"
                                                title="T2"
                                            />
                                            <span
                                                class="inline-block h-2.5 w-2.5 rounded-full"
                                                :class="dotClass(rows[i]?.semaforos?.t3)"
                                                title="T3"
                                            />
                                            <span
                                                class="inline-block h-2.5 w-2.5 rounded-full"
                                                :class="dotClass(rows[i]?.semaforos?.t4)"
                                                title="T4"
                                            />
                                        </div>
                                    </td>
                                    <td class="bg-base-200/20 px-1 py-1.5 text-xs text-base-content/70">
                                        {{ rows[i]?.programado?.t1 ?? '—' }}
                                    </td>
                                    <td class="bg-base-200/20 px-1 py-1.5 text-xs text-base-content/70">
                                        {{ rows[i]?.programado?.t2 ?? '—' }}
                                    </td>
                                    <td class="bg-base-200/20 px-1 py-1.5 text-xs text-base-content/70">
                                        {{ rows[i]?.programado?.t3 ?? '—' }}
                                    </td>
                                    <td class="bg-base-200/20 px-1 py-1.5 text-xs text-base-content/70">
                                        {{ rows[i]?.programado?.t4 ?? '—' }}
                                    </td>
                                    <td class="bg-primary/5 px-1 py-1">
                                        <InputText v-model="fr.t1" class="w-full min-w-16 text-sm" />
                                    </td>
                                    <td class="bg-primary/5 px-1 py-1">
                                        <InputText v-model="fr.t2" class="w-full min-w-16 text-sm" />
                                    </td>
                                    <td class="bg-primary/5 px-1 py-1">
                                        <InputText v-model="fr.t3" class="w-full min-w-16 text-sm" />
                                    </td>
                                    <td class="bg-primary/5 px-1 py-1">
                                        <InputText v-model="fr.t4" class="w-full min-w-16 text-sm" />
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
