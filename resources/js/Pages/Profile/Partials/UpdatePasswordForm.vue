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

const fieldClass = 'input input-bordered mt-2 w-full';

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
    props.embedded ? '' : 'ps-panel p-6 sm:p-8',
);

const passwordGridClass = computed(() =>
    props.embedded ? 'grid grid-cols-1 gap-4' : 'grid gap-4 sm:grid-cols-2',
);
</script>

<template>
    <section :class="sectionClass">
        <header class="mb-6">
            <p class="ps-sticker ps-sticker-sun text-xs">Seguridad</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                Cambiar contraseña
            </h2>
            <p class="mt-1 text-sm leading-6 ps-muted">
                Usa una clave nueva para tu acceso.
            </p>
        </header>

        <div class="mb-6 rounded-2xl border-2 border-[#17141f]/10 bg-white/80 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide">Requisitos</p>
            <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm leading-6 ps-muted">
                <li>Mínimo 8 caracteres</li>
                <li>Mayúscula y minúscula</li>
                <li>Al menos un número</li>
                <li>Al menos un carácter especial</li>
            </ul>
        </div>

        <form class="space-y-4" @submit.prevent="updatePassword">
            <div>
                <label for="current_password" class="text-sm font-semibold">
                    Contraseña actual
                </label>
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
                <div>
                    <label for="password" class="text-sm font-semibold">
                        Nueva contraseña
                    </label>
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
                <div>
                    <label for="password_confirmation" class="text-sm font-semibold">
                        Confirmar contraseña
                    </label>
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

            <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <button
                    type="submit"
                    class="ps-btn w-full sm:w-auto"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Guardando…</span>
                    <span v-else>Actualizar contraseña</span>
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
                        Contraseña actualizada.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
