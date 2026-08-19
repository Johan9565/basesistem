<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    exam: {
        type: Object,
        required: true,
    },
    attempt: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    anuncio_visto: false,
});
const page = usePage();
const letters = ['A', 'B', 'C', 'D', 'E', 'F'];

const scoreTone = computed(() => {
    const score = props.attempt.score ?? 0;
    if (score >= 70) return 'ps-tone-mint';
    if (score >= 50) return 'ps-tone-sun';
    return 'ps-tone-coral';
});

const statusLabel = computed(() =>
    props.attempt.status === 'timed_out' ? 'Se envió al acabarse el tiempo' : 'Examen enviado',
);

function optionLabel(i) {
    return letters[i] ?? String(i + 1);
}

function optionClass(question, option) {
    const selectedIds = question.selected_option_ids?.length
        ? question.selected_option_ids
        : question.selected_option_id
            ? [question.selected_option_id]
            : [];
    const correctIds = question.correct_option_ids?.length
        ? question.correct_option_ids
        : question.correct_option_id
            ? [question.correct_option_id]
            : [];
    const selected = selectedIds.includes(option.id);
    const correct = correctIds.includes(option.id);

    if (correct) return 'ps-option-ok';
    if (selected && !correct) return 'ps-option-bad';
    return '';
}

function resultBadge(question) {
    if (question.estado === 'parcial') return { class: 'ps-sticker-sun', label: 'Parcial' };
    if (question.needs_review) return { class: 'ps-sticker-sun', label: 'Por revisar' };
    if (question.is_correct || question.estado === 'correcto') return { class: 'ps-sticker-mint', label: 'Correcta' };
    return { class: 'ps-sticker-coral', label: 'Incorrecta' };
}

function aciertoClass(nivel) {
    if (nivel >= 80) return 'ps-sticker-mint';
    if (nivel >= 50) return 'ps-sticker-sun';
    return 'ps-sticker-coral';
}

function aciertoBarClass(nivel) {
    if (nivel >= 80) return 'bg-[#1f9d6a]';
    if (nivel >= 50) return 'bg-[#e3b341]';
    return 'bg-[#e85a3c]';
}

function isOpenType(type) {
    return type === 'abierta' || type === 'open';
}

function typeLabel(type) {
    if (isOpenType(type)) return 'Abierta';
    if (type === 'opcion_multiple' || type === 'multiple') return 'Varias respuestas';
    return 'Opción única';
}

function retry() {
    const isPremium = Boolean(page.props?.auth?.es_premium);
    if (props.exam.acceso === 'prueba' && !isPremium) {
        router.visit(route('exams.show', props.exam.id));
        return;
    }
    form.post(route('exams.attempts.store', props.exam.id));
}
</script>

<template>
    <Head :title="`${exam.name} · Resultado`" />

    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('exams.show', exam.id)" class="ps-btn-ghost">
                ← {{ exam.name }}
            </Link>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight">Resultado</h2>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-3xl space-y-6 px-6">
                <div class="ps-card ps-card-static overflow-hidden p-8 text-center" :class="scoreTone">
                    <p class="ps-sticker bg-white/70 text-sm">{{ statusLabel }}</p>
                    <p class="mt-4 text-6xl font-semibold tabular-nums">
                        {{ attempt.score }}%
                    </p>
                    <p class="mt-2 text-sm leading-6 text-[#3d3848]/80">
                        {{ attempt.correct_count }} correctas
                        <span v-if="attempt.graded_total"> de {{ attempt.graded_total }} evaluadas</span>
                        <span v-if="attempt.pending_count"> · {{ attempt.pending_count }} por revisar</span>
                        · {{ attempt.total }} en total
                        <span v-if="attempt.intentos_ia_restantes != null && page.props?.auth?.es_premium">
                            · IA restantes: {{ attempt.intentos_ia_restantes }}
                        </span>
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <Link :href="route('dashboard')" class="ps-btn-ghost">
                            Ir al panel
                        </Link>
                        <button
                            type="button"
                            class="ps-btn"
                            :disabled="form.processing"
                            @click="retry"
                        >
                            Volver a intentar
                        </button>
                    </div>
                </div>

                <article
                    v-for="(question, questionIndex) in attempt.questions"
                    :key="question.id"
                    class="ps-panel p-6"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold ps-muted">
                                Pregunta {{ questionIndex + 1 }}
                            </span>
                            <span class="ps-chip">{{ typeLabel(question.type) }}</span>
                        </div>
                        <span
                            class="ps-sticker text-xs"
                            :class="resultBadge(question).class"
                        >
                            {{ resultBadge(question).label }}
                        </span>
                        <span
                            v-if="question.nivel_acierto != null"
                            class="ps-sticker text-xs"
                            :class="aciertoClass(question.nivel_acierto)"
                        >
                            {{ question.nivel_acierto }}% acierto
                        </span>
                    </div>
                    <h3 class="mt-4 font-semibold leading-7">{{ question.prompt }}</h3>

                    <div
                        v-if="question.nivel_acierto != null"
                        class="mt-4"
                    >
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <span class="ps-muted">Nivel de acierto</span>
                            <span>{{ question.nivel_acierto }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-[#eadfd2]">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="aciertoBarClass(question.nivel_acierto)"
                                :style="{ width: `${question.nivel_acierto}%` }"
                            ></div>
                        </div>
                    </div>

                    <div v-if="isOpenType(question.type)" class="mt-4 space-y-3">
                        <div class="rounded-2xl border-2 border-[#eadfd2] bg-white px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide ps-muted">Tu respuesta</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm leading-6">
                                {{ question.text_answer || 'Sin respuesta' }}
                            </p>
                        </div>
                        <div
                            v-if="question.retroalimentacion"
                            class="rounded-2xl bg-[#eef2ff] px-4 py-3 text-sm leading-6"
                        >
                            {{ question.retroalimentacion }}
                        </div>
                        <div
                            v-if="question.criterios_cumplidos?.length"
                            class="text-sm leading-6"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide ps-muted">Criterios cubiertos</p>
                            <ul class="mt-1 list-disc pl-5">
                                <li v-for="item in question.criterios_cumplidos" :key="`ok-${item}`">{{ item }}</li>
                            </ul>
                        </div>
                        <div
                            v-if="question.criterios_omitidos?.length"
                            class="text-sm leading-6"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide ps-muted">Criterios omitidos</p>
                            <ul class="mt-1 list-disc pl-5">
                                <li v-for="item in question.criterios_omitidos" :key="`miss-${item}`">{{ item }}</li>
                            </ul>
                        </div>
                        <div
                            v-if="question.respuesta_correcta"
                            class="rounded-2xl bg-[#e7fff4] px-4 py-3 text-sm leading-6"
                        >
                            Respuesta correcta:
                            {{ question.respuesta_correcta }}
                        </div>
                    </div>

                    <div v-else class="mt-4 space-y-2">
                        <div
                            v-for="(option, optionIndex) in question.options"
                            :key="option.id"
                            class="ps-option"
                            :class="optionClass(question, option)"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-full border border-[#eadfd2] bg-white text-sm font-semibold">
                                {{ optionLabel(optionIndex) }}
                            </span>
                            <span class="pt-1 text-sm leading-6">{{ option.text }}</span>
                        </div>
                    </div>
                    <p
                        v-if="question.explanation"
                        class="mt-4 rounded-2xl bg-[#fff4e4] px-4 py-3 text-sm leading-6 text-[#3d3848]/80"
                    >
                        {{ question.explanation }}
                    </p>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
