<script setup>
import AuthCartesianLayout from '@/Layouts/AuthCartesianLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Cuenta desactivada" />

    <AuthCartesianLayout divider-text="Acceso restringido">
        <div class="space-y-4 text-center">
            <h1 class="text-lg font-semibold text-base-content">
                Cuenta desactivada
            </h1>
            <p class="text-sm leading-relaxed text-base-content/70">
                Su usuario ha sido desactivado. Si cree que esto es un error,
                comuníquese con el administrador.
            </p>
            <div class="flex flex-col gap-2 pt-2 sm:flex-row sm:justify-center">
                <PrimaryButton type="button" @click="logout">
                    Cerrar sesión
                </PrimaryButton>
                <Link
                    href="/"
                    class="btn-secondary inline-flex items-center justify-center rounded-md border px-4 py-2 text-sm font-medium shadow-sm transition"
                >
                    Volver al inicio
                </Link>
            </div>
        </div>
    </AuthCartesianLayout>
</template>
