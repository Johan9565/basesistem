<script setup>
import AuthCartesianLayout from '@/Layouts/AuthCartesianLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const inputClass =
    'w-full px-8 py-4 rounded-lg font-medium bg-base-200 border border-base-300 text-base-content placeholder:text-base-content/50 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 focus:bg-base-100';

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <AuthCartesianLayout divider-text="Iniciar sesión">
        <template #banner>
            <div
                v-if="status"
                class="mb-4 text-center text-sm font-medium text-success"
            >
                {{ status }}
            </div>
        </template>

        <form @submit.prevent="submit">
            <input
                id="email"
                v-model="form.email"
                :class="inputClass"
                type="email"
                name="email"
                placeholder="Email"
                required
                autofocus
                autocomplete="username"
            />
            <InputError class="mt-2" :message="form.errors.email" />

            <input
                id="password"
                v-model="form.password"
                :class="[inputClass, 'mt-5']"
                type="password"
                name="password"
                placeholder="Password"
                required
                autocomplete="current-password"
            />
            <InputError class="mt-2" :message="form.errors.password" />

            <label
                class="mt-4 flex cursor-pointer items-center gap-2 text-sm text-base-content/70"
            >
                <input
                    v-model="form.remember"
                    type="checkbox"
                    name="remember"
                    class="checkbox checkbox-sm checkbox-primary rounded border-base-300"
                />
                Remember me
            </label>

            <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="mt-3 block text-center text-sm text-primary underline decoration-dotted hover:brightness-110"
            >
                Forgot your password?
            </Link>

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
                <span class="ml-2"> Sign In </span>
            </button>
        </form>
    </AuthCartesianLayout>
</template>
