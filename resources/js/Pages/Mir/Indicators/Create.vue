<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MirBreadcrumb from '@/Components/MirBreadcrumb.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import PrimeCheckbox from 'primevue/checkbox';
import Button from 'primevue/button';

const props = defineProps({
    program: { type: Object, required: true },
    niveles: { type: Array, default: () => [] },
    default_meta_years: { type: Array, default: () => [2025, 2026, 2027] },
});

const nivelOptions = computed(() =>
    (props.niveles || []).map((n) => ({ label: n, value: n })),
);

const frecuenciaOptions = [
    { label: 'Anual', value: 'Anual' },
    { label: 'Trimestral', value: 'Trimestral' },
    { label: 'Semestral', value: 'Semestral' },
    { label: 'Trianual', value: 'Trianual' },
    { label: 'Otra / especificar en notas', value: 'Otra' },
];

const sentidoOptions = [
    { label: 'Ascendente', value: 'Ascendente' },
    { label: 'Descendente', value: 'Descendente' },
];

const crumbs = computed(() => [
    { label: 'Programas MIR', href: route('programs') },
    { label: props.program.clave || 'Programa', href: route('programs.show', props.program.id) },
    { label: 'Matriz MIR', href: route('indicators.index', props.program.id) },
    { label: 'Nuevo indicador', current: true },
]);

const form = useForm({
    nivel: 'Actividad',
    codigo: '',
    nombre: '',
    objetivo: '',
    frecuencia: 'Trimestral',
    definicion: '',
    dimension: 'Eficacia',
    unidad_medida: '',
    sentido: 'Ascendente',
    linea_base: '',
    medios_verificacion: '',
    supuestos: '',
    metodo_calculo: '',
    inicializar_metas: true,
});

function submit() {
    form.post(route('indicators.store', props.program.id), { preserveScroll: true });
}

const yearsHint = computed(() => (props.default_meta_years || []).join(', '));
</script>

<template>
    <Head title="Nuevo indicador MIR" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">
                        Programa {{ program.clave }}
                    </p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-base-content">
                        Nuevo indicador
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm text-base-content/65">
                        El código debe ser único dentro del programa. Opcionalmente se crean filas de
                        metas vacías para los ejercicios
                        {{ yearsHint }}.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="space-y-8 rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="nivel" value="Nivel *" />
                            <Select
                                id="nivel"
                                v-model="form.nivel"
                                :options="nivelOptions"
                                option-label="label"
                                option-value="value"
                                class="mt-1 w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.nivel" />
                        </div>
                        <div>
                            <InputLabel for="codigo" value="Código *" />
                            <InputText
                                id="codigo"
                                v-model="form.codigo"
                                class="mt-1 w-full font-mono"
                                placeholder="Ej. PAVCySRC"
                                autocomplete="off"
                            />
                            <InputError class="mt-1" :message="form.errors.codigo" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="nombre" value="Nombre del indicador *" />
                        <Textarea
                            id="nombre"
                            v-model="form.nombre"
                            class="mt-1 w-full font-sans"
                            rows="2"
                            auto-resize
                        />
                        <InputError class="mt-1" :message="form.errors.nombre" />
                    </div>

                    <div>
                        <InputLabel for="objetivo" value="Objetivo / alineación *" />
                        <Textarea
                            id="objetivo"
                            v-model="form.objetivo"
                            class="mt-1 w-full font-sans"
                            rows="4"
                            auto-resize
                            placeholder="Objetivo del indicador en la cadena de resultados"
                        />
                        <InputError class="mt-1" :message="form.errors.objetivo" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="frecuencia" value="Frecuencia de medición *" />
                            <Select
                                id="frecuencia"
                                v-model="form.frecuencia"
                                :options="frecuenciaOptions"
                                option-label="label"
                                option-value="value"
                                class="mt-1 w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.frecuencia" />
                        </div>
                        <div>
                            <InputLabel for="sentido" value="Sentido" />
                            <Select
                                id="sentido"
                                v-model="form.sentido"
                                :options="sentidoOptions"
                                option-label="label"
                                option-value="value"
                                class="mt-1 w-full"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="dimension" value="Dimensión" />
                            <InputText id="dimension" v-model="form.dimension" class="mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel for="unidad_medida" value="Unidad de medida" />
                            <InputText id="unidad_medida" v-model="form.unidad_medida" class="mt-1 w-full" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="definicion" value="Definición" />
                        <Textarea
                            id="definicion"
                            v-model="form.definicion"
                            class="mt-1 w-full font-sans"
                            rows="4"
                            auto-resize
                        />
                    </div>

                    <div>
                        <InputLabel for="metodo_calculo" value="Método de cálculo" />
                        <Textarea
                            id="metodo_calculo"
                            v-model="form.metodo_calculo"
                            class="mt-1 w-full font-sans"
                            rows="4"
                            auto-resize
                        />
                    </div>

                    <div>
                        <InputLabel for="linea_base" value="Línea base" />
                        <Textarea
                            id="linea_base"
                            v-model="form.linea_base"
                            class="mt-1 w-full font-sans"
                            rows="2"
                            auto-resize
                        />
                    </div>

                    <div>
                        <InputLabel for="medios_verificacion" value="Medios de verificación" />
                        <Textarea
                            id="medios_verificacion"
                            v-model="form.medios_verificacion"
                            class="mt-1 w-full font-sans"
                            rows="3"
                            auto-resize
                        />
                    </div>

                    <div>
                        <InputLabel for="supuestos" value="Supuestos" />
                        <Textarea
                            id="supuestos"
                            v-model="form.supuestos"
                            class="mt-1 w-full font-sans"
                            rows="2"
                            auto-resize
                        />
                    </div>

                    <div
                        class="flex items-start gap-3 rounded-xl border border-base-300 bg-base-200/30 p-4"
                    >
                        <PrimeCheckbox v-model="form.inicializar_metas" binary input-id="inicializar_metas" />
                        <div>
                            <label class="cursor-pointer text-sm font-medium text-base-content" for="inicializar_metas">
                                Inicializar metas ({{ yearsHint }})
                            </label>
                            <p class="mt-1 text-xs text-base-content/55">
                                Crea entradas vacías en
                                <code class="rounded bg-base-200 px-1 font-mono text-[11px]">metas</code>
                                para capturar valores en la hoja de metas.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 border-t border-base-300 pt-6">
                        <Button
                            type="button"
                            label="Guardar indicador"
                            icon="pi pi-check"
                            :loading="form.processing"
                            :disabled="form.processing"
                            @click="submit"
                        />
                        <Button
                            label="Cancelar"
                            severity="secondary"
                            text
                            type="button"
                            @click="router.visit(route('indicators.index', program.id))"
                        />
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
