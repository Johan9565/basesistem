<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MirBreadcrumb from '@/Components/MirBreadcrumb.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    program: { type: Object, required: true },
    indicator: { type: Object, required: true },
});

const form = useForm({
    definicion: props.indicator.definicion ?? '',
    metodo_calculo: props.indicator.metodo_calculo ?? '',
    supuestos: props.indicator.supuestos ?? '',
});

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

const crumbs = computed(() => [
    { label: 'Programas MIR', href: route('programs') },
    { label: props.program.clave || 'Programa', href: route('programs.show', props.program.id) },
    { label: 'Matriz MIR', href: route('indicators.index', props.program.id) },
    { label: props.indicator.codigo, current: true },
]);

function submit() {
    form.patch(
        route('indicators.update', {
            program: props.program.id,
            codigo: props.indicator.codigo,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Editar ${indicator.codigo}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div>
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary"
                    >
                        Definición MIR · {{ indicator.nivel }}
                    </p>
                    <h2 class="mt-1 font-mono text-xl font-bold tracking-tight text-primary">
                        {{ indicator.codigo }}
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-base-content/70">
                        {{ indicator.nombre }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm">
                        <div class="flex items-center gap-2 border-b border-base-300 pb-3">
                            <i class="pi pi-align-left text-primary" aria-hidden="true" />
                            <h3 class="text-sm font-bold text-base-content">Definición</h3>
                        </div>
                        <p class="mt-3 text-xs text-base-content/50">
                            Descripción del indicador y qué mide en el marco del programa.
                        </p>
                        <Textarea
                            v-model="form.definicion"
                            class="mt-3 w-full font-sans"
                            rows="6"
                            auto-resize
                        />
                    </div>

                    <div class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm">
                        <div class="flex items-center gap-2 border-b border-base-300 pb-3">
                            <i class="pi pi-calculator text-secondary" aria-hidden="true" />
                            <h3 class="text-sm font-bold text-base-content">Método de cálculo</h3>
                        </div>
                        <p class="mt-3 text-xs text-base-content/50">
                            Fórmula, variables y reglas para reproducir el indicador.
                        </p>
                        <Textarea
                            v-model="form.metodo_calculo"
                            class="mt-3 w-full font-sans"
                            rows="8"
                            auto-resize
                        />
                    </div>

                    <div class="rounded-2xl border border-base-300 bg-base-100 p-6 shadow-sm">
                        <div class="flex items-center gap-2 border-b border-base-300 pb-3">
                            <i class="pi pi-info-circle text-accent" aria-hidden="true" />
                            <h3 class="text-sm font-bold text-base-content">Supuestos</h3>
                        </div>
                        <p class="mt-3 text-xs text-base-content/50">
                            Condiciones externas necesarias para interpretar metas y resultados.
                        </p>
                        <Textarea
                            v-model="form.supuestos"
                            class="mt-3 w-full font-sans"
                            rows="4"
                            auto-resize
                        />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Button
                            type="button"
                            label="Guardar cambios"
                            icon="pi pi-check"
                            :loading="form.processing"
                            :disabled="form.processing"
                            @click="submit"
                        />
                            <Button
                                label="Volver a la matriz"
                                severity="secondary"
                                text
                                type="button"
                                :disabled="form.processing"
                                @click="router.visit(route('indicators.index', program.id))"
                            />
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
