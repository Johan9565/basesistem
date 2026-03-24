<script setup>
import ProfileImageCropModal from '@/Components/ProfileImageCropModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { PROFILE_HIGHLIGHT_FIELDS_EVENT } from '@/composables/useNotificacionToUser';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
        };
    }
    return {
        name: u?.name ?? '—',
        ape_pat: u?.ape_pat ?? '—',
        ape_mat: u?.ape_mat ?? '—',
        email: u?.email ?? '—',
        role: page.props.auth?.role ?? '—',
        area: '—',
        status: '—',
        avatar_url: null,
        banner_url: null,
    };
});

const user = computed(() => page.props.auth?.user);

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

        <section class="py-8 my-auto sm:py-10">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-2 lg:gap-10">
                    <!-- Columna: perfil -->
                    <div :class="cardClass">
                        <div class="py-2">
                            <h1 class="lg:text-3xl md:text-2xl text-xl font-extrabold mb-2 dark:text-white">
                                Perfil
                            </h1>
                            <h2 class="text-grey text-sm mb-4 dark:text-gray-400">
                                Información de tu cuenta (solo lectura)
                            </h2>

                            <div
                                class="w-full rounded-lg min-h-[140px] relative overflow-hidden"
                                :style="bannerStyle"
                            >
                                <div
                                    class="group relative z-10 mx-auto -mb-[70px] flex h-[141px] w-[141px] justify-center overflow-hidden rounded-full bg-slate-600 shadow-lg ring-4 ring-white/90 dark:ring-gray-800/90"
                                >
                                    <img
                                        v-if="display.avatar_url"
                                        :src="display.avatar_url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-indigo-600 text-4xl font-bold text-white"
                                    >
                                        {{ avatarInitial }}
                                    </div>
                                    <label
                                        class="absolute inset-0 z-20 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-full bg-black/55 text-center opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100 active:opacity-100"
                                        title="Cambiar foto de perfil"
                                    >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="sr-only"
                                            @change="onAvatarChange"
                                        />
                                        <svg
                                            class="h-7 w-7 text-white"
                                            fill="none"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"
                                            />
                                        </svg>
                                        <span class="px-1 text-[11px] font-semibold leading-tight text-white">
                                            Cambiar foto
                                        </span>
                                    </label>
                                </div>
                                <div class="flex justify-end pt-2 pb-1">
                                    <label
                                        class="flex cursor-pointer items-center gap-2 rounded-tl-md bg-white/95 px-3 py-1.5 text-sm font-semibold text-gray-800 shadow-sm ring-1 ring-gray-200 transition hover:bg-white dark:bg-gray-800/95 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-800"
                                        title="Cambiar portada"
                                    >
                                        <span>Portada</span>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="sr-only"
                                            @change="onBannerChange"
                                        />
                                        <svg
                                            class="h-5 w-5 text-blue-700 dark:text-blue-400"
                                            fill="none"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"
                                            />
                                        </svg>
                                    </label>
                                </div>
                            </div>

                            <p
                                v-if="errors.avatar || errors.banner"
                                class="mt-3 text-center text-xs text-red-600 dark:text-red-400"
                            >
                                <span v-if="errors.avatar">{{ errors.avatar }}</span>
                                <span v-if="errors.avatar && errors.banner"> · </span>
                                <span v-if="errors.banner">{{ errors.banner }}</span>
                            </p>

                            <h2 class="text-center mt-20 sm:mt-16 font-semibold dark:text-gray-300 text-sm px-2">
                                Elige foto o portada: podrás encuadrar y ver una vista previa antes de guardar.
                            </h2>

                            <div class="flex flex-col lg:flex-row gap-2 justify-center w-full">
                                <div
                                    class="w-full mb-4 mt-6"
                                    :class="fieldHighlightClass('name')"
                                >
                                    <label class="mb-2 dark:text-gray-300 text-sm font-medium block">Nombre</label>
                                    <input
                                        type="text"
                                        readonly
                                        tabindex="-1"
                                        :value="display.name || '—'"
                                        :class="inputReadonlyClass"
                                    />
                                </div>
                                <div
                                    class="w-full mb-4 lg:mt-6"
                                    :class="fieldHighlightClass('ape_pat')"
                                >
                                    <label class="mb-2 dark:text-gray-300 text-sm font-medium block">
                                        Apellido paterno
                                    </label>
                                    <input
                                        type="text"
                                        readonly
                                        tabindex="-1"
                                        :value="display.ape_pat || '—'"
                                        :class="inputReadonlyClass"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-col lg:flex-row gap-2 justify-center w-full">
                                <div
                                    class="w-full mb-4"
                                    :class="fieldHighlightClass('ape_mat')"
                                >
                                    <label class="mb-2 dark:text-gray-300 text-sm font-medium block">
                                        Apellido materno
                                    </label>
                                    <input
                                        type="text"
                                        readonly
                                        tabindex="-1"
                                        :value="display.ape_mat || '—'"
                                        :class="inputReadonlyClass"
                                    />
                                </div>
                                <div
                                    class="w-full mb-4"
                                    :class="fieldHighlightClass('email')"
                                >
                                    <label class="mb-2 dark:text-gray-300 text-sm font-medium block">Correo</label>
                                    <input
                                        type="text"
                                        readonly
                                        tabindex="-1"
                                        :value="display.email || '—'"
                                        :class="inputReadonlyClass"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-col lg:flex-row gap-2 justify-center w-full">
                                <div
                                    class="w-full mb-4"
                                    :class="fieldHighlightClass('role')"
                                >
                                    <h3 class="dark:text-gray-300 mb-2 text-sm font-medium">Rol</h3>
                                    <div :class="[inputReadonlyClass, 'mt-0!']">
                                        {{ display.role || '—' }}
                                    </div>
                                </div>
                                <div
                                    class="w-full mb-4"
                                    :class="fieldHighlightClass('area')"
                                >
                                    <h3 class="dark:text-gray-300 mb-2 text-sm font-medium">Área</h3>
                                    <div :class="[inputReadonlyClass, 'mt-0!']">
                                        {{ display.area || '—' }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="w-full mb-2"
                                :class="fieldHighlightClass('status')"
                            >
                                <h3 class="dark:text-gray-300 mb-2 text-sm font-medium">Estado</h3>
                                <div :class="[inputReadonlyClass, 'mt-0!']">
                                    {{ display.status || '—' }}
                                </div>
                            </div>

                            <div
                                v-if="mustVerifyEmail && user && user.email_verified_at === null"
                                class="rounded-lg border border-amber-200/80 bg-amber-50/90 px-4 py-3 mt-6 dark:border-amber-900/50 dark:bg-amber-950/30"
                            >
                                <p class="text-sm text-gray-800 dark:text-gray-200">
                                    Tu correo aún no está verificado.
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="rounded-md text-sm font-medium text-amber-800 underline hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-100"
                                    >
                                        Reenviar correo de verificación
                                    </Link>
                                </p>
                            </div>

                            <div
                                v-if="status"
                                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 mt-4 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                            >
                                {{ status }}
                            </div>
                        </div>
                    </div>

                    <div class="lg:sticky lg:top-24">
                        <div :class="cardClass">
                            <UpdatePasswordForm embedded />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
