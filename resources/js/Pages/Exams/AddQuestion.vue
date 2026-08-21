<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    exam: {
        type: Object,
        required: true,
    },
});

const optionLabels = ['A', 'B', 'C', 'D', 'E', 'F'];

const form = useForm({
    tipo: 'opcion_unica',
    pregunta: '',
    materia: props.exam.subjects?.[0] ?? '',
    opciones: ['', '', '', ''],
    correctas: [],
    respuesta_correcta: '',
    respuesta_modelo: '',
    criterios: '',
});

const isOpen = computed(() => form.tipo === 'abierta');
const isMultiple = computed(() => form.tipo === 'opcion_multiple');

function toggleCorrect(index) {
    if (form.tipo === 'opcion_unica') {
        form.correctas = [index];
        return;
    }

    const set = new Set(form.correctas);
    if (set.has(index)) {
        set.delete(index);
    } else {
        set.add(index);
    }
    form.correctas = [...set].sort((a, b) => a - b);
}

function isCorrect(index) {
    return form.correctas.includes(index);
}

function addOption() {
    if (form.opciones.length >= 6) return;
    form.opciones.push('');
}

function removeOption(index) {
    if (form.opciones.length <= 2) return;
    form.opciones.splice(index, 1);
    form.correctas = form.correctas
        .filter((i) => i !== index)
        .map((i) => (i > index ? i - 1 : i));
}

function onTipoChange() {
    form.correctas = [];
    if (form.tipo === 'abierta') {
        form.opciones = [];
    } else if (form.opciones.length < 2) {
        form.opciones = ['', '', '', ''];
    }
}

function submit() {
    form.post(route('exams.questions.store', props.exam.id));
}
</script>

<template>
    <Head :title="`Agregar pregunta · ${exam.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('exams.show', exam.id)" class="ps-btn-ghost">
                ← {{ exam.name }}
            </Link>
            <h2 class="mt-5 text-3xl font-semibold tracking-tight">
                Agregar pregunta
            </h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 ps-muted">
                Añade una pregunta a este examen sin usar el CSV. También puedes seguir cargando muchas de golpe desde
                <Link :href="route('exams.import')" class="underline">Cargar examen</Link>.
            </p>
        </template>

        <div class="py-10">
            <form
                class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6"
                @submit.prevent="submit"
            >
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="tipo" value="Tipo" />
                                <select
                                    id="tipo"
                                    v-model="form.tipo"
                                    class="select select-bordered mt-1 w-full"
                                    @change="onTipoChange"
                                >
                                    <option value="opcion_unica">Opción única</option>
                                    <option value="opcion_multiple">Opción múltiple</option>
                                    <option value="abierta">Abierta</option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.tipo" />
                            </div>
                            <div>
                                <InputLabel for="materia" value="Tema de la pregunta (opcional)" />
                                <input
                                    id="materia"
                                    v-model="form.materia"
                                    type="text"
                                    class="input input-bordered mt-1 w-full"
                                    placeholder="No cambia la materia del examen"
                                />
                                <InputError class="mt-1" :message="form.errors.materia" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="pregunta" value="Enunciado" />
                            <textarea
                                id="pregunta"
                                v-model="form.pregunta"
                                class="textarea textarea-bordered mt-1 w-full"
                                rows="4"
                                required
                            ></textarea>
                            <InputError class="mt-1" :message="form.errors.pregunta" />
                        </div>

                        <template v-if="!isOpen">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <InputLabel value="Opciones" />
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-sm"
                                        :disabled="form.opciones.length >= 6"
                                        @click="addOption"
                                    >
                                        + Opción
                                    </button>
                                </div>
                                <p class="mt-1 text-xs ps-muted">
                                    {{ isMultiple ? 'Marca todas las correctas.' : 'Marca la correcta.' }}
                                </p>
                                <div class="mt-3 space-y-2">
                                    <div
                                        v-for="(option, index) in form.opciones"
                                        :key="index"
                                        class="flex items-center gap-2"
                                    >
                                        <button
                                            type="button"
                                            class="btn btn-sm shrink-0"
                                            :class="isCorrect(index) ? 'btn-primary' : 'btn-outline'"
                                            :title="isCorrect(index) ? 'Correcta' : 'Marcar correcta'"
                                            @click="toggleCorrect(index)"
                                        >
                                            {{ optionLabels[index] }}
                                        </button>
                                        <input
                                            v-model="form.opciones[index]"
                                            type="text"
                                            class="input input-bordered w-full"
                                            :placeholder="`Opción ${optionLabels[index]}`"
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-sm"
                                            :disabled="form.opciones.length <= 2"
                                            @click="removeOption(index)"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>
                                <InputError class="mt-1" :message="form.errors.opciones" />
                                <InputError class="mt-1" :message="form.errors.correctas" />
                            </div>
                        </template>

                        <template v-else>
                            <div>
                                <InputLabel for="respuesta_correcta" value="Respuesta esperada" />
                                <textarea
                                    id="respuesta_correcta"
                                    v-model="form.respuesta_correcta"
                                    class="textarea textarea-bordered mt-1 w-full"
                                    rows="3"
                                    placeholder="Texto modelo o respuesta completa"
                                ></textarea>
                                <InputError class="mt-1" :message="form.errors.respuesta_correcta" />
                            </div>
                            <div>
                                <InputLabel for="criterios" value="Criterios / palabras clave" />
                                <input
                                    id="criterios"
                                    v-model="form.criterios"
                                    type="text"
                                    class="input input-bordered mt-1 w-full"
                                    placeholder="separadas con |  ej: definitividad|recursos ordinarios"
                                />
                                <p class="mt-1 text-xs ps-muted">
                                    Si no hay criterios ni respuesta modelo, la pregunta queda por revisar.
                                </p>
                                <InputError class="mt-1" :message="form.errors.criterios" />
                            </div>
                        </template>

                        <div>
                            <InputLabel for="respuesta_modelo" value="Explicación (opcional)" />
                            <textarea
                                id="respuesta_modelo"
                                v-model="form.respuesta_modelo"
                                class="textarea textarea-bordered mt-1 w-full"
                                rows="3"
                                placeholder="Se muestra al revisar el examen"
                            ></textarea>
                            <InputError class="mt-1" :message="form.errors.respuesta_modelo" />
                        </div>

                        <div class="card-actions justify-end gap-2">
                            <Link :href="route('exams.show', exam.id)" class="btn btn-ghost">
                                Cancelar
                            </Link>
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                {{ form.processing ? 'Guardando…' : 'Agregar pregunta' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
