<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const mobileOpen = ref(false);
const currentYear = new Date().getFullYear();

const modules = [
    {
        title: 'Temario guiado',
        text: 'Bloques por materia con lo esencial de norma, doctrina y jurisprudencia.',
        tone: 'violet',
        icon: 'book',
    },
    {
        title: 'Casos y doctrina',
        text: 'Lecturas cortas y casos tipo examen para entender el criterio, no solo memorizar.',
        tone: 'mint',
        icon: 'case',
    },
    {
        title: 'Simulacros',
        text: 'Práctica con tiempo y revisión de errores para llegar al examen con método.',
        tone: 'coral',
        icon: 'timer',
    },
];

const subjects = [
    { title: 'Constitucional', text: 'Principios, derechos y control.', tone: 'violet', emoji: '⚖️' },
    { title: 'Civil', text: 'Personas, contratos y bienes.', tone: 'sky', emoji: '🏠' },
    { title: 'Penal', text: 'Delito, tipos y proceso.', tone: 'coral', emoji: '🔍' },
    { title: 'Administrativo', text: 'Acto, procedimiento y Estado.', tone: 'mint', emoji: '🏛️' },
    { title: 'Laboral', text: 'Trabajo, derechos y conflictos.', tone: 'sun', emoji: '🤝' },
    { title: 'Amparo', text: 'Procedencia y sentencias.', tone: 'pink', emoji: '🛡️' },
];

const steps = [
    {
        number: '01',
        title: 'Diagnóstico',
        text: 'Mira qué materias ya llevas y dónde está el hueco.',
        tone: 'violet',
    },
    {
        number: '02',
        title: 'Bloques cortos',
        text: 'Norma, doctrina y un caso. Sin maratones inútiles.',
        tone: 'mint',
    },
    {
        number: '03',
        title: 'Simulacro',
        text: 'Pon el timer, resuelve, revisa errores y repite.',
        tone: 'coral',
    },
];
</script>

