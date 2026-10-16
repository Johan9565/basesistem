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

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="inst in instances"
                        :key="inst.instance_name"
                        class="border border-base-300 bg-base-100 p-5 shadow-sm sm:rounded-2xl transition-all hover:border-primary/40 hover:shadow-md flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div>
                                    <h3 class="text-lg font-bold text-base-content">{{ inst.instance_name }}</h3>
                                    <div v-if="inst.company_name" class="text-xs text-secondary flex items-center gap-1 mt-0.5">
                                        <i class="pi pi-briefcase text-[10px]"></i>
                                        {{ inst.company_name }}
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-md border border-primary/20 bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                    <i class="pi pi-building text-[10px]"></i>
                                    {{ inst.branch_name }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-base-content/70">
                                📅 {{ inst.appointment_count }} cita{{
                                    inst.appointment_count !== 1 ? 's' : ''
                                }} agendada{{ inst.appointment_count !== 1 ? 's' : '' }}
                            </p>
                            <p
                                v-if="inst.next_summary"
                                class="mt-2 text-xs text-base-content/60"
                            >
                                ⏳ Próxima: <span class="font-medium text-base-content">{{ inst.next_summary }}</span>
                            </p>
                        </div>
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
