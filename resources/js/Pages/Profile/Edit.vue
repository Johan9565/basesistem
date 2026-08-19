<script setup>
import ProfileImageCropModal from '@/Components/ProfileImageCropModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { PROFILE_HIGHLIGHT_FIELDS_EVENT } from '@/composables/useNotificacionToUser';
import InputError from '@/Components/InputError.vue';
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
        };
    }
    return {
        name: u?.name ?? '—',
        ape_pat: u?.ape_pat ?? '—',
        ape_mat: u?.ape_mat ?? '—',
        email: u?.email ?? '—',
        avatar_url: null,
        banner_url: null,
    };
});

const user = computed(() => page.props.auth?.user);

function profileField(value) {
    const text = String(value ?? '').trim();
    return text === '—' ? '' : text;
}

const form = useForm({
    name: profileField(display.value.name),
    ape_pat: profileField(display.value.ape_pat),
    ape_mat: profileField(display.value.ape_mat),
    email: profileField(display.value.email),
});

const fullName = computed(() =>
    [form.name, form.ape_pat, form.ape_mat]
        .map((part) => String(part ?? '').trim())
        .filter(Boolean)
        .join(' ') || 'Tu perfil',
);

watch(display, (d) => {
    if (form.processing || form.isDirty) {
        return;
    }
    form.name = profileField(d.name);
    form.ape_pat = profileField(d.ape_pat);
    form.ape_mat = profileField(d.ape_mat);
    form.email = profileField(d.email);
    form.defaults({
        name: form.name,
        ape_pat: form.ape_pat,
        ape_mat: form.ape_mat,
        email: form.email,
    });
});

function submitProfile() {
    form.transform((data) => ({
        name: data.name,
        ape_pat: data.ape_pat,
        ape_mat: data.ape_mat,
        email: data.email,
    })).patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults({
                name: form.name,
                ape_pat: form.ape_pat,
                ape_mat: form.ape_mat,
                email: form.email,
            });
            form.reset();
        },
    });
}

const highlightedDisplayKeys = ref(new Set());
let highlightClearTimer;

function isFieldHighlighted(key) {
    return highlightedDisplayKeys.value.has(key);
}

