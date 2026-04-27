<script setup>
import ProfileImageCropModal from '@/Components/ProfileImageCropModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { PROFILE_HIGHLIGHT_FIELDS_EVENT } from '@/composables/useNotificacionToUser';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    profileDisplay: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const errors = computed(() => page.props.errors ?? {});

const display = computed(() => {
    const p = props.profileDisplay;
    const u = page.props.auth?.user;
    if (p) {
        return {
            ...p,
            avatar_url: p.avatar_url ?? null,
            banner_url: p.banner_url ?? null,
            settings: p.settings ?? null,
        };
    }
    return {
        name: u?.name ?? '—',
        ape_pat: u?.ape_pat ?? '—',
        ape_mat: u?.ape_mat ?? '—',
        email: u?.email ?? '—',
        status: '—',
        avatar_url: null,
        banner_url: null,
        settings: u?.settings ?? null,
    };
});

const user = computed(() => page.props.auth?.user);

const settingsFromServer = computed(() => display.value.settings ?? {});
const form = useForm({
    name: user.value?.name ?? '',
    ape_pat: user.value?.ape_pat ?? '',
    ape_mat: user.value?.ape_mat ?? '',
    email: user.value?.email ?? '',
    settings: {
        working_hours: settingsFromServer.value?.working_hours ?? {},
        services: settingsFromServer.value?.services ?? [],
    },
});

const dayOrder = [
    { key: 'monday', label: 'Lunes' },
    { key: 'tuesday', label: 'Martes' },
    { key: 'wednesday', label: 'Miércoles' },
    { key: 'thursday', label: 'Jueves' },
    { key: 'friday', label: 'Viernes' },
    { key: 'saturday', label: 'Sábado' },
    { key: 'sunday', label: 'Domingo' },
];

function normalizeWorkingHours(raw) {
    const src = raw && typeof raw === 'object' ? raw : {};
    const out = {};
    for (const d of dayOrder) {
        const v = src?.[d.key];
        if (!v) {
            out[d.key] = { enabled: false, start: '09:00', end: '18:00' };
        } else {
            out[d.key] = {
                enabled: true,
                start: String(v.start ?? '09:00'),
                end: String(v.end ?? '18:00'),
            };
        }
    }
    return out;
}

function denormalizeWorkingHours(ui) {
    const out = {};
    const src = ui && typeof ui === 'object' ? ui : {};
    for (const d of dayOrder) {
        const v = src?.[d.key];
        if (!v || !v.enabled) {
            out[d.key] = null;
            continue;
        }
        out[d.key] = { start: String(v.start || '09:00'), end: String(v.end || '18:00') };
    }
    return out;
}

const workingHoursUi = ref(normalizeWorkingHours(settingsFromServer.value?.working_hours));

function normalizeServices(raw) {
    if (!Array.isArray(raw)) return [];
    return raw.map((s) => ({
        name: String(s?.name ?? ''),
        duration_minutes:
            s?.duration_minutes === 0 || s?.duration_minutes ? Number(s.duration_minutes) : 60,
        price: s?.price === 0 || s?.price ? Number(s.price) : null,
        currency: String(s?.currency ?? 'MXN'),
    }));
}

function addService() {
    form.settings.services = [
        ...(Array.isArray(form.settings.services) ? form.settings.services : []),
        { name: '', duration_minutes: 60, price: null, currency: 'MXN' },
    ];
}

function removeService(idx) {
    const arr = Array.isArray(form.settings.services) ? [...form.settings.services] : [];
    arr.splice(idx, 1);
    form.settings.services = arr;
}

watch(
    () => settingsFromServer.value,
    (s) => {
        // Solo refrescamos cuando el formulario aún no fue tocado, para evitar pisar edición en curso.
        if (form.isDirty) return;
        form.settings = {
            working_hours: s?.working_hours ?? {},
            services: normalizeServices(s?.services ?? []),
        };
        workingHoursUi.value = normalizeWorkingHours(s?.working_hours ?? {});
    },
    { deep: true },
);

function submitProfile() {
    form.settings = {
        ...(form.settings ?? {}),
        working_hours: denormalizeWorkingHours(workingHoursUi.value),
    };

    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
}

const highlightedDisplayKeys = ref(new Set());
let highlightClearTimer;

function isFieldHighlighted(key) {
    return highlightedDisplayKeys.value.has(key);
}

