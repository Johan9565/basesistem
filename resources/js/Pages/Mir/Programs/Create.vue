<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MirBreadcrumb from '@/Components/MirBreadcrumb.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

const crumbs = computed(() => [
    { label: 'Programas MIR', href: route('programs') },
    { label: 'Nuevo programa', current: true },
]);

const form = useForm({
    clave: '',
    nombre: '',
    finalidad: '',
    funcion: '',
    subfuncion: '',
    unidad_responsable: '',
    unidad_administrativa: '',
    actividad_institucional: '',
});

function submit() {
    form.post(route('programs.store'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Nuevo programa MIR" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-4">
                <MirBreadcrumb :items="crumbs" />
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">
                        Alta en colección mir
                    </p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-base-content">
                        Nuevo programa presupuestario
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm text-base-content/65">
                        La clave debe ser única (p. ej. 1.1, 2.3). Luego podrás agregar indicadores y
                        metas desde la matriz MIR.
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
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-base-content">
                            Identificación
                        </h3>
                        <div>
                            <InputLabel for="clave" value="Clave del programa *" />
                            <InputText
                                id="clave"
                                v-model="form.clave"
                                class="mt-1 w-full"
                                placeholder="Ej. 1.1"
                                autocomplete="off"
                            />
                            <InputError class="mt-1" :message="form.errors.clave" />
                        </div>
                        <div>
                            <InputLabel for="nombre" value="Nombre completo *" />
                            <Textarea
                                id="nombre"
                                v-model="form.nombre"
                                class="mt-1 w-full font-sans"
                                rows="3"
                                auto-resize
                                placeholder="Denominación del programa presupuestario"
                            />
                            <InputError class="mt-1" :message="form.errors.nombre" />
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-base-300 pt-6">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-base-content">
                            Clasificación funcional <span class="font-normal text-base-content/50">(opcional)</span>
                        </h3>
                        <div class="grid gap-4 sm:grid-cols-1">
                            <div>
                                <InputLabel for="finalidad" value="Finalidad" />
                                <InputText id="finalidad" v-model="form.finalidad" class="mt-1 w-full" />
                            </div>
                            <div>
                                <InputLabel for="funcion" value="Función" />
                                <InputText id="funcion" v-model="form.funcion" class="mt-1 w-full" />
                            </div>
                            <div>
                                <InputLabel for="subfuncion" value="Subfunción" />
                                <InputText id="subfuncion" v-model="form.subfuncion" class="mt-1 w-full" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-base-300 pt-6">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-base-content">
                            Estructura administrativa <span class="font-normal text-base-content/50">(opcional)</span>
                        </h3>
                        <div>
                            <InputLabel for="unidad_responsable" value="Unidad responsable" />
                            <InputText
                                id="unidad_responsable"
                                v-model="form.unidad_responsable"
                                class="mt-1 w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="unidad_administrativa" value="Unidad administrativa" />
                            <InputText
                                id="unidad_administrativa"
                                v-model="form.unidad_administrativa"
                                class="mt-1 w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="actividad_institucional" value="Actividad institucional" />
                            <InputText
                                id="actividad_institucional"
                                v-model="form.actividad_institucional"
                                class="mt-1 w-full"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 border-t border-base-300 pt-6">
                        <Button
                            type="button"
                            label="Guardar programa"
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
                            @click="router.visit(route('programs'))"
                        />
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