function fieldHighlightClass(key) {
    return isFieldHighlighted(key)
        ? 'rounded-2xl ring-2 ring-[#7c5cff] ring-offset-2 ring-offset-[#fff4e4] p-1 -m-1 transition-shadow duration-300'
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
        background:
            'linear-gradient(135deg, #ffd0c4 0%, #ddd6ff 52%, #ffe7a3 100%)',
    };
});

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

        <template #header>
            <p class="ps-sticker ps-sticker-violet">Tu cuenta</p>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight">
                Perfil, <span class="bg-[linear-gradient(transparent_58%,#ddd6ff_58%)]">en bloques.</span>
            </h2>
            <p class="mt-2 max-w-xl text-base leading-7 ps-muted">
                Puedes actualizar tu nombre, apellidos y correo. Foto y portada también se cambian aquí.
            </p>
        </template>

        <div class="py-10">
            <div class="mx-auto grid max-w-6xl items-start gap-6 px-6 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="ps-panel overflow-hidden">
                    <div class="relative h-44 w-full" :style="bannerStyle">
                        <label
                            class="ps-btn-ghost absolute top-4 right-4 z-10 cursor-pointer gap-2 bg-white/95 px-3 py-1.5 text-xs shadow-[3px_3px_0_#17141f]"
                            title="Cambiar portada"
                        >
                            <input
                                type="file"
                                accept="image/*"
                                class="sr-only"
                                @change="onBannerChange"
                            />
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke-width="1.8"
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
                                    d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"
                                />
                            </svg>
                            Portada
                        </label>
                    </div>

                    <div class="px-6 pb-8 md:px-8">
                        <div class="-mt-14 flex flex-col items-center text-center">
                            <div
                                class="group relative h-28 w-28 overflow-hidden rounded-[1.75rem] border-[3px] border-[#17141f] bg-[#ffd0c4] shadow-[4px_4px_0_#17141f]"
                            >
                                <img
                                    v-if="display.avatar_url"
                                    :src="display.avatar_url"
                                    alt=""
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-3xl font-semibold text-[#17141f]"
                                >
                                    {{ avatarInitial }}
                                </div>
                                <label
                                    class="absolute inset-0 z-20 flex cursor-pointer flex-col items-center justify-center gap-1 bg-[#17141f]/55 text-center opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100"
                                    title="Cambiar foto de perfil"
                                >
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="sr-only"
                                        @change="onAvatarChange"
                                    />
                                    <svg
                                        class="h-6 w-6 text-white"
                                        fill="none"
                                        stroke-width="1.6"
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
                                            d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"
                                        />
                                    </svg>
                                    <span class="px-1 text-[11px] font-semibold leading-tight text-white">
                                        Cambiar foto
                                    </span>
                                </label>
                            </div>

                            <h3 class="mt-5 text-2xl font-semibold tracking-tight">
                                {{ fullName }}
                            </h3>
                            <p class="mt-1 text-sm ps-muted">{{ form.email || display.email || '—' }}</p>
                            <p class="mt-3 max-w-md text-xs leading-5 ps-muted">
                                Elige foto o portada: podrás encuadrar y ver una vista previa antes de guardar.
                            </p>
                        </div>

                        <p
                            v-if="errors.avatar || errors.banner"
                            class="mt-4 text-center text-xs font-medium text-[#ff6b4a]"
                        >
                            <span v-if="errors.avatar">{{ errors.avatar }}</span>
                            <span v-if="errors.avatar && errors.banner"> · </span>
                            <span v-if="errors.banner">{{ errors.banner }}</span>
                        </p>

                        <form class="mt-8 space-y-4" @submit.prevent="submitProfile">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div :class="fieldHighlightClass('name')">
                                    <div class="ps-card ps-card-static ps-tone-violet p-4">
                                        <label for="profile_name" class="text-xs font-semibold ps-muted">Nombre</label>
                                        <input
                                            id="profile_name"
                                            v-model="form.name"
                                            type="text"
                                            required
                                            maxlength="255"
                                            autocomplete="given-name"
                                            class="input input-bordered mt-2 w-full bg-white"
                                        />
                                        <InputError class="mt-2" :message="form.errors.name" />
                                    </div>
                                </div>
                                <div :class="fieldHighlightClass('ape_pat')">
                                    <div class="ps-card ps-card-static ps-tone-sun p-4">
                                        <label for="profile_ape_pat" class="text-xs font-semibold ps-muted">
                                            Apellido paterno
                                        </label>
                                        <input
                                            id="profile_ape_pat"
                                            v-model="form.ape_pat"
                                            type="text"
                                            required
                                            maxlength="255"
                                            autocomplete="family-name"
                                            class="input input-bordered mt-2 w-full bg-white"
                                        />
                                        <InputError class="mt-2" :message="form.errors.ape_pat" />
                                    </div>
                                </div>
                                <div :class="fieldHighlightClass('ape_mat')">
                                    <div class="ps-card ps-card-static ps-tone-mint p-4">
                                        <label for="profile_ape_mat" class="text-xs font-semibold ps-muted">
                                            Apellido materno
                                        </label>
                                        <input
                                            id="profile_ape_mat"
                                            v-model="form.ape_mat"
                                            type="text"
                                            required
                                            maxlength="255"
                                            autocomplete="additional-name"
                                            class="input input-bordered mt-2 w-full bg-white"
                                        />
                                        <InputError class="mt-2" :message="form.errors.ape_mat" />
                                    </div>
                                </div>
                                <div :class="fieldHighlightClass('email')">
                                    <div class="ps-card ps-card-static ps-tone-sky p-4">
                                        <label for="profile_email" class="text-xs font-semibold ps-muted">Correo</label>
                                        <input
                                            id="profile_email"
                                            v-model="form.email"
                                            type="email"
                                            required
                                            maxlength="255"
                                            autocomplete="email"
                                            class="input input-bordered mt-2 w-full bg-white"
                                        />
                                        <InputError class="mt-2" :message="form.errors.email" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <button
                                    type="submit"
                                    class="ps-btn w-full sm:w-auto"
                                    :disabled="form.processing"
                                >
                                    <span v-if="form.processing">Guardando…</span>
                                    <span v-else>Guardar datos</span>
                                </button>
                                <Transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0"
                                >
                                    <p
                                        v-if="form.recentlySuccessful"
                                        class="flex items-center gap-2 text-sm font-semibold"
                                    >
                                        <span class="ps-sticker ps-sticker-mint px-2 py-0.5 text-xs">Listo</span>
                                        Datos actualizados.
                                    </p>
                                </Transition>
                            </div>
                        </form>

                        <div
                            v-if="mustVerifyEmail && user && user.email_verified_at === null"
                            class="ps-card ps-card-static ps-tone-sun mt-6 p-4"
                        >
                            <p class="text-sm leading-6">
                                Tu correo aún no está verificado.
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="font-semibold underline decoration-[#17141f]/40 underline-offset-2 hover:decoration-[#17141f]"
                                >
                                    Reenviar correo de verificación
                                </Link>
                            </p>
                        </div>

                        <div
                            v-if="status"
                            class="ps-card ps-card-static ps-tone-mint mt-4 p-4 text-sm font-medium"
                        >
                            {{ status }}
                        </div>
                    </div>
                </div>

                <div class="lg:sticky lg:top-28">
                    <div class="ps-card ps-card-static ps-tone-coral p-6 md:p-8">
                        <UpdatePasswordForm embedded />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
