<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';

defineProps({
    instances: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Calendario WhatsApp" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-base-content">
                Calendario
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-6 text-sm text-base-content/60">
                    Citas guardadas en el sistema por instancia (antes de
                    sincronizar a Google).
                </p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="inst in instances"
                        :key="inst.instance_name"
                        class="border border-base-300 bg-base-100 p-5 shadow-sm sm:rounded-lg"
                    >
                        <h3 class="text-lg font-medium">{{ inst.instance_name }}</h3>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ inst.appointment_count }} cita{{
                                inst.appointment_count !== 1 ? 's' : ''
                            }}
                        </p>
                        <p
                            v-if="inst.next_summary"
                            class="mt-2 text-sm text-base-content/50"
                        >
                            Próxima: {{ inst.next_summary }}
                        </p>
                        <div class="mt-4">
                            <Link
                                :href="
                                    route('whatsapp.calendar.show', {
                                        instance: inst.instance_name,
                                    })
                                "
                            >
                                <Button
                                    label="Ver calendario"
                                    icon="pi pi-calendar"
                                    class="w-full"
                                    severity="secondary"
                                />
                            </Link>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!instances.length"
                    class="py-16 text-center text-sm text-base-content/60"
                >
                    No hay instancias.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
