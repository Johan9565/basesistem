<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Sin marco propio: el contenedor padre aporta la tarjeta (p. ej. layout en dos columnas). */
    embedded: {
        type: Boolean,
        default: false,
    },
});

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const fieldClass =
    'mt-2 block w-full px-4 py-2.5 border-2 rounded-lg transition-colors ' +
    'bg-white text-gray-900 border-gray-300 placeholder:text-gray-400 ' +
    'focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/25 ' +
    'dark:bg-gray-800/90 dark:text-gray-100 dark:border-gray-600 dark:placeholder:text-gray-500 ' +
    'dark:focus:border-blue-400 dark:focus:ring-blue-400/20';

const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300';

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};

const sectionClass = computed(() =>
    props.embedded
        ? ''
        : 'rounded-xl border-2 border-gray-200/90 bg-linear-to-b from-white to-gray-50/80 p-6 shadow-inner dark:border-gray-600/60 dark:from-gray-800/50 dark:to-gray-900/40 sm:p-8',
);

const headerFlexClass = computed(() =>
    props.embedded
        ? 'flex flex-col gap-3'
        : 'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
);

const passwordGridClass = computed(() =>
    props.embedded ? 'grid grid-cols-1 gap-5' : 'grid gap-5 sm:grid-cols-2',
);
</script>

<template>
    <section :class="sectionClass">
        <header class="mb-8 border-b border-gray-200/80 pb-6 dark:border-gray-600/50">
            <div :class="headerFlexClass">
                <div>
                    <h2
                        class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-3xl"
                    >
                        Seguridad
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Cambiar contraseña de acceso
                    </p>
                </div>
                <div
                    class="shrink-0 rounded-lg border border-blue-200/80 bg-blue-50/90 px-3 py-2 text-xs text-blue-900 dark:border-blue-800/50 dark:bg-blue-950/40 dark:text-blue-100"
                >
                    <p class="font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200">
                        Requisitos
                    </p>
                    <ul class="mt-2 list-inside list-disc space-y-0.5 text-blue-900/90 dark:text-blue-100/90">
                        <li>Mínimo 8 caracteres</li>
                        <li>Mayúscula y minúscula</li>
                        <li>Al menos un número</li>
                        <li>Al menos un carácter especial</li>
                    </ul>
                </div>
            </div>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-5">
            <div>
                <label for="current_password" :class="labelClass">Contraseña actual</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    :class="fieldClass"
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.current_password" class="mt-2" />
            </div>

            <div :class="passwordGridClass">
                <div class="sm:col-span-1">
                    <label for="password" :class="labelClass">Nueva contraseña</label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        :class="fieldClass"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>
                <div class="sm:col-span-1">
                    <label for="password_confirmation" :class="labelClass">Confirmar contraseña</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        :class="fieldClass"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-gray-200/80 pt-6 dark:border-gray-600/50 sm:flex-row sm:items-center sm:justify-between"
            >
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-blue-500 px-6 py-3.5 text-base font-semibold text-white shadow-md transition hover:bg-blue-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-gray-900 sm:w-auto sm:min-w-[200px]"
                >
                    <span v-if="form.processing">Guardando…</span>
                    <span v-else>Actualizar contraseña</span>
                </button>

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400"
                    >
                        <span
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                            aria-hidden="true"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2.5"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </span>
                        Contraseña actualizada correctamente.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
