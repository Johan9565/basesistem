<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    exam: {
        type: Object,
        required: true,
    },
    current_attempt_id: {
        type: String,
        default: null,
    },
    attempts: {
        type: Array,
        default: () => [],
    },
    requiere_anuncio: {
        type: Boolean,
        default: false,
    },
    can_delete_exam: {
        type: Boolean,
        default: false,
    },
    can_add_questions: {
        type: Boolean,
        default: false,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const form = useForm({
    anuncio_visto: false,
});
const deleteForm = useForm({});
const adOpen = ref(false);
const deleteOpen = ref(false);
const isPremium = computed(() => Boolean(page.props?.auth?.es_premium));
const showManage = computed(() => props.can_delete_exam || props.can_add_questions);
const isTrial = computed(() => props.exam.acceso === 'prueba');
const usesSampling = computed(() => Number(props.exam.preguntas_por_materia ?? 0) > 0);
const trialRemaining = computed(() =>
    props.exam.intentos_prueba_restantes == null ? null : Number(props.exam.intentos_prueba_restantes),
);
const canStart = computed(() => {
    if ((props.exam.question_count ?? 0) <= 0) return false;
    if (isTrial.value && !isPremium.value && trialRemaining.value === 0 && !props.current_attempt_id) {
        return false;
    }
    return true;
});

watch(
    () => props.requiere_anuncio,
    (value) => {
        if (value) adOpen.value = true;
    },
    { immediate: true },
);

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function startExam() {
    if (isTrial.value && !isPremium.value && !props.current_attempt_id) {
        adOpen.value = true;
        return;
    }
    form.anuncio_visto = false;
    form.post(route('exams.attempts.store', props.exam.id));
}

function confirmStartAfterAd() {
    form.anuncio_visto = true;
    form.post(route('exams.attempts.store', props.exam.id), {
        onFinish: () => {
            adOpen.value = false;
        },
    });
}

function continueExam() {
    router.visit(
        route('exams.attempts.show', {
            exam: props.exam.id,
            attempt: props.current_attempt_id,
        }),
    );
}

function attemptStatus(status) {
    if (status === 'timed_out') return 'Tiempo agotado';
    return 'Enviado';
}

function toneClass(tone) {
    const known = ['violet', 'mint', 'coral', 'sky', 'sun', 'pink'];
    return known.includes(tone) ? `ps-tone-${tone}` : 'ps-tone-primary';
}

function accesoLabel(acceso) {
    if (acceso === 'premium') return 'Premium';
    if (acceso === 'prueba') return 'Prueba';
    return 'Gratis';
}

function confirmDelete() {
    deleteForm.delete(route('exams.destroy', props.exam.id), {
        onFinish: () => {
            deleteOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="exam.name" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('dashboard')" class="ps-btn-ghost">
                ← Exámenes
            </Link>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <span class="ps-icon-tile text-2xl" :class="toneClass(exam.tone)">
                    {{ exam.emoji }}
                </span>
                <h2 class="text-3xl font-semibold tracking-tight">
                    {{ exam.name }}
                </h2>
                <span class="ps-sticker text-xs">{{ accesoLabel(exam.acceso) }}</span>
                <span v-if="exam.tipo === 'repaso'" class="ps-sticker ps-sticker-sky text-xs">
                    Repaso
                </span>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto grid max-w-6xl gap-6 px-6 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="ps-panel p-6 md:p-8">
                    <p class="text-sm leading-7 ps-muted">
                        {{ exam.description }}
                    </p>

                    <div v-if="exam.subjects?.length" class="mt-6">
                        <p class="ps-sticker ps-sticker-violet text-xs">Materia</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="subject in exam.subjects"
                                :key="subject"
                                class="ps-chip"
                            >
                                {{ subject }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="ps-card ps-card-static ps-tone-sun p-4">
                            <p class="text-xs font-semibold ps-muted">
                                {{ usesSampling ? 'Por intento' : 'Preguntas' }}
                            </p>
                            <p class="mt-1 text-lg font-semibold">{{ exam.question_count }}</p>
                            <p v-if="usesSampling" class="mt-1 text-xs ps-muted">
                                Banco: {{ exam.banco_preguntas }} · {{ exam.preguntas_por_materia }} por materia
                            </p>
                        </div>
                        <div class="ps-card ps-card-static ps-tone-mint p-4">
                            <p class="text-xs font-semibold ps-muted">Tiempo</p>
                            <p class="mt-1 text-lg font-semibold">{{ exam.duration_minutes }} min</p>
                        </div>
                    </div>

                    <div v-if="exam.materias_banco?.length > 1" class="mt-6">
                        <p class="ps-sticker ps-sticker-sky text-xs">Materias en el banco</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="subject in exam.materias_banco"
                                :key="subject"
                                class="ps-chip"
                            >
                                {{ subject }}
                            </span>
                        </div>
                    </div>

                    <ul class="mt-6 list-disc space-y-1 pl-5 text-sm leading-6 ps-muted">
                        <li>Hay preguntas de opción única, varias respuestas y abiertas.</li>
                        <li v-if="usesSampling">
                            Cada intento elige al azar {{ exam.preguntas_por_materia }} pregunta(s) de cada materia del banco.
                        </li>
                        <li>El avance se guarda solo. Si sales, puedes continuar.</li>
                        <li>Al acabarse el tiempo, el examen se envía con lo que hayas contestado.</li>
                        <template v-if="exam.tipo === 'repaso'">
                            <li>Examen de repaso: al dar siguiente te dice si la pregunta está bien o mal.</li>
                            <li v-if="isPremium">
                                Como eres premium, también verás por qué está bien o mal.
                            </li>
                            <li v-else>
                                Con premium también te dice por qué está bien o mal.
                            </li>
                        </template>
                        <li v-if="isTrial && !isPremium">
                            Examen de prueba: {{ exam.intentos_prueba_limite }} intentos en total.
                            <span v-if="trialRemaining !== null"> Te quedan {{ trialRemaining }}.</span>
                        </li>
                    </ul>

                    <InputError class="mt-4" :message="form.errors.exam || errors.exam || form.errors.anuncio || errors.anuncio" />

                    <div class="mt-8 flex flex-wrap items-center justify-end gap-3">
                        <button
                            v-if="current_attempt_id"
                            type="button"
                            class="ps-btn ps-btn-lg"
                            @click="continueExam"
                        >
                            Continuar examen
                        </button>
                        <button
                            v-else
                            type="button"
                            class="ps-btn ps-btn-lg"
                            :disabled="!canStart || form.processing"
                            @click="startExam"
                        >
                            {{ form.processing ? 'Preparando…' : (isTrial && !isPremium && trialRemaining === 0 ? 'Sin intentos de prueba' : 'Comenzar examen') }}
                        </button>
                    </div>
                </div>

                <div class="space-y-6">
                    <div v-if="showManage" class="ps-card ps-card-static ps-tone-coral p-6">
                        <h3 class="text-lg font-semibold tracking-tight">Gestión</h3>
                        <p class="mt-1 text-sm leading-6 ps-muted">
                            Acciones de administración para este examen.
                        </p>
                        <div class="mt-4 flex flex-col gap-2">
                            <Link
                                v-if="can_add_questions"
                                :href="route('exams.questions.create', exam.id)"
                                class="ps-btn w-full justify-center"
                            >
                                Agregar pregunta
                            </Link>
                            <button
                                v-if="can_delete_exam"
                                type="button"
                                class="btn btn-error btn-outline w-full"
                                @click="deleteOpen = true"
                            >
                                Borrar examen
                            </button>
                        </div>
                    </div>

                    <div class="ps-card ps-card-static ps-tone-violet p-6">
                        <h3 class="text-lg font-semibold tracking-tight">Intentos anteriores</h3>
                        <p
                            v-if="!attempts.length"
                            class="mt-2 text-sm text-[#3d3848]/80"
                        >
                            Todavía no presentas este examen.
                        </p>
                        <ul v-else class="mt-3 space-y-2">
                            <li
                                v-for="attempt in attempts"
                                :key="attempt.id"
                            >
                                <Link
                                    :href="route('exams.attempts.result', { exam: exam.id, attempt: attempt.id })"
                                    class="flex items-center justify-between gap-3 rounded-2xl border-2 border-[#17141f] bg-white/75 px-3 py-2"
                                >
                                    <div>
                                        <p class="text-sm font-semibold">
                                            {{ attempt.score }}% · {{ attempt.correct_count }}/{{ attempt.total }}
                                        </p>
                                        <p class="text-xs ps-muted">
                                            {{ attemptStatus(attempt.status) }} · {{ formatDate(attempt.submitted_at) }}
                                        </p>
                                    </div>
                                    <span class="text-xs font-bold">Ver →</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="deleteOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#17141f]/50 px-4"
        >
            <div class="ps-card ps-card-static max-w-lg p-6">
                <p class="ps-sticker ps-sticker-coral text-xs">Borrar examen</p>
                <h3 class="mt-4 text-xl font-semibold tracking-tight">
                    ¿Borrar «{{ exam.name }}»?
                </h3>
                <p class="mt-3 text-sm leading-6 ps-muted">
                    Se eliminarán también todas las preguntas y los intentos asociados. Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button type="button" class="ps-btn-ghost" :disabled="deleteForm.processing" @click="deleteOpen = false">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="btn btn-error"
                        :disabled="deleteForm.processing"
                        @click="confirmDelete"
                    >
                        {{ deleteForm.processing ? 'Borrando…' : 'Sí, borrar' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="adOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#17141f]/50 px-4"
        >
            <div class="ps-card ps-card-static max-w-lg p-6">
                <p class="ps-sticker ps-sticker-sun text-xs">Anuncio · examen de prueba</p>
                <h3 class="mt-4 text-xl font-semibold tracking-tight">
                    Antes de comenzar
                </h3>
                <div class="mt-4 grid min-h-28 place-items-center rounded-2xl border-2 border-dashed border-[#eadfd2] bg-[#fff8ef] px-4 py-8 text-center text-sm leading-6 ps-muted">
                    Espacio publicitario
                    <span class="mt-1 block text-xs">Pasa a premium para quitar anuncios y abrir los exámenes completos.</span>
                </div>
                <p class="mt-4 text-sm leading-6 ps-muted">
                    Este intento cuenta dentro de tus {{ exam.intentos_prueba_limite }} pruebas.
                    La evaluación con IA queda para el plan premium.
                </p>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button type="button" class="ps-btn-ghost" @click="adOpen = false">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="ps-btn"
                        :disabled="form.processing"
                        @click="confirmStartAfterAd"
                    >
                        {{ form.processing ? 'Preparando…' : 'Continuar al examen' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
