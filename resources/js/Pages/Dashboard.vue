<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    exams: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const firstName = computed(() => {
    const name = page.props?.auth?.user?.name ?? '';
    return String(name).trim().split(/\s+/)[0] || 'tú';
});
const isPremium = computed(() => Boolean(page.props?.auth?.es_premium));
const iaRestantes = computed(() => Number(page.props?.auth?.intentos_ia_restantes ?? 0));

const searchBySubject = reactive({});

function toneClass(tone) {
    const known = ['violet', 'mint', 'coral', 'sky', 'sun', 'pink'];
    return known.includes(tone) ? `ps-tone-${tone}` : 'ps-tone-primary';
}

function accesoLabel(acceso) {
    if (acceso === 'premium') return 'Premium';
    if (acceso === 'prueba') return 'Prueba';
    return 'Gratis';
}

function accesoClass(acceso) {
    if (acceso === 'premium') return 'ps-sticker-violet';
    if (acceso === 'prueba') return 'ps-sticker-sun';
    return 'ps-sticker-mint';
}

function isReviewExam(exam) {
    return exam.tipo === 'repaso';
}

function examMatches(exam, query) {
    const q = String(query ?? '').trim().toLowerCase();
    if (!q) return true;

    const haystack = [exam.name, exam.description, isReviewExam(exam) ? 'repaso' : '', ...(exam.subjects ?? [])]
        .map((value) => String(value ?? '').toLowerCase())
        .join(' ');

    return haystack.includes(q);
}

const subjectSections = computed(() => {
    const groups = new Map();

    for (const exam of props.exams) {
        const subjects = (exam.subjects ?? [])
            .map((subject) => String(subject ?? '').trim())
            .filter(Boolean);

        const keys = subjects.length ? subjects : ['Sin materia'];

        for (const name of keys) {
            if (!groups.has(name)) {
                groups.set(name, []);
            }
            groups.get(name).push(exam);
        }
    }

    return [...groups.entries()]
        .sort((a, b) => a[0].localeCompare(b[0], 'es', { sensitivity: 'base' }))
        .map(([name, exams], index) => {
            const query = searchBySubject[name] ?? '';
            return {
                name,
                exams,
                query,
                filtered: exams.filter((exam) => examMatches(exam, query)),
                emoji: exams[0]?.emoji || '📘',
                tone: exams[0]?.tone || ['violet', 'mint', 'coral', 'sky', 'sun', 'pink'][index % 6],
            };
        });
});
</script>

