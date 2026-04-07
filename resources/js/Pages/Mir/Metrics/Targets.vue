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

const form = useForm({
    rows: props.rows.map((r) => ({
        codigo: r.codigo,
        anual: r.anual ?? '',
        t1: r.t1 ?? '',
        t2: r.t2 ?? '',
        t3: r.t3 ?? '',
        t4: r.t4 ?? '',
    })),
});

watch(
    () => props.rows,
    (next) => {
        form.rows = next.map((r) => ({
            codigo: r.codigo,
            anual: r.anual ?? '',
            t1: r.t1 ?? '',
            t2: r.t2 ?? '',
            t3: r.t3 ?? '',
            t4: r.t4 ?? '',
        }));
    },
    { deep: true },
);

const yearOptions = props.allowed_years.map((y) => ({ label: String(y), value: y }));

function onYearChange(v) {
    if (v === props.year) return;
    router.get(
        route('metrics.targets', { program: props.program.id, year: v }),
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
            severity: f.type === 'error' ? 'error' : f.type === 'success' ? 'success' : 'info',
            summary: f.message,
            life: 5000,
        });
    }
});

function submit() {
    form.patch(
        route('metrics.targets.update', {
            program: props.program.id,
            year: props.year,
        }),
        { preserveScroll: true },
    );
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
    { label: `Metas ${props.year}`, current: true },
]);

const yearAddForm = useForm({ year: props.year });

watch(
    () => props.year,
    (y) => {
        yearAddForm.year = y;
    },
);

function submitNewYear() {
    yearAddForm.post(route('metrics.years.store', props.program.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Metas MIR ${year}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold uppercase tracking-[0.2em] text-secondary"
                        >
                            Programación anual y trimestral
                        </p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-base-content">
                            Hoja de metas · {{ program.clave }}
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-base-content/65">
                            Captura la meta anual y la distribución por trimestre (T1–T4) para cada
                            indicador del programa. Los valores se guardan en
                            <code class="rounded bg-base-200 px-1 font-mono text-[11px]">metas</code>
                            por ejercicio fiscal.
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
                            label="Guardar"
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
                    class="mb-4 flex flex-col gap-4 rounded-xl border border-base-300 bg-base-200/30 p-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between"
                >
                    <div class="flex flex-wrap gap-3 text-xs text-base-content/65">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-primary" />
                            <strong class="text-base-content">Anual</strong>
                            meta del año completo
                        </span>
                        <span class="text-base-content/30">|</span>
                        <span
                            ><strong class="text-base-content">T1–T4</strong> programación por
                            trimestre</span
                        >
                    </div>
                    <div
                        class="flex flex-wrap items-end gap-2 border-t border-base-300 pt-4 sm:border-t-0 sm:pt-0"
                    >
                        <div class="w-36">
                            <label
                                class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-base-content/45"
                            >
                                Agregar ejercicio
                            </label>
                            <Select
                                v-model="yearAddForm.year"
                                :options="allowed_years.map((x) => ({ label: String(x), value: x }))"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                        </div>
                        <Button
                            label="Añadir a indicadores"
                            icon="pi pi-plus"
                            size="small"
                            severity="secondary"
                            type="button"
                            :loading="yearAddForm.processing"
                            :disabled="yearAddForm.processing"
                            @click="submitNewYear"
                        />
                    </div>
                </div>

                <form @submit.prevent="submit">
                    <div
                        class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm"
                    >
                        <div
                            class="border-b border-base-300 bg-base-200/50 px-4 py-2 text-[11px] font-bold uppercase tracking-wide text-base-content/50"
                        >
                            Indicadores · {{ rows.length }} filas
                        </div>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr
                                        class="border-b border-base-300 bg-base-200/70 text-left text-[11px] font-bold uppercase tracking-wide text-base-content/55"
                                    >
                                        <th
                                            class="sticky left-0 z-10 min-w-32 bg-base-200/90 px-3 py-2.5 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]"
                                        >
                                            Código
                                        </th>
                                        <th class="min-w-48 px-3 py-2.5">Indicador</th>
                                        <th
                                            class="bg-primary/10 px-2 py-2.5 text-center text-primary"
                                            colspan="1"
                                        >
                                            Meta anual
                                        </th>
                                        <th class="bg-secondary/10 px-2 py-2.5 text-center" colspan="4">
                                            <span class="text-secondary">Trimestres programados</span>
                                        </th>
                                    </tr>
                                    <tr
                                        class="border-b border-base-300 bg-base-200/40 text-[10px] font-semibold uppercase text-base-content/45"
                                    >
                                        <th class="sticky left-0 z-10 bg-base-200/90" />
                                        <th />
                                        <th class="px-2 py-1">Anual</th>
                                        <th class="px-2 py-1">T1</th>
                                        <th class="px-2 py-1">T2</th>
                                        <th class="px-2 py-1">T3</th>
                                        <th class="px-2 py-1">T4</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, i) in form.rows"
                                        :key="row.codigo"
                                        class="border-b border-base-300 hover:bg-base-200/20"
                                    >
                                        <td
                                            class="sticky left-0 z-10 bg-base-100 px-3 py-2 font-mono text-xs font-bold text-primary shadow-[2px_0_4px_-2px_rgba(0,0,0,0.06)]"
                                        >
                                            {{ row.codigo }}
                                        </td>
                                        <td class="max-w-xs px-3 py-2 text-xs leading-snug text-base-content/80">
                                            {{ rows[i]?.nombre }}
                                        </td>
                                        <td class="bg-primary/5 px-2 py-1.5">
                                            <InputText v-model="row.anual" class="w-full text-sm" />
                                        </td>
                                        <td class="bg-secondary/5 px-2 py-1.5">
                                            <InputText v-model="row.t1" class="w-full text-sm" />
                                        </td>
                                        <td class="bg-secondary/5 px-2 py-1.5">
                                            <InputText v-model="row.t2" class="w-full text-sm" />
                                        </td>
                                        <td class="bg-secondary/5 px-2 py-1.5">
                                            <InputText v-model="row.t3" class="w-full text-sm" />
                                        </td>
                                        <td class="bg-secondary/5 px-2 py-1.5">
                                            <InputText v-model="row.t4" class="w-full text-sm" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
