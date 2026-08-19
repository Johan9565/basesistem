<script setup>
import ExamTakeLayout from '@/Layouts/ExamTakeLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    exam: {
        type: Object,
        required: true,
    },
    attempt: {
        type: Object,
        required: true,
    },
    questions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const index = ref(0);
const answers = ref({ ...(props.attempt.answers || {}) });
const confirmOpen = ref(false);
const saving = ref(false);
const checking = ref(false);
const remaining = ref(Number(props.attempt.remaining_seconds || 0));
const finishing = ref(false);
const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
const jumpTo = ref('');
const isPremium = computed(() => Boolean(page.props?.auth?.es_premium));
const isReview = computed(() => props.exam.tipo === 'repaso');

function isOpenType(type) {
    return type === 'abierta' || type === 'open';
}

function isMultipleType(type) {
    return type === 'opcion_multiple' || type === 'multiple';
}

const current = computed(() => props.questions[index.value] ?? null);
const currentType = computed(() => current.value?.type || 'opcion_unica');
const currentIsOpen = computed(() => isOpenType(currentType.value));
const currentIsMultiple = computed(() => isMultipleType(currentType.value));
const total = computed(() => props.questions.length);
const feedbackById = computed(() => props.attempt.feedback || {});
const currentFeedback = computed(() => {
    if (!current.value) return null;
    return feedbackById.value[current.value.id] ?? null;
});
const currentRevealed = computed(() => Boolean(currentFeedback.value));
const currentLocked = computed(() => isReview.value && currentRevealed.value);
const answeredCount = computed(
    () => props.questions.filter((question) => isAnswered(question.id)).length,
);
const revealedCount = computed(
    () => props.questions.filter((question) => Boolean(feedbackById.value[question.id])).length,
);
const unanswered = computed(() => Math.max(0, total.value - answeredCount.value));
const progress = computed(() => {
    if (!total.value) return 0;
    if (isReview.value) {
        return Math.round((revealedCount.value / total.value) * 100);
    }
    return Math.round((answeredCount.value / total.value) * 100);
});
const maxReachableIndex = computed(() => {
    if (!isReview.value) return total.value - 1;
    const firstUnrevealed = props.questions.findIndex((question) => !feedbackById.value[question.id]);
    return firstUnrevealed === -1 ? total.value - 1 : firstUnrevealed;
});
const timerClass = computed(() => {
    if (remaining.value <= 60) return 'ps-sticker-coral';
    if (remaining.value <= 180) return 'ps-sticker-sun';
    return 'ps-sticker-mint';
});
const checkError = computed(() => page.props?.errors?.question_id || '');

function optionLabel(i) {
    return letters[i] ?? String(i + 1);
}