function fieldHighlightClass(key) {
    return isFieldHighlighted(key)
        ? 'rounded-lg ring-2 ring-amber-400 ring-offset-2 ring-offset-white p-1 -m-1 transition-shadow duration-300 dark:ring-amber-300 dark:ring-offset-gray-900'
        : '';
}

function onProfileFieldsHighlight(event) {
    const keys = event.detail?.keys ?? [];
    clearTimeout(highlightClearTimer);
    highlightedDisplayKeys.value = new Set(keys);
    highlightClearTimer = setTimeout(() => {
        highlightedDisplayKeys.value = new Set();
    }, 5500);
}

onMounted(() => {
    window.addEventListener(PROFILE_HIGHLIGHT_FIELDS_EVENT, onProfileFieldsHighlight);
});

onUnmounted(() => {
    window.removeEventListener(PROFILE_HIGHLIGHT_FIELDS_EVENT, onProfileFieldsHighlight);
    clearTimeout(highlightClearTimer);
});

const avatarInitial = computed(() => {
    const n = (display.value.name || '').trim();
    return n ? n.charAt(0).toUpperCase() : '?';
});

/** Portada: imagen o color sólido si no hay banner en BD */
const bannerStyle = computed(() => {
    const url = display.value.banner_url;
    if (url) {
        return {
            backgroundImage: `url("${url}")`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            backgroundRepeat: 'no-repeat',
        };
    }
    return {
        backgroundColor: '#475569',
    };
});

const inputReadonlyClass =
    'mt-2 px-4 py-2 w-full border-2 rounded-lg dark:text-gray-200 dark:border-gray-600 dark:bg-gray-800/80 bg-gray-50 text-gray-800 border-gray-300 cursor-default focus:outline-none focus:ring-0';

const inputEditableClass =
    'mt-2 px-4 py-2 w-full border-2 rounded-lg dark:text-gray-200 dark:border-gray-600 dark:bg-gray-800/80 bg-white text-gray-800 border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-400';

const cardClass =
    'rounded-xl shadow-2xl p-4 sm:p-6 h-fit dark:bg-gray-800/40 border border-gray-200/50 dark:border-gray-700/50';

/** Recorte antes de subir (misma proporción que banner 3:1 o avatar 1:1). */
const cropVisible = ref(false);
const cropSrc = ref('');
const cropAspect = ref(3);
const cropKind = ref('banner');
const cropTitle = computed(() =>
    cropKind.value === 'banner' ? 'Ajustar portada' : 'Ajustar foto de perfil',
);

watch(cropVisible, (v) => {
    if (!v) {
        cropSrc.value = '';
    }
});

function readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

async function onAvatarChange(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
        return;
    }
    try {
        cropSrc.value = await readFileAsDataUrl(file);
        cropAspect.value = 1;
        cropKind.value = 'avatar';
        cropVisible.value = true;
    } catch {
        /* ignore */
    }
}

async function onBannerChange(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
        return;
    }
    try {
        cropSrc.value = await readFileAsDataUrl(file);
        cropAspect.value = 3;
        cropKind.value = 'banner';
        cropVisible.value = true;
    } catch {
        /* ignore */
    }
}

function onCropApplied(file) {
    if (cropKind.value === 'banner') {
        router.post(
            route('profile.banner'),
            { banner: file },
            { forceFormData: true, preserveScroll: true },
        );
    } else {
        router.post(
            route('profile.avatar'),
            { avatar: file },
            { forceFormData: true, preserveScroll: true },
        );
    }
    cropSrc.value = '';
}
</script>

