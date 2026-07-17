<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canLogin: Boolean,
});

const page = usePage();

const paletteStyle = computed(() => {
    const palette = page.props.landingPalette ?? {};
    return Object.fromEntries(
        Object.entries(palette).filter(([, value]) => typeof value === 'string' && value !== ''),
    );
});

const services = [
    {
        number: '01',
        title: 'Consulta general',
        text: 'Revisiones completas, diagnóstico y planes de salud personalizados para cada etapa de su vida.',
        icon: 'M6 3v5a4 4 0 0 0 8 0V3M4 3h4m4 0h4m-6 9v3a5 5 0 0 0 10 0v-1',
    },
    {
        number: '02',
        title: 'Medicina preventiva',
        text: 'Vacunación, desparasitación y chequeos periódicos para mantenerlos sanos y felices.',
        icon: 'M12 3 4.5 6v5.5c0 4.7 3.2 8 7.5 9.5 4.3-1.5 7.5-4.8 7.5-9.5V6L12 3Zm-3.5 9 2.2 2.2 4.8-5',
    },
    {
        number: '03',
        title: 'Cirugía y hospitalización',
        text: 'Procedimientos seguros, monitoreo continuo y acompañamiento durante su recuperación.',
        icon: 'M20.8 5.8a5.5 5.5 0 0 0-7.8 0L12 6.9l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 22l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z',
    },
];
</script>

