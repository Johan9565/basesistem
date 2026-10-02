<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';
import Select from 'primevue/select';

const props = defineProps({
    companies: Array,
    clients: Array,
});

const dialogVisible = ref(false);
const isEditing = ref(false);
const editingCompanyId = ref(null);

const form = useForm({
    client_id: null,
    name: '',
    document_number: '',
    email: '',
    phone: '',
    status: 'active',
    modules: {
        inventory: false,
        services: false,
        appointments: false,
        whatsapp: true,
    },
});

function openCreateDialog() {
    isEditing.value = false;
    editingCompanyId.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    form.modules = {
        inventory: true,
        services: true,
        appointments: true,
        whatsapp: true,
    };
    if (props.clients && props.clients.length) {
        form.client_id = props.clients[0].id;
    }
    dialogVisible.value = true;
}

function openEditDialog(company) {
    isEditing.value = true;
    editingCompanyId.value = company.id;
    form.reset();
    form.clearErrors();
    form.name = company.name ?? '';
    form.document_number = company.document_number ?? '';
    form.email = company.email ?? '';
    form.phone = company.phone ?? '';
    form.status = company.status ?? 'active';
    dialogVisible.value = true;
}

function submitForm() {
    if (isEditing.value) {
        form.patch(route('companies.update', editingCompanyId.value), {
            onSuccess: () => { dialogVisible.value = false; },
        });
    } else {
        form.post(route('companies.store'), {
            onSuccess: () => { dialogVisible.value = false; },
        });
    }
}

function toggleCompanyModule(company, moduleKey, event) {
    const isChecked = event;
    router.post(route('companies.toggle-module', company.id), {
        module: moduleKey,
        enabled: isChecked,
    }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Gestión de Empresas y Módulos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-base-content">
                        🏢 Empresas y Módulos Habilitados
                    </h2>
                    <p class="text-xs text-base-content/60">
                        Administra las empresas asociadas a la cuenta y activa o desactiva módulos individualmente en tiempo real.
                    </p>
                </div>
                <Button
                    label="Nueva Empresa"
                    icon="pi pi-plus"
                    size="small"
                    severity="primary"
                    @click="openCreateDialog"
                />
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Grid de Tarjetas de Empresas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="company in companies"
                        :key="company.id"
                        class="flex flex-col justify-between rounded-2xl border border-base-300 bg-base-100 p-6 shadow-xl transition-all duration-200 hover:border-primary/40 hover:shadow-2xl"
                    >
                        <div>
                            <!-- Header de Empresa -->
                            <div class="flex items-start justify-between gap-2 mb-4">
                                <div>
                                    <h3 class="text-base font-bold text-base-content">{{ company.name }}</h3>
                                    <div class="text-xs text-base-content/50 font-mono">{{ company.slug }}</div>
                                </div>
                                <span
                                    class="badge badge-sm"
                                    :class="company.status === 'active' ? 'badge-success' : 'badge-ghost'"
                                >
                                    {{ company.status === 'active' ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>

                            <div class="text-xs text-base-content/70 space-y-1 mb-6 border-b border-base-300 pb-4">
                                <div v-if="company.email" class="flex items-center gap-1.5">
                                    <i class="pi pi-envelope text-[11px]"></i>
                                    {{ company.email }}
                                </div>
                                <div v-if="company.phone" class="flex items-center gap-1.5">
                                    <i class="pi pi-phone text-[11px]"></i>
                                    {{ company.phone }}
                                </div>
                                <div class="text-[11px] text-base-content/40">
                                    Cliente: {{ company.client_name }}
                                </div>
                            </div>

                            <!-- Interruptores de Módulos -->
                            <div class="space-y-3">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/40">
                                    Módulos de la Empresa
                                </div>

                                <!-- WhatsApp CRM -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-emerald-500 font-bold text-xs">📱 WhatsApp CRM</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.whatsapp)"
                                        @update:modelValue="toggleCompanyModule(company, 'whatsapp', $event)"
                                    />
                                </div>

                                <!-- Citas / Google Calendar -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sky-500 font-bold text-xs">📅 Citas & Agenda</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.appointments)"
                                        @update:modelValue="toggleCompanyModule(company, 'appointments', $event)"
                                    />
                                </div>

                                <!-- Catálogo de Servicios -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-indigo-500 font-bold text-xs">💼 Servicios</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.services)"
                                        @update:modelValue="toggleCompanyModule(company, 'services', $event)"
                                    />
                                </div>

                                <!-- Inventario -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-amber-500 font-bold text-xs">📦 Inventario</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.inventory)"
                                        @update:modelValue="toggleCompanyModule(company, 'inventory', $event)"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="mt-6 pt-4 border-t border-base-300 flex justify-end">
                            <Button
                                label="Editar Datos"
                                icon="pi pi-pencil"
                                text
                                size="small"
                                severity="secondary"
                                @click="openEditDialog(company)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear / Editar -->
        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="isEditing ? 'Editar Empresa' : 'Nueva Empresa'"
            :style="{ width: '480px' }"
            class="p-fluid"
        >
            <form @submit.prevent="submitForm" class="space-y-4 pt-2">
                <div v-if="!isEditing && clients && clients.length">
                    <label class="text-xs font-semibold text-base-content/80">Cuenta Principal (Cliente) *</label>
                    <Select
                        v-model="form.client_id"
                        :options="clients"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full text-sm mt-1"
                        required
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Nombre de la Empresa / Sucursal *</label>
                    <InputText v-model="form.name" class="w-full text-sm mt-1" placeholder="Ej. Sucursal Norte" required />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">RFC / Identificación Fiscal</label>
                        <InputText v-model="form.document_number" class="w-full text-sm mt-1" placeholder="ABC-123456" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Teléfono</label>
                        <InputText v-model="form.phone" class="w-full text-sm mt-1" placeholder="+1 234 567 890" />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Email de Contacto</label>
                    <InputText v-model="form.email" type="email" class="w-full text-sm mt-1" placeholder="contacto@empresa.com" />
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                    <Button label="Cancelar" severity="secondary" text size="small" @click="dialogVisible = false" />
                    <Button :label="isEditing ? 'Actualizar' : 'Crear Empresa'" type="submit" severity="primary" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AuthenticatedLayout>
</template>
