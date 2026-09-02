<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const form = useForm({
    name: '',
    materia: '',
    description: '',
    duration_minutes: 180,
    emoji: '📘',
    is_public: true,
    acceso: 'gratis',
    tipo: 'normal',
    preguntas_por_materia: 10,
    file: null,
});

const fileName = ref('');
const dragging = ref(false);
const modoPreguntas = ref('muestreo');
const cantidadPorMateria = ref(10);

watch(modoPreguntas, (modo) => {
    if (modo === 'todas') {
        form.preguntas_por_materia = 0;
        return;
    }
    form.preguntas_por_materia = Math.max(1, Number(cantidadPorMateria.value) || 10);
});

watch(cantidadPorMateria, (valor) => {
    if (modoPreguntas.value !== 'muestreo') return;
    form.preguntas_por_materia = Math.max(1, Number(valor) || 10);
});

const ayudaPreguntas = computed(() => {
    if (modoPreguntas.value === 'todas') {
        return 'Cada intento muestra todas las preguntas del CSV, en el orden del banco.';
    }
    return 'Cada intento toma al azar esa cantidad por cada materia del CSV (columna materia). Ej.: 10 con 13 materias ≈ 130 preguntas. Solo se define al crear el examen.';
});

function onFile(file) {
    if (!file) return;
    form.file = file;
    fileName.value = file.name;
}

