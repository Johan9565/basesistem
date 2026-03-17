<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    users: Array,
});
</script>

<template>
    <Head title="Usuarios del Sistema" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Usuarios del Sistema
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">

                    <!-- Header tabla -->
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ users.length }} usuario{{ users.length !== 1 ? 's' : '' }} registrado{{ users.length !== 1 ? 's' : '' }}
                        </p>
                    </div>

                    <!-- Tabla -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Nombre
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Email
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Rol
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Estado
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                <tr v-if="users.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                                <tr
                                    v-for="user in users"
                                    :key="user.id"
                                    class="transition hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300">
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ user.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                            {{ user.role }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="user.status == 1
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        >
                                            {{ user.status == 1 ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