function formatTime(totalSeconds) {
    const safe = Math.max(0, Number(totalSeconds) || 0);
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function isAnswered(questionId) {
    const value = answers.value[questionId];
    if (Array.isArray(value)) {
        return value.length > 0;
    }
    return typeof value === 'string' && value.trim() !== '';
}

function selectedIds() {
    if (!current.value) return [];
    const value = answers.value[current.value.id];
    return Array.isArray(value) ? value : [];
}

function isOptionSelected(optionId) {
    if (!current.value) return false;
    if (currentIsMultiple.value) {
        return selectedIds().includes(optionId);
    }
    return answers.value[current.value.id] === optionId;
}

function optionClass(option) {
    if (!currentRevealed.value) {
        return isOptionSelected(option.id) ? 'ps-option-on' : '';
    }

    const feedback = currentFeedback.value;
    const selected = isOptionSelected(option.id);
    const correctIds = feedback?.correct_option_ids?.length
        ? feedback.correct_option_ids
        : feedback?.correct_option_id
            ? [feedback.correct_option_id]
            : [];

    if (correctIds.length) {
        if (correctIds.includes(option.id)) return 'ps-option-ok';
        if (selected) return 'ps-option-bad';
        return '';
    }

    if (!selected) return '';
    if (feedback?.needs_review) return 'ps-option-on';
    return feedback?.is_correct ? 'ps-option-ok' : 'ps-option-bad';
}

function feedbackBadge(feedback) {
    if (!feedback) return null;
    if (feedback.estado === 'parcial') return { class: 'ps-sticker-sun', label: 'Parcial' };
    if (feedback.needs_review) return { class: 'ps-sticker-sun', label: 'Por revisar' };
    if (feedback.is_correct || feedback.estado === 'correcto') {
        return { class: 'ps-sticker-mint', label: 'Correcta' };
    }
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

function feedbackPanelClass(feedback) {
    const nivel = Number(feedback?.nivel_acierto ?? 0);
    if (feedback?.is_correct || nivel >= 80) return 'border-[#1f9d6a] bg-[#e7fff4]';
    if (feedback?.estado === 'parcial' || nivel >= 1) return 'border-[#e3b341] bg-[#fff6dc]';
    return 'border-[#e85a3c] bg-[#fff0eb]';
}

function feedbackHeadline(feedback) {
    const nivel = Number(feedback?.nivel_acierto ?? 0);
    if (feedback?.is_correct || feedback?.estado === 'correcto') {
        return 'Bien. Esa respuesta es correcta.';
    }
    if (feedback?.estado === 'parcial' || (nivel > 0 && nivel < 80)) {
        return `Parcial. Nivel de acierto: ${nivel}%.`;
    }
    return 'Mal. Esa respuesta no es la correcta.';
}

function mapButtonClass(question, questionIndex) {
    const feedback = feedbackById.value[question.id];
    if (questionIndex === index.value) {
        return 'border-[#17141f] bg-[#ff6b4a] text-white';
    }
    if (feedback) {
        if (feedback.needs_review || feedback.estado === 'parcial' || (feedback.nivel_acierto > 0 && !feedback.is_correct)) {
            return 'border-[#17141f] bg-[#ffe7a3]';
        }
        return feedback.is_correct
            ? 'border-[#17141f] bg-[#c8f5e2]'
            : 'border-[#17141f] bg-[#ffd0c4]';
    }
    if (isAnswered(question.id)) {
        return 'border-[#17141f] bg-[#c8f5e2]';
    }
    return 'border-[#eadfd2] bg-white';
}

function selectOption(optionId) {
    if (!current.value || finishing.value || currentLocked.value) return;
    if (currentIsMultiple.value) {
        const currentIds = selectedIds();
        const next = currentIds.includes(optionId)
            ? currentIds.filter((id) => id !== optionId)
            : [...currentIds, optionId];
        answers.value = {
            ...answers.value,
            [current.value.id]: next,
        };
        return;
    }
    answers.value = {
        ...answers.value,
        [current.value.id]: optionId,
    };
}

function setOpenAnswer(value) {
    if (!current.value || finishing.value || currentLocked.value) return;
    answers.value = {
        ...answers.value,
        [current.value.id]: value,
    };
}

function goToNumber() {
    const next = Number.parseInt(String(jumpTo.value), 10);
    if (Number.isNaN(next)) return;
    goTo(next - 1);
}

function goTo(nextIndex) {
    if (nextIndex < 0 || nextIndex >= total.value) return;
    if (nextIndex > maxReachableIndex.value) return;
    index.value = nextIndex;
}

function attemptUrl(name, suffix = '') {
    const fallback = `/exams/${props.exam.id}/attempts/${props.attempt.id}${suffix}`;
    try {
        return route(name, {
            exam: props.exam.id,
            attempt: props.attempt.id,
        });
    } catch {
        return fallback;
    }
}

function goNext() {
    if (isReview.value && !currentRevealed.value) {
        checkCurrent();
        return;
    }
    if (index.value < total.value - 1) {
        goTo(index.value + 1);
        return;
    }
    confirmOpen.value = true;
}

let saveTimer = null;
function queueSave() {
    if (finishing.value || checking.value) return;
    if (isReview.value && currentLocked.value) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveAnswers, 450);
}

function saveAnswers() {
    if (finishing.value || checking.value) return;
    router.patch(
        attemptUrl('exams.attempts.update'),
        { answers: answers.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function stopChecking() {
    checking.value = false;
}

function checkCurrent() {
    if (!current.value || checking.value || finishing.value) return;
    if (!isAnswered(current.value.id)) {
        return;
    }
    clearTimeout(saveTimer);
    checking.value = true;
    router.post(
        attemptUrl('exams.attempts.check', '/check'),
        {
            question_id: current.value.id,
            answers: { ...answers.value },
        },
        {
            preserveScroll: true,
            preserveState: true,
            onCancel: stopChecking,
            onError: stopChecking,
            onFinish: stopChecking,
        },
    );
}

function submitExam() {
    if (finishing.value) return;
    finishing.value = true;
    confirmOpen.value = false;
    router.post(
        attemptUrl('exams.attempts.submit', '/submit'),
        { answers: { ...answers.value } },
    );
}

function onLeave(event) {
    if (finishing.value) return;
    event.preventDefault();
    event.returnValue = '';
}

watch(answers, queueSave, { deep: true });

let tick = null;
onMounted(() => {
    tick = setInterval(() => {
        remaining.value = Math.max(0, remaining.value - 1);
        if (remaining.value <= 0) {
            clearInterval(tick);
            submitExam();
        }
    }, 1000);
    window.addEventListener('beforeunload', onLeave);
});

onUnmounted(() => {
    clearInterval(tick);
    clearTimeout(saveTimer);
    window.removeEventListener('beforeunload', onLeave);
});
</script>

<template>
    <Head :title="`${exam.name} · En curso`" />

    <ExamTakeLayout>
        <template #title>{{ exam.emoji }} {{ exam.name }}</template>
        <template #subtitle>
            Pregunta {{ index + 1 }} de {{ total }}
            <span v-if="isReview"> · Repaso</span>
            <span v-if="saving"> · Guardando</span>
            <span v-else-if="checking"> · Revisando</span>
        </template>
        <template #actions>
            <div
                class="ps-sticker font-mono text-sm tabular-nums"
                :class="timerClass"
            >
                ⏱ {{ formatTime(remaining) }}
            </div>
            <button type="button" class="ps-btn" @click="confirmOpen = true">
                Enviar
            </button>
        </template>
        <template #progress>
            <div class="h-1.5 bg-[#eadfd2]">
                <div
                    class="h-full bg-[#7c5cff] transition-all duration-300"
                    :style="{ width: `${progress}%` }"
                ></div>
            </div>
        </template>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
            <article v-if="current" class="ps-panel p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="current.subject" class="ps-chip">
                            {{ current.subject }}
                        </span>
                        <span class="ps-sticker ps-sticker-violet text-xs">
                            {{ currentIsOpen ? 'Abierta' : currentIsMultiple ? 'Varias respuestas' : 'Opción única' }}
                        </span>
                        <span
                            v-if="currentFeedback"
                            class="ps-sticker text-xs"
                            :class="feedbackBadge(currentFeedback)?.class"
                        >
                            {{ feedbackBadge(currentFeedback)?.label }}
                        </span>
                        <span
                            v-if="currentFeedback && currentFeedback.nivel_acierto != null"
                            class="ps-sticker text-xs"
                            :class="aciertoClass(currentFeedback.nivel_acierto)"
                        >
                            {{ currentFeedback.nivel_acierto }}%
                        </span>
                    </div>
                    <span class="text-xs font-semibold ps-muted">
                        {{ isReview ? `${revealedCount} / ${total} revisadas` : `${answeredCount} / ${total} contestadas` }}
                    </span>
                </div>

                <h2 class="mt-5 text-lg font-semibold leading-8 text-pretty sm:text-xl">
                    {{ current.prompt }}
                </h2>

                <textarea
                    v-if="currentIsOpen"
                    class="mt-5 min-h-40 w-full rounded-2xl border-2 border-[#eadfd2] bg-white px-4 py-3 text-base leading-7 disabled:bg-[#fff8ef]"
                    placeholder="Escribe tu respuesta…"
                    :value="answers[current.id] || ''"
                    :disabled="currentLocked"
                    @input="setOpenAnswer($event.target.value)"
                ></textarea>

                <div v-else class="mt-5 space-y-2">
                    <p v-if="currentIsMultiple" class="text-xs ps-muted">
                        Puedes marcar más de una opción.
                    </p>
                    <button
                        v-for="(option, optionIndex) in current.options || []"
                        :key="option.id"
                        type="button"
                        class="ps-option"
                        :class="optionClass(option)"
                        :disabled="currentLocked"
                        @click="selectOption(option.id)"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center border-2 text-sm font-semibold"
                            :class="[
                                currentIsMultiple ? 'rounded-md' : 'rounded-full',
                                isOptionSelected(option.id)
                                    ? 'border-[#7c5cff] bg-[#7c5cff] text-white'
                                    : 'border-[#eadfd2] bg-white',
                            ]"
                        >
                            {{ optionLabel(optionIndex) }}
                        </span>
                        <span class="pt-1 text-sm leading-6">{{ option.text }}</span>
                    </button>
                </div>

                <div
                    v-if="isReview && currentFeedback"
                    class="mt-5 rounded-2xl border-2 px-4 py-3"
                    :class="feedbackPanelClass(currentFeedback)"
                >
                    <p class="text-sm font-semibold">
                        {{ feedbackHeadline(currentFeedback) }}
                    </p>
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <span>Nivel de acierto</span>
                            <span>{{ currentFeedback.nivel_acierto ?? 0 }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-white/80">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="aciertoBarClass(currentFeedback.nivel_acierto ?? 0)"
                                :style="{ width: `${currentFeedback.nivel_acierto ?? 0}%` }"
                            ></div>
                        </div>
                    </div>
                    <p
                        v-if="isPremium && currentFeedback.explanation"
                        class="mt-2 text-sm leading-6 text-[#3d3848]/80"
                    >
                        {{ currentFeedback.explanation }}
                    </p>
                    <ul
                        v-if="isPremium && currentFeedback.criterios_cumplidos?.length"
                        class="mt-2 list-disc pl-5 text-sm leading-6"
                    >
                        <li v-for="item in currentFeedback.criterios_cumplidos" :key="`ok-${item}`">
                            Cubierto: {{ item }}
                        </li>
                    </ul>
                    <ul
                        v-if="isPremium && currentFeedback.criterios_omitidos?.length"
                        class="mt-1 list-disc pl-5 text-sm leading-6 ps-muted"
                    >
                        <li v-for="item in currentFeedback.criterios_omitidos" :key="`miss-${item}`">
                            Falta: {{ item }}
                        </li>
                    </ul>
                    <p
                        v-if="!isPremium"
                        class="mt-2 text-sm leading-6 ps-muted"
                    >
                        Con premium te decimos por qué está bien o mal.
                    </p>
                </div>

                <p v-if="isReview && checkError" class="mt-3 text-sm font-semibold text-[#e85a3c]">
                    {{ checkError }}
                </p>
                <p
                    v-else-if="isReview && !currentRevealed && !isAnswered(current.id)"
                    class="mt-3 text-sm ps-muted"
                >
                    Contesta para ver si está bien o mal al dar siguiente.
                </p>

                <div class="mt-6 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        class="ps-btn-ghost"
                        :disabled="index === 0"
                        @click="goTo(index - 1)"
                    >
                        Anterior
                    </button>
                    <button
                        v-if="index < total - 1 || (isReview && !currentRevealed)"
                        type="button"
                        class="ps-btn"
                        :disabled="checking || (isReview && !currentRevealed && !isAnswered(current.id))"
                        @click="goNext"
                    >
                        {{ checking ? 'Revisando…' : 'Siguiente →' }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="ps-btn"
                        @click="confirmOpen = true"
                    >
                        Enviar examen
                    </button>
                </div>
            </article>

            <aside class="ps-card ps-card-static ps-tone-sun p-5 lg:sticky lg:top-28">
                <p class="text-sm font-semibold">Mapa de preguntas</p>
                <form class="mt-3 flex gap-2" @submit.prevent="goToNumber">
                    <input
                        v-model="jumpTo"
                        type="number"
                        min="1"
                        :max="total"
                        class="w-full rounded-full border-2 border-[#17141f] bg-white px-3 py-1.5 text-sm"
                        placeholder="Ir a #"
                    />
                    <button type="submit" class="ps-btn-ghost px-3 py-1.5">Ir</button>
                </form>
                <div class="mt-3 max-h-72 overflow-y-auto pr-1">
                    <div class="grid grid-cols-8 gap-1.5 lg:grid-cols-6">
                        <button
                            v-for="(question, questionIndex) in questions"
                            :key="question.id"
                            type="button"
                            class="min-h-8 rounded-lg border-2 text-xs font-semibold disabled:opacity-40"
                            :class="mapButtonClass(question, questionIndex)"
                            :disabled="questionIndex > maxReachableIndex"
                            @click="goTo(questionIndex)"
                        >
                            {{ questionIndex + 1 }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        <div
            v-if="confirmOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#17141f]/50 p-4"
            @click.self="confirmOpen = false"
        >
            <div class="ps-panel w-full max-w-md p-6">
                <h3 class="text-lg font-semibold">¿Enviar el examen?</h3>
                <p class="mt-2 text-sm leading-6 ps-muted">
                    Contestaste {{ answeredCount }} de {{ total }}.
                    <span v-if="unanswered"> Te faltan {{ unanswered }}. Las vacías cuentan como incorrectas.</span>
                    Ya no podrás cambiar las respuestas.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="ps-btn-ghost" @click="confirmOpen = false">
                        Seguir
                    </button>
                    <button type="button" class="ps-btn" :disabled="finishing" @click="submitExam">
                        Enviar ahora
                    </button>
                </div>
            </div>
        </div>
    </ExamTakeLayout>
</template>
