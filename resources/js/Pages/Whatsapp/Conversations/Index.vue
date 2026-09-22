<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

defineProps({
    instances: {
        type: Array,
        default: () => [],
    },
});

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash?.message) return;
        toast.add({
            severity: flash.type === 'error' ? 'error' : 'success',
            summary: flash.type === 'error' ? 'Error' : 'Listo',
            detail: flash.message,
            life: 4000,
        });
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Conversaciones WhatsApp" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-base-content">
                Conversaciones
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <p class="text-sm text-base-content/60">
                        Elige una instancia para ver todos sus chats y mensajes.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="inst in instances"
                        :key="inst.instance_name"
                        class="flex flex-col justify-between border border-base-300 bg-base-100 p-5 shadow-sm sm:rounded-lg"
                    >
                        <div>
                            <h3 class="text-lg font-medium text-base-content">
                                {{ inst.instance_name }}
                            </h3>
                            <p class="mt-1 text-xs uppercase text-base-content/50">
                                {{ inst.status }}
                            </p>
                            <p class="mt-3 text-sm text-base-content/70">
                                {{ inst.thread_count }} chat{{
                                    inst.thread_count !== 1 ? 's' : ''
                                }}
                                · {{ inst.message_count }} mensaje{{
                                    inst.message_count !== 1 ? 's' : ''
                                }}
                            </p>
                            <p
                                v-if="inst.last_preview"
                                class="mt-2 line-clamp-2 text-sm text-base-content/50"
                            >
                                {{ inst.last_preview }}
                            </p>
                        </div>
                        <div class="mt-4">
                            <Link
                                :href="
                                    route('whatsapp.conversations.show', {
                                        instance: inst.instance_name,
                                    })
                                "
                            >
                                <Button
                                    label="Ver chats"
                                    icon="pi pi-comments"
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
                    No hay instancias. Crea una en WhatsApp → Instancias.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