<template>
    <Head title="Small Animal Clinic | Veterinaria en Cancún">
        <meta name="description" content="Atención veterinaria cálida y profesional para perros y gatos en Cancún." />
    </Head>

    <div class="landing min-h-screen overflow-x-hidden font-sans" :style="paletteStyle">
        <header class="absolute inset-x-0 top-0 z-30">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-10">
                <a href="#" class="flex items-center gap-3 text-white">
                    <span class="grid size-11 place-items-center rounded-full bg-white/15 text-2xl backdrop-blur-md">🐾</span>
                    <span>
                        <span class="block font-serif text-xl font-semibold leading-none">Small Animal</span>
                        <span class="mt-1 block text-[10px] font-bold uppercase tracking-[0.34em] text-white/70">Clinic · Cancún</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-8 text-sm font-medium text-white/85 lg:flex">
                    <a href="#servicios" class="transition hover:text-white">Servicios</a>
                    <a href="#nosotros" class="transition hover:text-white">Nosotros</a>
                    <a href="#contacto" class="transition hover:text-white">Contacto</a>
                    <Link
                        v-if="canLogin && $page.props.auth.user"
                        :href="route('dashboard')"
                        class="transition hover:text-white"
                    >
                        Panel
                    </Link>
                    <Link v-else-if="canLogin" :href="route('login')" class="transition hover:text-white">Acceso</Link>
                    <a href="#contacto" class="landing-cta rounded-full px-6 py-3 font-bold shadow-lg transition hover:-translate-y-0.5">
                        Agendar cita
                    </a>
                </nav>
                <a href="#contacto" class="landing-cta rounded-full px-4 py-2.5 text-xs font-bold lg:hidden">Agendar cita</a>
            </div>
        </header>

        <main>
            <section class="landing-hero relative flex min-h-[760px] items-center overflow-hidden">
                <img
                    src="https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=1800&q=85"
                    alt="Perro feliz atendido por Small Animal Clinic"
                    class="absolute inset-0 size-full object-cover object-[68%_center]"
                />
                <div class="landing-hero-overlay absolute inset-0"></div>
                <div class="absolute inset-0 bg-linear-to-t from-black/40 via-transparent to-black/10"></div>

                <div class="relative mx-auto w-full max-w-7xl px-6 pb-20 pt-40 lg:px-10">
                    <div class="max-w-2xl">
                        <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-white backdrop-blur-md">
                            <span class="landing-dot size-2 rounded-full"></span>
                            Cuidado veterinario en Cancún
                        </div>
                        <h1 class="font-serif text-5xl font-semibold leading-[1.02] text-white sm:text-6xl lg:text-[78px]">
                            Cuidamos a quienes
                            <span class="landing-highlight italic">hacen familia.</span>
                        </h1>
                        <p class="mt-7 max-w-xl text-lg leading-8 text-white/80">
                            Medicina veterinaria cercana, honesta y de alta calidad para perros y gatos. Porque su bienestar también es el tuyo.
                        </p>
                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            <a href="#contacto" class="landing-cta rounded-full px-7 py-4 text-sm font-bold shadow-xl transition hover:-translate-y-1">
                                Agenda una consulta
                            </a>
                            <a href="#servicios" class="group flex items-center gap-3 px-3 py-4 text-sm font-bold text-white">
                                Conoce nuestros servicios
                                <span class="grid size-8 place-items-center rounded-full border border-white/30 transition group-hover:translate-x-1">→</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="landing-rating absolute bottom-0 right-0 hidden rounded-tl-[36px] px-10 py-7 lg:block">
                    <div class="flex items-center gap-4">
                        <div class="landing-stars text-xl tracking-wider">★★★★★</div>
                        <div>
                            <p class="landing-ink font-serif text-xl font-semibold">4.9 / 5</p>
                            <p class="landing-muted text-xs">Familias que confían en nosotros</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="servicios" class="landing-section px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                        <div>
                            <p class="landing-eyebrow text-xs font-bold uppercase tracking-[0.25em]">Nuestros servicios</p>
                            <h2 class="landing-ink mt-4 max-w-2xl font-serif text-4xl font-semibold leading-tight sm:text-5xl">
                                Todo lo que necesitan,
                                <span class="landing-soft-title italic">en un mismo lugar.</span>
                            </h2>
                        </div>
                        <p class="landing-muted max-w-md text-base leading-7">
                            Combinamos experiencia, tecnología y un trato amoroso para cuidar la salud de tu compañero.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-5 md:grid-cols-3">
                        <article
                            v-for="service in services"
                            :key="service.title"
                            class="landing-card group rounded-[28px] border bg-white p-8 transition duration-300 hover:-translate-y-2 hover:border-transparent hover:shadow-2xl"
                        >
                            <div class="flex items-start justify-between">
                                <span class="landing-icon grid size-14 place-items-center rounded-2xl transition">
                                    <svg viewBox="0 0 24 24" class="size-7 fill-none stroke-current" stroke-width="1.7">
                                        <path :d="service.icon" />
                                    </svg>
                                </span>
                                <span class="landing-number font-serif text-3xl">{{ service.number }}</span>
                            </div>
                            <h3 class="landing-ink mt-8 font-serif text-2xl font-semibold">{{ service.title }}</h3>
                            <p class="landing-muted mt-4 text-sm leading-7">{{ service.text }}</p>
                            <a href="#contacto" class="landing-link mt-7 inline-flex items-center gap-2 text-sm font-bold">
                                Saber más <span class="transition group-hover:translate-x-1">→</span>
                            </a>
                        </article>
                    </div>
                </div>
            </section>

            <section id="nosotros" class="landing-surface px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-2 lg:gap-24">
                    <div class="relative mx-auto w-full max-w-xl">
                        <div class="overflow-hidden rounded-[38px]">
                            <img
                                src="https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?auto=format&fit=crop&w=1000&q=85"
                                alt="Veterinaria examinando a un perro"
                                class="aspect-4/5 w-full object-cover"
                            />
                        </div>
                        <div class="landing-badge absolute -bottom-7 -right-3 max-w-[220px] rounded-[24px] p-6 shadow-xl lg:-right-10">
                            <p class="font-serif text-4xl font-semibold">+10</p>
                            <p class="mt-1 text-sm font-bold leading-5">años cuidando mascotas en Cancún</p>
                        </div>
                    </div>

                    <div>
                        <p class="landing-eyebrow text-xs font-bold uppercase tracking-[0.25em]">Sobre nosotros</p>
                        <h2 class="landing-ink mt-4 font-serif text-4xl font-semibold leading-tight sm:text-5xl">
                            Ciencia, experiencia y
                            <span class="landing-soft-title italic">mucho corazón.</span>
                        </h2>
                        <p class="landing-muted mt-7 text-base leading-8">
                            En Small Animal Clinic creemos que una buena consulta comienza escuchando. Nos tomamos el tiempo de conocer a tu mascota, resolver tus dudas y explicarte cada paso con claridad.
                        </p>
                        <div class="mt-9 space-y-5">
                            <div
                                v-for="item in ['Atención personalizada y sin prisas', 'Equipo médico profesional y actualizado', 'Espacios cómodos para perros y gatos']"
                                :key="item"
                                class="flex items-center gap-4"
                            >
                                <span class="landing-check grid size-7 shrink-0 place-items-center rounded-full text-sm text-white">✓</span>
                                <span class="landing-ink text-sm font-semibold">{{ item }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="landing-stats px-6 py-20 text-white lg:px-10">
                <div class="mx-auto grid max-w-7xl gap-10 text-center sm:grid-cols-3">
                    <div>
                        <p class="landing-highlight font-serif text-5xl font-semibold">+5,000</p>
                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.2em] text-white/65">Pacientes atendidos</p>
                    </div>
                    <div class="border-white/15 sm:border-x">
                        <p class="landing-highlight font-serif text-5xl font-semibold">4.9/5</p>
                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.2em] text-white/65">Satisfacción de familias</p>
                    </div>
                    <div>
                        <p class="landing-highlight font-serif text-5xl font-semibold">6 días</p>
                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.2em] text-white/65">Atención a la semana</p>
                    </div>
                </div>
            </section>

            <section class="landing-section px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-4xl text-center">
                    <div class="landing-stars text-2xl tracking-[0.3em]">★★★★★</div>
                    <blockquote class="landing-ink mt-8 font-serif text-3xl font-medium leading-snug sm:text-4xl">
                        “Desde que llegamos nos hicieron sentir tranquilos. Trataron a Milo con muchísimo cariño y nos explicaron todo de forma muy clara.”
                    </blockquote>
                    <p class="landing-ink mt-8 text-sm font-bold">Mariana & Milo · Familia Small Animal</p>
                </div>
            </section>

            <section id="contacto" class="landing-section px-6 pb-24 lg:px-10 lg:pb-32">
                <div class="landing-cta-band relative mx-auto max-w-7xl overflow-hidden rounded-[36px] px-7 py-14 sm:px-12 lg:px-20 lg:py-20">
                    <div class="absolute -right-10 -top-14 text-[180px] opacity-15">🐾</div>
                    <div class="relative flex flex-col justify-between gap-10 lg:flex-row lg:items-center">
                        <div class="max-w-2xl">
                            <p class="landing-eyebrow text-xs font-bold uppercase tracking-[0.24em]">Tu mascota cuenta contigo</p>
                            <h2 class="landing-ink mt-4 font-serif text-4xl font-semibold leading-tight sm:text-5xl">Hagamos equipo por su bienestar.</h2>
                            <p class="landing-muted mt-5 text-base">Agenda una consulta con nuestro equipo veterinario en Cancún.</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="tel:+529981234567" class="landing-primary-btn rounded-full px-7 py-4 text-sm font-bold text-white shadow-lg transition hover:-translate-y-1">Llamar ahora</a>
                            <a href="https://wa.me/529981234567" class="landing-outline-btn rounded-full border px-7 py-4 text-sm font-bold transition">WhatsApp</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="landing-footer px-6 pb-8 pt-16 text-white/70 lg:px-10">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-12 border-b border-white/10 pb-12 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <p class="font-serif text-xl font-semibold text-white">🐾 Small Animal Clinic</p>
                        <p class="mt-5 max-w-sm text-sm leading-7 text-white/55">Atención veterinaria profesional y humana para los miembros más pequeños de tu familia.</p>
                    </div>
                    <div>
                        <p class="landing-highlight text-xs font-bold uppercase tracking-[0.2em]">Visítanos</p>
                        <p class="mt-5 text-sm leading-7">Cancún, Quintana Roo<br />México</p>
                    </div>
                    <div>
                        <p class="landing-highlight text-xs font-bold uppercase tracking-[0.2em]">Horario</p>
                        <p class="mt-5 text-sm leading-7">Lun – Vie: 9:00 – 19:00<br />Sábados: 9:00 – 15:00</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 pt-7 text-xs text-white/40 sm:flex-row sm:justify-between">
                    <p>© 2026 Small Animal Clinic. Todos los derechos reservados.</p>
                    <p>Hecho con cariño para las mascotas de Cancún.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500&display=swap');