<template>
    <Head title="pa-saber · Guía de estudio">
        <meta
            name="description"
            content="pa-saber: guía de estudio para el examen de derecho. Temario por materias, doctrina, jurisprudencia y simulacros."
        />
    </Head>

    <div class="welcome min-h-screen overflow-x-hidden font-sans antialiased">
        <div class="blob blob-a" aria-hidden="true"></div>
        <div class="blob blob-b" aria-hidden="true"></div>
        <div class="blob blob-c" aria-hidden="true"></div>

        <header class="sticky top-0 z-30 border-b border-[#eadfd2]/80 bg-[#fff7ef]/85 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                <a href="#inicio" class="flex min-w-0 items-center gap-2">
                    <ApplicationLogo class="h-11 w-auto shrink-0" />
                    <span class="truncate text-lg font-semibold tracking-tight">pa-saber</span>
                </a>

                <nav class="hidden items-center gap-6 text-sm text-[#5c5668] md:flex">
                    <a href="#contenido" class="hover:text-[#17141f]">Contenido</a>
                    <a href="#materias" class="hover:text-[#17141f]">Materias</a>
                    <a href="#pasos" class="hover:text-[#17141f]">Cómo estudiar</a>
                    <Link
                        v-if="canLogin && $page.props.auth.user"
                        :href="route('dashboard')"
                        class="btn-fun"
                    >
                        Ir al panel
                    </Link>
                    <template v-else-if="canLogin">
                        <Link :href="route('login')" class="hover:text-[#17141f]">Iniciar sesión</Link>
                        <Link v-if="canRegister" :href="route('register')" class="btn-fun">Crear cuenta</Link>
                    </template>
                </nav>

                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-xl border border-[#eadfd2] md:hidden"
                    :aria-expanded="mobileOpen"
                    aria-controls="mobile-nav"
                    @click="mobileOpen = !mobileOpen"
                >
                    <span class="sr-only">Abrir menú</span>
                    <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current" stroke-width="1.8">
                        <path v-if="!mobileOpen" d="M4 7h16M4 12h16M4 17h16" />
                        <path v-else d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <div v-if="mobileOpen" id="mobile-nav" class="border-t border-[#eadfd2] px-6 py-4 text-sm md:hidden">
                <div class="flex flex-col gap-3">
                    <a href="#contenido" @click="mobileOpen = false">Contenido</a>
                    <a href="#materias" @click="mobileOpen = false">Materias</a>
                    <a href="#pasos" @click="mobileOpen = false">Cómo estudiar</a>
                    <Link
                        v-if="canLogin && $page.props.auth.user"
                        :href="route('dashboard')"
                        class="btn-fun mt-1 text-center"
                    >
                        Ir al panel
                    </Link>
                    <template v-else-if="canLogin">
                        <Link :href="route('login')">Iniciar sesión</Link>
                        <Link v-if="canRegister" :href="route('register')" class="btn-fun text-center">Crear cuenta</Link>
                    </template>
                </div>
            </div>
        </header>

        <main>
            <section id="inicio" class="relative">
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-14 lg:grid-cols-[1.05fr_0.95fr] lg:py-20">
                    <div>

                        <p class="sticker sticker-sun inline-flex items-center gap-2 text-sm font-semibold">
                            <span class="size-2 rounded-full bg-[#17141f]"></span>
                            Para estudiar el examen sin drama
                        </p>
                        <h1 class="mt-5 max-w-xl text-4xl font-semibold tracking-tight text-pretty sm:text-5xl">
                            Derecho claro,
                            <span class="mark-violet">en bloques</span>
                            que sí se pueden terminar.
                        </h1>
                        <p class="mt-5 max-w-lg text-lg leading-8 text-[#5c5668]">
                            Temario, casos y simulacros. Menos PDF eterno, más práctica que se siente como progreso.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <Link
                                v-if="canLogin && $page.props.auth.user"
                                :href="route('dashboard')"
                                class="btn-fun btn-fun-lg"
                            >
                                Seguir estudiando
                            </Link>
                            <template v-else>
                                <Link v-if="canRegister" :href="route('register')" class="btn-fun btn-fun-lg">
                                    Empezar gratis
                                </Link>
                                <Link
                                    v-else-if="canLogin"
                                    :href="route('login')"
                                    class="btn-fun btn-fun-lg"
                                >
                                    Iniciar sesión
                                </Link>
                            </template>
                            <a href="#contenido" class="btn-ghost">Ver qué incluye</a>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-2">
                            <span class="chip">⚡ Simulacros</span>
                            <span class="chip">📚 6 materias</span>
                            <span class="chip">✅ Casos cortos</span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-md">
                        <div class="float-card card-sun -left-4 -top-5 absolute z-10 hidden rotate-[-8deg] px-3 py-2 text-sm font-semibold sm:block">
                            Civil · 12/20
                        </div>
                        <div class="float-card card-mint -right-2 top-16 absolute z-10 hidden rotate-[7deg] px-3 py-2 text-sm font-semibold sm:block">
                            Racha 4 días
                        </div>

                        <div class="quiz-card relative overflow-hidden rounded-[28px] p-5 shadow-xl">
                            <div class="flex items-center justify-between text-sm font-semibold">
                                <span>Simulacro rápido</span>
                                <span class="rounded-full bg-white/70 px-3 py-1">12 / 40</span>
                            </div>
                            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/50">
                                <div class="h-full w-[30%] rounded-full bg-[#7c5cff]"></div>
                            </div>
                            <p class="mt-6 text-lg font-semibold leading-7">
                                En amparo, el principio de definitividad implica que…
                            </p>
                            <div class="mt-5 space-y-2">
                                <div class="option">A. Se agoten recursos ordinarios</div>
                                <div class="option option-ok">B. Basta un agravio directo</div>
                                <div class="option">C. Solo aplica en penal</div>
                            </div>
                            <div class="mt-6 flex items-center justify-between text-sm">
                                <span class="font-semibold">⏱ 00:42</span>
                                <span class="rounded-full bg-[#2ecf8f] px-3 py-1 font-bold text-[#113326]">Siguiente →</span>
                            </div>
                        </div>

                        <img
                            src="/images/brand/pa-saber-mark.png"
                            alt=""
                            class="mascot-float absolute -bottom-10 -left-8 w-28 sm:w-32"
                        />
                    </div>
                </div>

                <div class="marquee border-y border-[#eadfd2] bg-white/50 py-3" aria-hidden="true">
                    <div class="marquee-track">
                        <span v-for="n in 2" :key="n" class="flex gap-8 px-8 text-sm font-semibold text-[#5c5668]">
                            <span v-for="subject in subjects" :key="`${n}-${subject.title}`">
                                {{ subject.emoji }} {{ subject.title }}
                            </span>
                        </span>
                    </div>
                </div>
            </section>

            <section id="contenido" class="px-6 py-16 md:py-20">
                <div class="mx-auto max-w-6xl">
                    <p class="sticker sticker-violet inline-flex text-sm font-semibold">Qué trae la guía</p>
                    <h2 class="mt-4 max-w-xl text-3xl font-semibold tracking-tight">
                        Tres piezas. <span class="mark-sun">Cero relleno.</span>
                    </h2>
                    <p class="mt-3 max-w-xl text-base leading-7 text-[#5c5668]">
                        Qué estudiar, cómo entenderlo y cómo comprobar que ya te quedó.
                    </p>

                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <article
                            v-for="item in modules"
                            :key="item.title"
                            class="module-card relative overflow-hidden rounded-[28px] p-6"
                            :class="`card-${item.tone}`"
                        >
                            <div class="mb-8 grid size-14 place-items-center rounded-2xl bg-white/70">
                                <svg v-if="item.icon === 'book'" viewBox="0 0 48 48" class="size-8">
                                    <rect x="8" y="10" width="32" height="28" rx="6" fill="#17141f" />
                                    <path d="M14 16h20M14 22h14" stroke="#fff" stroke-width="3" stroke-linecap="round" />
                                </svg>
                                <svg v-else-if="item.icon === 'case'" viewBox="0 0 48 48" class="size-8">
                                    <circle cx="24" cy="24" r="16" fill="#17141f" />
                                    <path d="M16 24h16M24 16v16" stroke="#fff" stroke-width="3" stroke-linecap="round" />
                                </svg>
                                <svg v-else viewBox="0 0 48 48" class="size-8">
                                    <circle cx="24" cy="24" r="16" fill="#17141f" />
                                    <path d="M24 16v9l6 4" stroke="#fff" stroke-width="3" stroke-linecap="round" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold tracking-tight">{{ item.title }}</h3>
                            <p class="mt-3 text-sm leading-6 text-[#3d3848]/80">{{ item.text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="materias" class="px-6 pb-16 md:pb-20">
                <div class="mx-auto max-w-6xl">
                    <p class="sticker sticker-mint inline-flex text-sm font-semibold">Materias</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight">El examen, en colores.</h2>
                    <p class="mt-3 max-w-xl text-base leading-7 text-[#5c5668]">
                        Entra por la que más te cuesta. Cada una tiene su propio bloque.
                    </p>

                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="subject in subjects"
                            :key="subject.title"
                            class="subject-card rounded-[24px] p-5"
                            :class="`card-${subject.tone}`"
                        >
                            <div class="flex items-start justify-between">
                                <span class="grid size-12 place-items-center rounded-2xl bg-white/75 text-2xl">
                                    {{ subject.emoji }}
                                </span>
                                <span class="rotate-12 text-xs font-bold opacity-70">materia</span>
                            </div>
                            <h3 class="mt-5 text-xl font-semibold tracking-tight">{{ subject.title }}</h3>
                            <p class="mt-1.5 text-sm leading-6 text-[#3d3848]/80">{{ subject.text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="pasos" class="px-6 pb-16 md:pb-20">
                <div class="mx-auto max-w-6xl rounded-[32px] bg-[#17141f] px-6 py-12 text-white md:px-10 md:py-16">
                    <p class="text-sm font-semibold text-[#ffc857]">Cómo se estudia aquí</p>
                    <h2 class="mt-3 max-w-lg text-3xl font-semibold tracking-tight">
                        Tres pasos. Sin rituales raros.
                    </h2>
                    <ol class="mt-10 grid gap-5 md:grid-cols-3">
                        <li
                            v-for="step in steps"
                            :key="step.number"
                            class="rounded-[24px] bg-white/8 p-6 ring-1 ring-white/10"
                        >
                            <p class="text-3xl font-semibold" :class="`num-${step.tone}`">{{ step.number }}</p>
                            <h3 class="mt-4 text-xl font-semibold">{{ step.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-white/70">{{ step.text }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="px-6 pb-16 md:pb-20">
                <div class="cta-band mx-auto flex max-w-6xl flex-col gap-6 overflow-hidden rounded-[32px] px-6 py-10 md:flex-row md:items-center md:justify-between md:px-10">
                    <div class="relative z-10">
                        <h2 class="text-2xl font-semibold tracking-tight md:text-3xl">
                            Hoy un bloque. Mañana otro.
                        </h2>
                        <p class="mt-2 max-w-md text-sm leading-6 text-[#3d3848]/80">
                            Empieza por una materia y deja el PDF infinito para otro día.
                        </p>
                    </div>
                    <div class="relative z-10 flex flex-wrap gap-3">
                        <Link
                            v-if="canLogin && $page.props.auth.user"
                            :href="route('dashboard')"
                            class="rounded-full bg-[#17141f] px-6 py-3 text-sm font-semibold text-white"
                        >
                            Ir al panel
                        </Link>
                        <template v-else>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-full bg-[#17141f] px-6 py-3 text-sm font-semibold text-white"
                            >
                                Crear cuenta
                            </Link>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="rounded-full bg-white/70 px-6 py-3 text-sm font-semibold"
                            >
                                Ya tengo cuenta
                            </Link>
                        </template>
                    </div>
                    <svg class="pointer-events-none absolute -right-6 -top-8 h-40 w-40 opacity-80" viewBox="0 0 160 160" aria-hidden="true">
                        <circle cx="80" cy="80" r="54" fill="#ff6b4a" />
                        <circle cx="118" cy="48" r="22" fill="#7c5cff" />
                        <rect x="28" y="96" width="46" height="28" rx="10" fill="#fff" transform="rotate(-12 28 96)" />
                    </svg>
                </div>
            </section>
        </main>

        <footer class="border-t border-[#eadfd2] bg-white/40">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-6 py-8 text-sm text-[#5c5668] sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2">
                    <img src="/images/brand/pa-saber-mark.png" alt="" class="h-7 w-auto" />
                    <span>© {{ currentYear }} pa-saber</span>
                </p>
                <div class="flex gap-5">
                    <Link v-if="canLogin" :href="route('login')" class="hover:text-[#17141f]">Iniciar sesión</Link>
                    <Link v-if="canRegister" :href="route('register')" class="hover:text-[#17141f]">Crear cuenta</Link>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.welcome {
    background:
        radial-gradient(circle at 12% 8%, #ffe3c4 0%, transparent 32%),
        radial-gradient(circle at 92% 18%, #ddd6ff 0%, transparent 28%),
        radial-gradient(circle at 80% 88%, #c8f5e2 0%, transparent 26%),
        #fff7ef;
    color: #17141f;
}

.blob {
    position: fixed;
    z-index: 0;
    pointer-events: none;
    filter: blur(8px);
    opacity: 0.55;
}

.blob-a {
    top: 8rem;
    left: -4rem;
    width: 16rem;
    height: 16rem;
    border-radius: 40% 60% 70% 30%;
    background: #ffc857;
    animation: drift 12s ease-in-out infinite;
}

.blob-b {
    top: 28rem;
    right: -5rem;
    width: 18rem;
    height: 18rem;
    border-radius: 60% 40% 30% 70%;
    background: #c9b8ff;
    animation: drift 15s ease-in-out infinite reverse;
}

.blob-c {
    bottom: 6rem;
    left: 20%;
    width: 12rem;
    height: 12rem;
    border-radius: 50%;
    background: #9cf0cc;
    animation: drift 18s ease-in-out infinite;
}

.mascot-float {
    animation: bob 4.2s ease-in-out infinite;
    filter: drop-shadow(4px 6px 0 #17141f);
}

.logo-mark {
    background: linear-gradient(135deg, #7c5cff, #ff6b4a);
}

.btn-fun {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #ff6b4a;
    color: #fff;
    padding: 0.55rem 1.05rem;
    font-weight: 600;
    box-shadow: 3px 3px 0 #17141f;
}

.btn-fun-lg {
    padding: 0.8rem 1.3rem;
}

.btn-fun:hover {
    transform: translate(-1px, -1px);
    box-shadow: 4px 4px 0 #17141f;
}

.btn-ghost {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 2px solid #17141f;
    padding: 0.7rem 1.15rem;
    font-size: 0.875rem;
    font-weight: 600;
    background: #fff;
}

.sticker {
    border-radius: 999px;
    padding: 0.35rem 0.8rem;
}

.sticker-sun { background: #ffc857; }
.sticker-violet { background: #ddd6ff; }
.sticker-mint { background: #c8f5e2; }

.mark-violet {
    background: linear-gradient(transparent 58%, #ddd6ff 58%);
}

.mark-sun {
    background: linear-gradient(transparent 58%, #ffc857 58%);
}

.chip {
    border-radius: 999px;
    background: #fff;
    border: 1.5px solid #eadfd2;
    padding: 0.3rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.quiz-card {
    background: linear-gradient(180deg, #fff 0%, #fff4e4 100%);
    border: 3px solid #17141f;
}

.option {
    border-radius: 1rem;
    border: 2px solid #eadfd2;
    background: #fff;
    padding: 0.7rem 0.9rem;
    font-size: 0.9rem;
    font-weight: 600;
}

.option-ok {
    border-color: #2ecf8f;
    background: #e7fff4;
}

.float-card {
    border: 2px solid #17141f;
    border-radius: 1rem;
    animation: bob 3.8s ease-in-out infinite;
}

.card-sun { background: #ffe7a3; }
.card-mint { background: #c8f5e2; }
.card-violet { background: #ddd6ff; }
.card-coral { background: #ffd0c4; }
.card-sky { background: #caf1ff; }
.card-pink { background: #ffd4ea; }

.module-card,
.subject-card {
    border: 2px solid #17141f;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.module-card:hover,
.subject-card:hover {
    transform: translateY(-4px) rotate(-0.4deg);
    box-shadow: 6px 6px 0 #17141f;
}

.num-violet { color: #c9b8ff; }
.num-mint { color: #9cf0cc; }
.num-coral { color: #ffb39f; }

.cta-band {
    position: relative;
    background: #ffc857;
    border: 3px solid #17141f;
}

.marquee {
    overflow: hidden;
}

.marquee-track {
    display: flex;
    width: max-content;
    animation: scroll 22s linear infinite;
}

@keyframes scroll {
    from { transform: translateX(0); }
    to { transform: translateX(-50%); }
}

@keyframes drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(18px, -14px) scale(1.08); }
}

@keyframes bob {
    0%, 100% { transform: translateY(0) rotate(var(--r, -8deg)); }
    50% { transform: translateY(-7px) rotate(var(--r, -8deg)); }
}

header,
main,
footer {
    position: relative;
    z-index: 1;
}

@media (prefers-reduced-motion: reduce) {
    .blob,
    .float-card,
    .mascot-float,
    .marquee-track,
    .module-card,
    .subject-card,
    .btn-fun {
        animation: none !important;
        transition: none !important;
    }
}
</style>
