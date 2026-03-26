<script setup>
import AuthCartesianLayout from '@/Layouts/AuthCartesianLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
    password_confirmation: '',
});

const inputClass =
    'w-full px-8 py-4 rounded-lg font-medium bg-base-200 border border-base-300 text-base-content placeholder:text-base-content/50 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 focus:bg-base-100';

const submit = () => {
    form.post(route('account.password-required.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Cambiar contraseña" />

    <AuthCartesianLayout divider-text="Establezca su nueva contraseña">
        <template #banner>
            <div class="mb-6 mx-auto max-w-xs text-center sm:text-left">
                <h1 class="text-lg font-semibold text-base-content">
                    Debe actualizar su contraseña
                </h1>
                <p class="mt-2 text-sm text-base-content/70">
                    Por seguridad, establezca una contraseña nueva antes de
                    continuar.
                </p>
                <ul
                    class="mt-3 list-inside list-disc text-left text-xs text-base-content/60"
                >
                    <li>Mínimo 8 caracteres</li>
                    <li>Al menos una mayúscula y una minúscula</li>
                    <li>Al menos un número</li>
                    <li>Al menos un símbolo</li>
                </ul>
            </div>
        </template>

        <form @submit.prevent="submit">
            <input
                id="password"
                v-model="form.password"
                :class="inputClass"
                type="password"
                name="password"
                placeholder="Nueva contraseña"
                required
                autocomplete="new-password"
                autofocus
            />
            <InputError class="mt-2" :message="form.errors.password" />

            <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                :class="[inputClass, 'mt-5']"
                type="password"
                name="password_confirmation"
                placeholder="Confirmar contraseña"
                required
                autocomplete="new-password"
            />
            <InputError
                class="mt-2"
                :message="form.errors.password_confirmation"
            />

            <button
                type="submit"
                class="btn-primary mt-5 flex w-full items-center justify-center gap-2 rounded-lg py-4 text-sm font-semibold tracking-wide transition duration-300 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-[var(--btn-primary-ring)] focus:ring-offset-2 focus:ring-offset-base-100 disabled:opacity-25"
                :disabled="form.processing"
            >
                <svg
                    class="w-6 h-6 -ml-2"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                    <circle cx="8.5" cy="7" r="4" />
                    <path d="M20 8v6M23 11h-6" />
                </svg>
                <span class="ml-2"> Guardar y continuar </span>
            </button>
        </form>

        <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="btn-secondary mt-4 flex w-full items-center justify-center rounded-lg border px-4 py-3 text-sm font-semibold transition duration-150 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-[var(--input-focus-ring)] focus:ring-offset-2 focus:ring-offset-base-100"
        >
            Volver a la página principal
        </Link>
    </AuthCartesianLayout>
</template>