.font-sans {
    font-family: 'DM Sans', sans-serif;
}

.font-serif {
    font-family: 'Playfair Display', serif;
}

.landing {
    background: var(--landing-bg, #f3f7fc);
    color: var(--landing-ink, #0c2440);
}

.landing-cta {
    background: var(--landing-cta, #60a5fa);
    color: var(--landing-cta-text, #0c2440);
}

.landing-cta:hover {
    filter: brightness(1.06);
}

.landing-hero {
    background: var(--landing-primary, #1a4b8c);
}

.landing-hero-overlay {
    background: linear-gradient(
        to right,
        color-mix(in srgb, var(--landing-hero-from, #0f3a6e) 96%, transparent),
        color-mix(in srgb, var(--landing-hero-from, #0f3a6e) 78%, transparent),
        color-mix(in srgb, var(--landing-hero-from, #0f3a6e) 18%, transparent)
    );
}

.landing-dot,
.landing-stars,
.landing-highlight {
    color: var(--landing-accent, #93c5fd);
}

.landing-ink {
    color: var(--landing-ink, #0c2440);
}

.landing-muted {
    color: var(--landing-muted, #5b738c);
}

.landing-eyebrow,
.landing-link {
    color: var(--landing-accent-strong, #2563eb);
}

.landing-soft-title {
    color: color-mix(in srgb, var(--landing-primary, #1a4b8c) 72%, white);
}

.landing-rating,
.landing-section {
    background: var(--landing-bg, #f3f7fc);
}

.landing-card {
    border-color: var(--landing-border, #c5d8ef);
}

.landing-card:hover {
    box-shadow: 0 24px 50px color-mix(in srgb, var(--landing-primary, #1a4b8c) 16%, transparent);
}

.landing-icon {
    background: var(--landing-primary-soft, #dbeafe);
    color: var(--landing-primary, #1a4b8c);
}

.landing-card:hover .landing-icon {
    background: var(--landing-primary, #1a4b8c);
    color: white;
}

.landing-number {
    color: color-mix(in srgb, var(--landing-primary-soft, #dbeafe) 70%, white);
}

.landing-surface {
    background: var(--landing-surface, #e8f1fb);
}

.landing-badge {
    background: var(--landing-cta, #60a5fa);
    color: var(--landing-cta-text, #0c2440);
}

.landing-check,
.landing-stats,
.landing-primary-btn {
    background: var(--landing-primary, #1a4b8c);
}

.landing-cta-band {
    background: var(--landing-cta, #60a5fa);
}

.landing-outline-btn {
    border-color: color-mix(in srgb, var(--landing-ink, #0c2440) 35%, transparent);
    background: color-mix(in srgb, white 25%, transparent);
    color: var(--landing-ink, #0c2440);
}

.landing-outline-btn:hover {
    background: color-mix(in srgb, white 50%, transparent);
}

.landing-footer {
    background: var(--landing-footer, #0a1f38);
}
</style>