<template>
    <Head title="Perfil" />

    <AuthenticatedLayout>
        <ProfileImageCropModal
            v-model:visible="cropVisible"
            :image-src="cropSrc"
            :aspect-ratio="cropAspect"
            :kind="cropKind"
            :title="cropTitle"
            @applied="onCropApplied"
        />

        <section class="py-6 sm:py-8">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header tipo red social: cover + avatar + acciones -->
                <div class="overflow-hidden rounded-2xl border border-gray-200/60 bg-white shadow-sm dark:border-gray-700/60 dark:bg-gray-900/30">
                    <div class="relative h-44 sm:h-56" :style="bannerStyle">
                        <div class="absolute inset-0 bg-linear-to-t from-black/45 via-black/15 to-transparent" />

                        <div class="absolute right-3 top-3">
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-lg bg-white/95 px-3 py-2 text-xs font-semibold text-gray-800 shadow-sm ring-1 ring-gray-200 transition hover:bg-white dark:bg-gray-800/95 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-800"
                                title="Cambiar portada"
                            >
                                <span>Editar portada</span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="sr-only"
                                    @change="onBannerChange"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="relative px-4 pb-4 sm:px-6">
                        <div class="-mt-12 flex flex-col gap-4 sm:-mt-14 sm:flex-row sm:items-end sm:justify-between">
                            <div class="flex items-end gap-4">
                                <div class="group relative h-24 w-24 overflow-hidden rounded-full bg-slate-600 shadow-lg ring-4 ring-white dark:ring-gray-900 sm:h-28 sm:w-28">
                                    <img
                                        v-if="display.avatar_url"
                                        :src="display.avatar_url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-indigo-600 text-3xl font-bold text-white sm:text-4xl"
                                    >
                                        {{ avatarInitial }}
                                    </div>

                                    <label
                                        class="absolute inset-0 z-20 flex cursor-pointer flex-col items-center justify-center gap-1 bg-black/55 text-center opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100 active:opacity-100"
                                        title="Cambiar foto de perfil"
                                    >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="sr-only"
                                            @change="onAvatarChange"
                                        />
                                        <span class="px-2 text-[11px] font-semibold leading-tight text-white">
                                            Cambiar foto
                                        </span>
                                    </label>
                                </div>

                                <div class="pb-1">
                                    <h1 class="text-lg font-extrabold text-gray-900 dark:text-white sm:text-2xl">
                                        {{ form.name || 'Tu perfil' }}
                                    </h1>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ display.status || '—' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    @click="submitProfile"
                                    :disabled="form.processing"
                                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                                >
                                    Guardar
                                </button>
                                <span v-if="form.recentlySuccessful" class="text-xs text-green-700 dark:text-green-300">
                                    Guardado.
                                </span>
                            </div>
                        </div>

                        <p
                            v-if="errors.avatar || errors.banner"
                            class="mt-4 text-center text-xs text-red-600 dark:text-red-400"
                        >
                            <span v-if="errors.avatar">{{ errors.avatar }}</span>
                            <span v-if="errors.avatar && errors.banner"> · </span>
                            <span v-if="errors.banner">{{ errors.banner }}</span>
                        </p>
                    </div>
                </div>

                <!-- Layout tipo red social: sidebar + feed -->
                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <!-- Sidebar -->
                    <aside class="lg:col-span-4 space-y-6">
                        <div class="rounded-2xl border border-gray-200/60 bg-white p-4 shadow-sm dark:border-gray-700/60 dark:bg-gray-900/30">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Información</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Edita tu nombre y correo (como en una red social).
                            </p>

                            <div class="mt-4 space-y-3">
                                <div :class="fieldHighlightClass('name')">
                                    <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Nombre</label>
                                    <input type="text" v-model="form.name" :class="[inputEditableClass, 'mt-0!']" />
                                    <p v-if="form.errors.name" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                                </div>
                                <div :class="fieldHighlightClass('ape_pat')">
                                    <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Apellido paterno</label>
                                    <input type="text" v-model="form.ape_pat" :class="[inputEditableClass, 'mt-0!']" />
                                    <p v-if="form.errors.ape_pat" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ form.errors.ape_pat }}</p>
                                </div>
                                <div :class="fieldHighlightClass('ape_mat')">
                                    <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Apellido materno</label>
                                    <input type="text" v-model="form.ape_mat" :class="[inputEditableClass, 'mt-0!']" />
                                    <p v-if="form.errors.ape_mat" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ form.errors.ape_mat }}</p>
                                </div>
                                <div :class="fieldHighlightClass('email')">
                                    <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Correo</label>
                                    <input type="email" v-model="form.email" :class="[inputEditableClass, 'mt-0!']" />
                                    <p v-if="form.errors.email" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <div
                                v-if="mustVerifyEmail && user && user.email_verified_at === null"
                                class="mt-4 rounded-lg border border-amber-200/80 bg-amber-50/90 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/30"
                            >
                                <p class="text-xs text-gray-800 dark:text-gray-200">
                                    Tu correo aún no está verificado.
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="rounded-md text-xs font-medium text-amber-800 underline hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-100"
                                    >
                                        Reenviar verificación
                                    </Link>
                                </p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200/60 bg-white p-4 shadow-sm dark:border-gray-700/60 dark:bg-gray-900/30">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Seguridad</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Cambia tu contraseña.
                            </p>
                            <div class="mt-4">
                                <UpdatePasswordForm embedded />
                            </div>
                        </div>
                    </aside>

                    <!-- Feed -->
                    <main class="lg:col-span-8 space-y-6">
                        <div class="rounded-2xl border border-gray-200/60 bg-white p-4 shadow-sm dark:border-gray-700/60 dark:bg-gray-900/30">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Horarios</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Define por día si atiendes y en qué horario.
                            </p>

                            <div class="mt-4 space-y-2">
                                <div
                                    v-for="d in dayOrder"
                                    :key="d.key"
                                    class="flex flex-col sm:flex-row sm:items-center gap-2 rounded-xl border border-gray-200/70 dark:border-gray-700/70 bg-white/60 dark:bg-gray-900/20 p-3"
                                >
                                    <div class="flex items-center justify-between sm:justify-start sm:w-44">
                                        <span class="text-sm font-medium dark:text-gray-200">{{ d.label }}</span>
                                        <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 sm:hidden">
                                            <input type="checkbox" v-model="workingHoursUi[d.key].enabled" class="rounded border-gray-300 dark:border-gray-600" />
                                            Disponible
                                        </label>
                                    </div>

                                    <div class="hidden sm:flex items-center gap-2 sm:w-40">
                                        <input type="checkbox" v-model="workingHoursUi[d.key].enabled" class="rounded border-gray-300 dark:border-gray-600" />
                                        <span class="text-xs text-gray-600 dark:text-gray-400">Disponible</span>
                                    </div>

                                    <div class="flex items-center gap-2 flex-1">
                                        <input
                                            type="time"
                                            v-model="workingHoursUi[d.key].start"
                                            :disabled="!workingHoursUi[d.key].enabled"
                                            :class="[inputEditableClass, 'mt-0!', 'py-1.5', 'text-sm']"
                                        />
                                        <span class="text-xs text-gray-500 dark:text-gray-400">a</span>
                                        <input
                                            type="time"
                                            v-model="workingHoursUi[d.key].end"
                                            :disabled="!workingHoursUi[d.key].enabled"
                                            :class="[inputEditableClass, 'mt-0!', 'py-1.5', 'text-sm']"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200/60 bg-white p-4 shadow-sm dark:border-gray-700/60 dark:bg-gray-900/30">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Servicios</h2>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Agrega los servicios que ofreces.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                                    @click="addService"
                                >
                                    Agregar
                                </button>
                            </div>

                            <div v-if="(form.settings.services ?? []).length === 0" class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                No hay servicios aún.
                            </div>

                            <div v-else class="mt-4 space-y-3">
                                <div
                                    v-for="(svc, idx) in form.settings.services"
                                    :key="idx"
                                    class="rounded-xl border border-gray-200/70 dark:border-gray-700/70 bg-white/60 dark:bg-gray-900/20 p-4"
                                >
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                        <div class="sm:col-span-5">
                                            <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Nombre</label>
                                            <input v-model="svc.name" type="text" :class="[inputEditableClass, 'mt-0!', 'py-2', 'text-sm']" placeholder="Sesión de retrato" />
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Duración (min)</label>
                                            <input v-model.number="svc.duration_minutes" type="number" min="5" step="5" :class="[inputEditableClass, 'mt-0!', 'py-2', 'text-sm']" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Precio</label>
                                            <input v-model.number="svc.price" type="number" min="0" step="0.01" :class="[inputEditableClass, 'mt-0!', 'py-2', 'text-sm']" placeholder="0" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 dark:text-gray-300 text-xs font-medium block">Moneda</label>
                                            <select v-model="svc.currency" :class="[inputEditableClass, 'mt-0!', 'py-2', 'text-sm']">
                                                <option value="MXN">MXN</option>
                                                <option value="USD">USD</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex justify-end">
                                        <button
                                            type="button"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30"
                                            @click="removeService(idx)"
                                        >
                                            Quitar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p v-if="form.errors.settings" class="mt-3 text-xs text-red-600 dark:text-red-400">
                                {{ form.errors.settings }}
                            </p>
                        </div>

                        <div
                            v-if="status"
                            class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                        >
                            {{ status }}
                        </div>
                    </main>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