function submit() {
    form.post(route('exams.import.store'), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Cargar examen" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-3xl font-semibold tracking-tight">
                Cargar examen
            </h2>
        </template>

        <div class="py-10">
            <div class="mx-auto grid max-w-5xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.1fr_0.9fr]">
                <form class="card border border-base-300 bg-base-100 shadow-sm" @submit.prevent="submit">
                    <div class="card-body gap-4">
                        <p class="text-sm leading-6 text-base-content/70">
                            Define la materia del examen aquí (así se agrupa en el dashboard). El CSV solo trae las preguntas.
                            Si el examen ya existe, puedes agregar preguntas una por una desde la ficha del examen.
                        </p>

                        <div>
                            <InputLabel for="name" value="Nombre del examen" />
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="input input-bordered mt-1 w-full"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="materia" value="Materia" />
                            <input
                                id="materia"
                                v-model="form.materia"
                                type="text"
                                class="input input-bordered mt-1 w-full"
                                placeholder="Ej. Constitucional"
                                required
                            />
                            <p class="mt-1 text-xs text-base-content/60">
                                El examen pertenece a esta materia; no se infiere de las preguntas del CSV.
                            </p>
                            <InputError class="mt-1" :message="form.errors.materia" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Descripción" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="textarea textarea-bordered mt-1 w-full"
                                rows="3"
                            ></textarea>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="duration_minutes" value="Duración (minutos)" />
                                <input
                                    id="duration_minutes"
                                    v-model="form.duration_minutes"
                                    type="number"
                                    min="1"
                                    max="600"
                                    class="input input-bordered mt-1 w-full"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.duration_minutes" />
                            </div>
                            <div>
                                <InputLabel for="emoji" value="Emoji" />
                                <input
                                    id="emoji"
                                    v-model="form.emoji"
                                    type="text"
                                    class="input input-bordered mt-1 w-full"
                                />
                            </div>
                        </div>

                        <label class="label cursor-pointer justify-start gap-3">
                            <input v-model="form.is_public" type="checkbox" class="checkbox checkbox-primary" />
                            <span class="label-text">Visible para todos los usuarios con sesión</span>
                        </label>

                        <div>
                            <InputLabel for="acceso" value="Acceso" />
                            <select id="acceso" v-model="form.acceso" class="select select-bordered mt-1 w-full">
                                <option value="gratis">Gratis</option>
                                <option value="prueba">Prueba (10 preguntas, 3 intentos)</option>
                                <option value="premium">Premium</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.acceso" />
                        </div>

                        <div>
                            <InputLabel for="tipo" value="Tipo de examen" />
                            <select id="tipo" v-model="form.tipo" class="select select-bordered mt-1 w-full">
                                <option value="normal">Examen normal</option>
                                <option value="repaso">Examen de repaso</option>
                            </select>
                            <p class="mt-1 text-xs leading-5 text-base-content/60">
                                En el de repaso, al dar siguiente se indica si acertó. Si el usuario es premium, también ve por qué.
                            </p>
                            <InputError class="mt-1" :message="form.errors.tipo" />
                        </div>

                        <div>
                            <InputLabel for="modo_preguntas" value="Preguntas por intento" />
                            <select
                                id="modo_preguntas"
                                v-model="modoPreguntas"
                                class="select select-bordered mt-1 w-full"
                            >
                                <option value="muestreo">Aleatorias por materia</option>
                                <option value="todas">Mostrar todas las preguntas</option>
                            </select>
                            <div v-if="modoPreguntas === 'muestreo'" class="mt-3">
                                <InputLabel for="preguntas_por_materia" value="Cantidad por materia" />
                                <input
                                    id="preguntas_por_materia"
                                    v-model.number="cantidadPorMateria"
                                    type="number"
                                    min="1"
                                    max="200"
                                    class="input input-bordered mt-1 w-full"
                                    required
                                />
                            </div>
                            <p class="mt-1 text-xs leading-5 text-base-content/60">
                                {{ ayudaPreguntas }}
                            </p>
                            <InputError class="mt-1" :message="form.errors.preguntas_por_materia" />
                        </div>

                        <div>
                            <InputLabel value="Archivo CSV" />
                            <div
                                class="mt-1 rounded-2xl border-2 border-dashed px-4 py-8 text-center"
                                :class="dragging ? 'border-primary bg-primary/5' : 'border-base-300'"
                                @dragover.prevent="dragging = true"
                                @dragleave.prevent="dragging = false"
                                @drop.prevent="dragging = false; onFile($event.dataTransfer.files[0])"
                            >
                                <p class="text-sm">{{ fileName || 'Arrastra el CSV o elige un archivo' }}</p>
                                <input
                                    class="file-input file-input-bordered mt-3 w-full max-w-xs"
                                    type="file"
                                    accept=".csv,text/csv,text/plain"
                                    @change="onFile($event.target.files[0])"
                                />
                            </div>
                            <InputError class="mt-1" :message="form.errors.file" />
                        </div>

                        <div class="card-actions justify-end">
                            <button type="submit" class="btn btn-primary" :disabled="form.processing || !form.file">
                                {{ form.processing ? 'Cargando…' : 'Crear examen' }}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4 text-sm leading-6 text-base-content/80">
                        <h3 class="text-base font-semibold text-base-content">Cómo armar el template</h3>
                        <a :href="route('exams.template')" class="btn btn-outline btn-sm w-fit">
                            Descargar CSV de ejemplo
                        </a>
                        <p>En Excel: <strong>Archivo → Guardar como → CSV UTF-8</strong>. Una fila = una pregunta.</p>

                        <p class="font-semibold text-base-content">Columna tipo</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li><code>opcion_unica</code> — una sola respuesta correcta.</li>
                            <li><code>opcion_multiple</code> — varias correctas, en <code>correctas</code> (<code>0|2</code>) o en <code>respuesta_correcta</code>.</li>
                            <li><code>abierta</code> — el alumno escribe texto. Llena <code>respuesta_correcta</code> y/o <code>criterios</code>.</li>
                        </ul>

                        <p class="font-semibold text-base-content">Columnas</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li><code>pregunta</code> — enunciado (obligatorio).</li>
                            <li><code>materia</code> — tema de la pregunta; si hay varias, el examen puede muestrear N al azar por materia.</li>
                            <li><code>opcion_a</code> … <code>opcion_f</code> — vacías en abiertas.</li>
                            <li><code>correctas</code> — índices desde 0. Varias: <code>0|1</code>. Opcional si pones el texto en <code>respuesta_correcta</code>.</li>
                            <li><code>respuesta_correcta</code> — texto de la opción buena, o varias separadas con <code>|</code>. En abiertas, la respuesta esperada.</li>
                            <li><code>respuesta_modelo</code> — explicación que se muestra al revisar.</li>
                            <li><code>criterios</code> — en abiertas, todas deben aparecer para marcar correcta. Ej: <code>definitividad|recursos ordinarios</code>.</li>
                        </ul>

                        <p>
                            Para ~500 preguntas no pongas saltos de línea raros dentro de una celda, o encierra el texto en comillas.
                            Si una abierta no tiene respuesta modelo ni palabras clave, queda <strong>por revisar</strong> y no entra al porcentaje automático.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
