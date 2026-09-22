<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const page = usePage();

const props = defineProps({
    instances: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    instance_name: props.instances[0]?.instance_name ?? '',
    phone: '',
    text: '',
});

const instanceOptions = computed(() =>
    props.instances.map((i) => ({
        label: i.instance_name,
        value: i.instance_name,
    })),
);

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
        if (flash.type !== 'error') {
            form.reset('phone', 'text');
        }
    },
    { immediate: true },
);

function submit() {
    form.post(route('whatsapp.send.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Enviar mensaje WhatsApp" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-base-content">
                Enviar mensaje
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
                    <div class="border-b border-base-300 p-6">
                        <h3 class="text-lg font-medium text-base-content">
                            Mensaje de prueba
                        </h3>
                        <p class="mt-1 text-sm text-base-content/60">
                            Envía un texto vía Evolution API a un número
                            (código país, sin +).
                        </p>
                    </div>

                    <form class="space-y-4 p-6" @submit.prevent="submit">
                        <div>
                            <InputLabel value="Instancia" />
                            <Select
                                v-model="form.instance_name"
                                :options="instanceOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="mt-1 block w-full"
                                placeholder="Selecciona instancia"
                                :disabled="!instanceOptions.length"
                            />
                            <InputError
                                :message="form.errors.instance_name"
                                class="mt-2"
                            />
                            <p
                                v-if="!instanceOptions.length"
                                class="mt-2 text-sm text-warning"
                            >
                                No hay instancias activas. Crea una en Instancias.
                            </p>
                        </div>

                        <div>
                            <InputLabel value="Teléfono" />
                            <InputText
                                v-model="form.phone"
                                class="mt-1 block w-full"
                                placeholder="5219981234567"
                            />
                            <InputError :message="form.errors.phone" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel value="Mensaje" />
                            <Textarea
                                v-model="form.text"
                                class="mt-1 block w-full"
                                rows="5"
                                autoResize
                            />
                            <InputError :message="form.errors.text" class="mt-2" />
                        </div>

                        <div class="flex justify-end pt-2">
                            <Button
                                type="submit"
                                label="Enviar"
                                icon="pi pi-send"
                                :disabled="
                                    form.processing || !instanceOptions.length
                                "
                                :loading="form.processing"
                            />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