<template>
    <Head title="Mis exámenes" />

    <AuthenticatedLayout>
        <template #header>
            <p class="ps-sticker ps-sticker-sun">
                <span class="size-2 rounded-full bg-[#17141f]"></span>
                Hola, {{ firstName }}
            </p>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight">
                Tus materias, <span class="bg-[linear-gradient(transparent_58%,#ddd6ff_58%)]">con sus exámenes.</span>
            </h2>
            <p class="mt-2 max-w-xl text-base leading-7 ps-muted">
                Cada materia junta lo que puedes presentar. Busca dentro del apartado y entra a practicar.
            </p>
            <p v-if="isPremium" class="mt-3 text-sm ps-muted">
                Evaluaciones IA restantes hoy: <strong>{{ iaRestantes }}</strong>
            </p>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-6xl px-6">
                <div
                    v-if="exams.length"
                    class="mb-8 flex flex-wrap items-center gap-2"
                >
                    <span class="ps-chip">
                        {{ subjectSections.length }}
                        {{ subjectSections.length === 1 ? 'materia' : 'materias' }}
                    </span>
                    <span class="ps-chip">
                        {{ exams.length }}
                        {{ exams.length === 1 ? 'examen' : 'exámenes' }}
                    </span>
                </div>

                <div v-if="subjectSections.length" class="space-y-10">
                    <section
                        v-for="section in subjectSections"
                        :key="section.name"
                        class="ps-panel p-5 sm:p-6"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div class="flex items-start gap-3">
                                <span class="ps-icon-tile" :class="toneClass(section.tone)">
                                    {{ section.emoji }}
                                </span>
                                <div>
                                    <h3 class="text-2xl font-semibold tracking-tight">
                                        {{ section.name }}
                                    </h3>
                                    <p class="mt-1 text-sm ps-muted">
                                        {{ section.exams.length }}
                                        {{ section.exams.length === 1 ? 'examen' : 'exámenes' }}
                                    </p>
                                </div>
                            </div>

                            <label class="relative block w-full lg:max-w-sm">
                                <span class="sr-only">Buscar exámenes en {{ section.name }}</span>
                                <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-[#3d3848]/55">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        class="size-4"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M9 3.5a5.5 5.5 0 1 0 3.47 9.77l3.63 3.63a.75.75 0 1 0 1.06-1.06l-3.63-3.63A5.5 5.5 0 0 0 9 3.5ZM5.5 9a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </span>
                                <input
                                    v-model="searchBySubject[section.name]"
                                    type="search"
                                    :placeholder="`Buscar en ${section.name}`"
                                    class="w-full rounded-2xl border-2 border-[var(--line)] bg-white py-2.5 pl-9 pr-3 text-sm font-medium text-[#17141f] placeholder:text-[#3d3848]/45 focus:border-[#17141f] focus:outline-none"
                                >
                            </label>
                        </div>

                        <div
                            v-if="section.filtered.length"
                            class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <Link
                                v-for="exam in section.filtered"
                                :key="exam.id"
                                :href="route('exams.show', exam.id)"
                                class="ps-card relative overflow-hidden p-5"
                                :class="toneClass(exam.tone)"
                            >
                                <div class="flex items-start justify-between">
                                    <span class="ps-icon-tile">{{ exam.emoji }}</span>
                                    <div class="flex flex-col items-end gap-1">
                                        <span
                                            class="ps-sticker text-[11px]"
                                            :class="accesoClass(exam.acceso)"
                                        >
                                            {{ accesoLabel(exam.acceso) }}
                                        </span>
                                        <span
                                            v-if="isReviewExam(exam)"
                                            class="ps-sticker ps-sticker-sky text-[11px]"
                                        >
                                            Repaso
                                        </span>
                                    </div>
                                </div>

                                <h4 class="mt-5 text-xl font-semibold tracking-tight">
                                    {{ exam.name }}
                                </h4>
                                <p class="mt-1.5 line-clamp-2 text-sm leading-6 text-[#3d3848]/80">
                                    {{ exam.description }}
                                </p>

                                <div
                                    v-if="exam.subjects?.filter((subject) => subject !== section.name).length"
                                    class="mt-4 flex flex-wrap gap-1.5"
                                >
                                    <span
                                        v-for="subject in exam.subjects.filter((item) => item !== section.name).slice(0, 3)"
                                        :key="subject"
                                        class="ps-chip"
                                    >
                                        {{ subject }}
                                    </span>
                                </div>

                                <div class="mt-5 flex items-center justify-between gap-3">
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold text-[#3d3848]/70">
                                        <span v-if="exam.question_count">
                                            {{ exam.question_count }} preguntas
                                        </span>
                                        <span v-if="exam.duration_minutes">
                                            {{ exam.duration_minutes }} min
                                        </span>
                                    </div>
                                    <span class="ps-btn">Presentar</span>
                                </div>
                            </Link>
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-2xl border-2 border-dashed border-[var(--line)] bg-white/70 px-5 py-10 text-center"
                        >
                            <p class="text-sm font-semibold">Nada coincide con esa búsqueda</p>
                            <p class="mt-1 text-sm ps-muted">
                                Prueba con el nombre del examen o borra el filtro de {{ section.name }}.
                            </p>
                        </div>
                    </section>
                </div>

                <div
                    v-else
                    class="ps-panel px-6 py-16 text-center"
                >
                    <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-white/80 text-3xl">
                        📘
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">Aún no tienes exámenes</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 ps-muted">
                        Cuando te asignen acceso a un examen, aparecerá aquí agrupado por materia.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
