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
    users: Array,
    can: {
        type: Object,
        default: () => ({
            create: false,
            edit: false,
            toggle_module: false,
            manage_owner: false,
            manage_all: false,
            select_all: false,
            branches_view: false,
            branches_create: false,
            branches_edit: false,
            branches_delete: false,
        }),
    },
});



const dialogVisible = ref(false);
const isEditing = ref(false);
const editingCompanyId = ref(null);

const form = useForm({
    user_id: null,
    client_id: null,
    name: '',
    document_number: '',
    email: '',
    phone: '',
    status: 'active',
    modules: {
        inventory: false,
        orders: false,
        services: false,
        appointments: false,
        whatsapp: true,
        'chat-test': true,
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
        orders: true,
        services: true,
        appointments: true,
        whatsapp: true,
        'chat-test': true,
    };
    if (props.clients && props.clients.length) {
        form.client_id = props.clients[0].id;
    }
    if (props.users && props.users.length) {
        form.user_id = props.users[0].id;
    }
    dialogVisible.value = true;
}

function openEditDialog(company) {
    isEditing.value = true;
    editingCompanyId.value = company.id;
    form.reset();
    form.clearErrors();
    form.user_id = company.user_id || (props.users?.[0]?.id ?? null);
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

// --- Gestión de Sucursales ---
const branchDialogVisible = ref(false);
const isEditingBranch = ref(false);
const selectedCompanyForBranch = ref(null);
const editingBranchId = ref(null);

const branchForm = useForm({
    name: '',
    code: '',
    phone: '',
    email: '',
    address: '',
    is_main: false,
    is_active: true,
});

function openCreateBranchDialog(company) {
    selectedCompanyForBranch.value = company;
    isEditingBranch.value = false;
    editingBranchId.value = null;
    branchForm.reset();
    branchForm.clearErrors();
    branchForm.is_active = true;
    branchForm.is_main = !company.branches || company.branches.length === 0;
    branchDialogVisible.value = true;
}

function openEditBranchDialog(company, branch) {
    selectedCompanyForBranch.value = company;
    isEditingBranch.value = true;
    editingBranchId.value = branch.id;
    branchForm.reset();
    branchForm.clearErrors();
    branchForm.name = branch.name ?? '';
    branchForm.code = branch.code ?? '';
    branchForm.phone = branch.phone ?? '';
    branchForm.email = branch.email ?? '';
    branchForm.address = branch.address ?? '';
    branchForm.is_main = Boolean(branch.is_main);
    branchForm.is_active = branch.is_active ?? true;
    branchDialogVisible.value = true;
}

function submitBranchForm() {
    if (!selectedCompanyForBranch.value) return;

    if (isEditingBranch.value) {
        branchForm.patch(route('companies.branches.update', [selectedCompanyForBranch.value.id, editingBranchId.value]), {
            onSuccess: () => { branchDialogVisible.value = false; },
        });
    } else {
        branchForm.post(route('companies.branches.store', selectedCompanyForBranch.value.id), {
            onSuccess: () => { branchDialogVisible.value = false; },
        });
    }
}

function deleteBranch() {
    if (!selectedCompanyForBranch.value || !editingBranchId.value) return;
    if (confirm('¿Estás seguro de que deseas eliminar esta sucursal?')) {
        router.delete(route('companies.branches.destroy', [selectedCompanyForBranch.value.id, editingBranchId.value]), {
            onSuccess: () => { branchDialogVisible.value = false; },
        });
    }
}

// --- Gestión de Cuentas Principales (Clientes SaaS) ---
const clientDialogVisible = ref(false);
const isEditingClient = ref(false);
const editingClientId = ref(null);
const clientsListDialogVisible = ref(false);

const clientForm = useForm({
    name: '',
    document_number: '',
    billing_email: '',
    phone: '',
    plan: 'pro',
    max_companies: 5,
    status: 'active',
});

function openCreateClientDialog() {
    isEditingClient.value = false;
    editingClientId.value = null;
    clientForm.reset();
    clientForm.clearErrors();
    clientForm.name = '';
    clientForm.document_number = '';
    clientForm.billing_email = '';
    clientForm.phone = '';
    clientForm.plan = 'pro';
    clientForm.max_companies = 5;
    clientForm.status = 'active';
    clientDialogVisible.value = true;
}

function openEditClientDialog(client) {
    isEditingClient.value = true;
    editingClientId.value = client.id;
    clientForm.reset();
    clientForm.clearErrors();
    clientForm.name = client.name ?? '';
    clientForm.document_number = client.document_number ?? '';
    clientForm.billing_email = client.billing_email ?? '';
    clientForm.phone = client.phone ?? '';
    clientForm.plan = client.plan ?? 'pro';
    clientForm.max_companies = client.max_companies ?? 5;
    clientForm.status = client.status ?? 'active';
    clientDialogVisible.value = true;
}

function submitClientForm() {
    if (isEditingClient.value) {
        clientForm.patch(route('clients.update', editingClientId.value), {
            preserveScroll: true,
            onSuccess: () => {
                clientDialogVisible.value = false;
            },
        });
    } else {
        clientForm.post(route('clients.store'), {
            preserveScroll: true,
            onSuccess: (page) => {
                clientDialogVisible.value = false;
                const newId = page.props?.flash?.new_client_id;
                if (newId) {
                    form.client_id = newId;
                }
            },
        });
    }
}

function deleteClient(client) {
    if (client.companies_count > 0) {
        alert(`No puedes eliminar la cuenta "${client.name}" porque tiene ${client.companies_count} empresa(s) asociada(s).`);
        return;
    }
    if (confirm(`¿Estás seguro de que deseas eliminar la cuenta principal "${client.name}"?`)) {
        router.delete(route('clients.destroy', client.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Gestión de Empresas y Módulos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-base-content flex items-center gap-2">
                        <i class="pi pi-building text-base-content/70"></i>
                        <span>Empresas y Módulos Habilitados</span>
                    </h2>
                    <p class="text-xs text-base-content/60">
                        Administra las empresas asociadas a la cuenta y activa o desactiva módulos individualmente en tiempo real.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        label="Cuentas Principales"
                        icon="pi pi-building-columns"
                        size="small"
                        severity="secondary"
                        outlined
                        @click="clientsListDialogVisible = true"
                    />
                    <Button
                        v-if="can?.create"
                        label="Nueva Empresa"
                        icon="pi pi-plus"
                        size="small"
                        severity="primary"
                        @click="openCreateDialog"
                    />
                </div>
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

                            <div class="text-xs text-base-content/70 space-y-1.5 mb-6 border-b border-base-300 pb-4">
                                <div class="flex items-center gap-1.5 text-primary font-semibold text-xs">
                                    <i class="pi pi-user text-[11px]"></i>
                                    <span>Dueño: {{ company.owner_name }}</span>
                                </div>
                                <div v-if="company.owner_email" class="text-[11px] text-base-content/50 ml-4 font-mono">
                                    {{ company.owner_email }}
                                </div>
                                <div v-if="company.email" class="flex items-center gap-1.5 pt-1">
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
                                    <div class="flex items-center gap-2 text-base-content/80">
                                        <i class="pi pi-comments text-xs text-base-content/60"></i>
                                        <span class="font-medium text-xs">WhatsApp CRM</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.whatsapp)"
                                        :disabled="!can?.toggle_module"
                                        @update:modelValue="toggleCompanyModule(company, 'whatsapp', $event)"
                                    />
                                </div>

                                <!-- Citas / Google Calendar -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2 text-base-content/80">
                                        <i class="pi pi-calendar text-xs text-base-content/60"></i>
                                        <span class="font-medium text-xs">Citas & Agenda</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.appointments)"
                                        :disabled="!can?.toggle_module"
                                        @update:modelValue="toggleCompanyModule(company, 'appointments', $event)"
                                    />
                                </div>

                                <!-- Catálogo de Servicios -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2 text-base-content/80">
                                        <i class="pi pi-briefcase text-xs text-base-content/60"></i>
                                        <span class="font-medium text-xs">Servicios</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.services)"
                                        :disabled="!can?.toggle_module"
                                        @update:modelValue="toggleCompanyModule(company, 'services', $event)"
                                    />
                                </div>

                                <!-- Inventario -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2 text-base-content/80">
                                        <i class="pi pi-box text-xs text-base-content/60"></i>
                                        <span class="font-medium text-xs">Inventario</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.inventory)"
                                        :disabled="!can?.toggle_module"
                                        @update:modelValue="toggleCompanyModule(company, 'inventory', $event)"
                                    />
                                </div>

                                <!-- Pedidos (Ventas & Descuento de Inventario) -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2 text-base-content/80">
                                        <i class="pi pi-shopping-cart text-xs text-base-content/60"></i>
                                        <div class="flex flex-col">
                                            <span class="font-medium text-xs">Pedidos</span>
                                            <span class="text-[10px] text-base-content/50">Inherente a Inventario</span>
                                        </div>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.orders && company.modules?.inventory)"
                                        :disabled="!can?.toggle_module"
                                        @update:modelValue="toggleCompanyModule(company, 'orders', $event)"
                                    />
                                </div>

                                <!-- Chat Test (Simulador de Bot) -->
                                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-2.5">
                                    <div class="flex items-center gap-2 text-base-content/80">
                                        <i class="pi pi-bolt text-xs text-base-content/60"></i>
                                        <span class="font-medium text-xs">Chat Test</span>
                                    </div>
                                    <ToggleSwitch
                                        :modelValue="Boolean(company.modules?.['chat-test'] ?? company.modules?.whatsapp)"
                                        :disabled="!can?.toggle_module"
                                        @update:modelValue="toggleCompanyModule(company, 'chat-test', $event)"
                                    />
                                </div>
                            </div>

                            <!-- Sección de Sucursales -->
                            <div v-if="can?.branches_view" class="mt-5 pt-4 border-t border-base-300">
                                <div class="flex items-center justify-between mb-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-base-content/50 flex items-center gap-1.5">
                                            <i class="pi pi-sitemap text-xs"></i>
                                            <span>Sucursales ({{ company.branches_count || company.branches?.length || 0 }})</span>
                                        </span>
                                    </div>
                                    <Button
                                        v-if="can?.branches_create"
                                        label="Nueva Sucursal"
                                        icon="pi pi-plus"
                                        size="small"
                                        text
                                        class="!text-xs !py-0.5 !px-1.5 text-primary hover:bg-primary/10"
                                        @click="openCreateBranchDialog(company)"
                                    />
                                </div>

                                <!-- Lista de Sucursales -->
                                <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                                    <div
                                        v-for="branch in company.branches || []"
                                        :key="branch.id"
                                        class="flex items-center justify-between rounded-lg bg-base-200/60 p-2 text-xs transition-all hover:bg-base-200"
                                    >
                                        <div class="flex items-center gap-2 truncate">
                                            <span v-if="branch.is_main" class="badge badge-primary badge-xs font-bold uppercase text-[9px]">Matriz</span>
                                            <span v-else class="badge badge-ghost badge-xs font-mono text-[9px]">{{ branch.code || 'SUC' }}</span>
                                            <span class="font-medium text-base-content truncate">{{ branch.name }}</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span
                                                class="badge badge-xs"
                                                :class="branch.is_active ? 'badge-success' : 'badge-ghost'"
                                            >
                                                {{ branch.is_active ? 'Activa' : 'Inactiva' }}
                                            </span>
                                            <button
                                                v-if="can?.branches_edit"
                                                type="button"
                                                class="btn btn-ghost btn-xs btn-square text-base-content/60 hover:text-primary"
                                                title="Editar sucursal"
                                                @click="openEditBranchDialog(company, branch)"
                                            >
                                                <i class="pi pi-pencil text-[10px]"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="!company.branches || !company.branches.length" class="text-[11px] text-base-content/40 italic py-1 text-center">
                                        Sin sucursales registradas
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div v-if="can?.edit" class="mt-6 pt-4 border-t border-base-300 flex justify-end">
                            <Button
                                label="Editar Datos"
                                icon="pi pi-pencil"
                                text
                                size="small"
                                severity="secondary"
                                class="!text-xs !py-1 !px-2"
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
                <div v-if="can?.manage_owner || !isEditing">
                    <label class="text-xs font-semibold text-base-content/80">Usuario Propietario (Dueño de la Empresa) *</label>
                    <Select
                        v-model="form.user_id"
                        :options="users"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Selecciona el usuario propietario"
                        class="w-full text-sm mt-1"
                        required
                    >
                        <template #option="slotProps">
                            <div class="flex flex-col text-xs py-0.5">
                                <span class="font-bold text-base-content">{{ slotProps.option.name }}</span>
                                <span class="text-base-content/50 font-mono text-[11px]">{{ slotProps.option.email }}</span>
                            </div>
                        </template>
                    </Select>
                </div>
                <div v-else>
                    <label class="text-xs font-semibold text-base-content/80">Usuario Propietario</label>
                    <div class="p-2.5 rounded-lg bg-base-200/60 text-xs text-base-content/80 font-medium mt-1">
                        {{ users.find(u => u.id === form.user_id)?.name || 'Propietario asignado' }}
                    </div>
                </div>

                <div v-if="!isEditing && clients && clients.length">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold text-base-content/80">Cuenta Principal (Cliente) *</label>
                        <button
                            type="button"
                            class="text-[11px] text-primary hover:underline flex items-center gap-1 font-semibold"
                            @click="openCreateClientDialog"
                        >
                            <i class="pi pi-plus text-[10px]"></i>
                            <span>Nueva Cuenta Principal</span>
                        </button>
                    </div>
                    <Select
                        v-model="form.client_id"
                        :options="clients"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Selecciona la cuenta principal"
                        class="w-full text-sm mt-1"
                        required
                    >
                        <template #option="slotProps">
                            <div class="flex flex-col text-xs py-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-base-content">{{ slotProps.option.name }}</span>
                                    <span class="badge badge-xs uppercase font-mono text-[9px]">{{ slotProps.option.plan }}</span>
                                </div>
                                <span v-if="slotProps.option.document_number" class="text-base-content/50 font-mono text-[11px]">
                                    RFC: {{ slotProps.option.document_number }}
                                </span>
                            </div>
                        </template>
                    </Select>
                </div>
                <div v-else-if="!isEditing && (!clients || !clients.length)" class="p-3 bg-warning/10 border border-warning/30 rounded-lg text-xs space-y-2">
                    <div class="font-semibold text-warning-content flex items-center gap-1.5">
                        <i class="pi pi-exclamation-triangle"></i>
                        <span>No hay Cuentas Principales registradas</span>
                    </div>
                    <p class="text-base-content/70">
                        Para crear una empresa necesitas al menos una Cuenta Principal corporativa.
                    </p>
                    <Button
                        label="Crear Cuenta Principal"
                        icon="pi pi-plus"
                        size="small"
                        severity="warn"
                        type="button"
                        @click="openCreateClientDialog"
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

                <div v-if="isEditing && can?.manage_owner">
                    <label class="text-xs font-semibold text-base-content/80">Estado de la Empresa *</label>
                    <Select
                        v-model="form.status"
                        :options="[
                            { label: 'Activa', value: 'active' },
                            { label: 'Inactiva', value: 'inactive' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full text-sm mt-1"
                        required
                    />
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                    <Button label="Cancelar" severity="secondary" text size="small" @click="dialogVisible = false" />
                    <Button :label="isEditing ? 'Actualizar' : 'Crear Empresa'" type="submit" severity="primary" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>

        <!-- Modal Crear / Editar Sucursal -->
        <Dialog
            v-model:visible="branchDialogVisible"
            modal
            :header="isEditingBranch ? 'Editar Sucursal' : `Nueva Sucursal - ${selectedCompanyForBranch?.name}`"
            :style="{ width: '500px' }"
            class="p-fluid"
        >
            <form @submit.prevent="submitBranchForm" class="space-y-4 pt-2">
                <div>
                    <label class="text-xs font-semibold text-base-content/80">Nombre de la Sucursal *</label>
                    <InputText v-model="branchForm.name" class="w-full text-sm mt-1" placeholder="Ej. Sucursal Centro" required />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Código Interno</label>
                        <InputText v-model="branchForm.code" class="w-full text-sm mt-1" placeholder="SUC-01" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Teléfono</label>
                        <InputText v-model="branchForm.phone" class="w-full text-sm mt-1" placeholder="+1 234 567 890" />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Email de Contacto</label>
                    <InputText v-model="branchForm.email" type="email" class="w-full text-sm mt-1" placeholder="sucursal@empresa.com" />
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Dirección</label>
                    <InputText v-model="branchForm.address" class="w-full text-sm mt-1" placeholder="Av. Principal #123, Col. Centro" />
                </div>

                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-3 mt-2">
                    <div>
                        <div class="text-xs font-bold text-base-content">¿Es Sucursal Matriz / Principal?</div>
                        <div class="text-[11px] text-base-content/50">La sucursal matriz representa la sede central de la empresa.</div>
                    </div>
                    <ToggleSwitch v-model="branchForm.is_main" />
                </div>

                <div class="flex items-center justify-between rounded-xl bg-base-200/50 p-3">
                    <div>
                        <div class="text-xs font-bold text-base-content">Estado Activo</div>
                        <div class="text-[11px] text-base-content/50">Habilita o deshabilita la operación de esta sucursal.</div>
                    </div>
                    <ToggleSwitch v-model="branchForm.is_active" />
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-base-300">
                    <div>
                        <Button
                            v-if="isEditingBranch && can?.branches_delete && (selectedCompanyForBranch?.branches_count > 1 || selectedCompanyForBranch?.branches?.length > 1)"
                            label="Eliminar"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            size="small"
                            type="button"
                            @click="deleteBranch"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button label="Cancelar" severity="secondary" text size="small" type="button" @click="branchDialogVisible = false" />
                        <Button :label="isEditingBranch ? 'Guardar Cambios' : 'Crear Sucursal'" type="submit" severity="primary" size="small" :loading="branchForm.processing" />
                    </div>
                </div>
            </form>
        </Dialog>

        <!-- Modal Crear / Editar Cuenta Principal (Cliente SaaS) -->
        <Dialog
            v-model:visible="clientDialogVisible"
            modal
            :header="isEditingClient ? 'Editar Cuenta Principal' : 'Nueva Cuenta Principal (Cliente Corporativo)'"
            :style="{ width: '480px' }"
            class="p-fluid"
        >
            <form @submit.prevent="submitClientForm" class="space-y-4 pt-2">
                <div class="text-xs text-base-content/60 pb-1 border-b border-base-300">
                    Una Cuenta Principal agrupa a una o más empresas bajo una misma entidad comercial o cliente de la plataforma.
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Nombre de la Cuenta / Corporativo *</label>
                    <InputText
                        v-model="clientForm.name"
                        class="w-full text-sm mt-1"
                        placeholder="Ej. Corporativo Médico del Norte S.A."
                        required
                    />
                    <div v-if="clientForm.errors.name" class="text-error text-[11px] mt-0.5">{{ clientForm.errors.name }}</div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">RFC / ID Fiscal</label>
                        <InputText
                            v-model="clientForm.document_number"
                            class="w-full text-sm mt-1"
                            placeholder="ABC-123456-XYZ"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Teléfono</label>
                        <InputText
                            v-model="clientForm.phone"
                            class="w-full text-sm mt-1"
                            placeholder="+52 998 123 4567"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Email de Facturación / Contacto</label>
                    <InputText
                        v-model="clientForm.billing_email"
                        type="email"
                        class="w-full text-sm mt-1"
                        placeholder="facturacion@corporativo.com"
                    />
                    <div v-if="clientForm.errors.billing_email" class="text-error text-[11px] mt-0.5">{{ clientForm.errors.billing_email }}</div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Plan SaaS *</label>
                        <Select
                            v-model="clientForm.plan"
                            :options="[
                                { label: 'Básico', value: 'basic' },
                                { label: 'Pro', value: 'pro' },
                                { label: 'Enterprise', value: 'enterprise' }
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm mt-1"
                            required
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Límite de Empresas *</label>
                        <InputText
                            v-model.number="clientForm.max_companies"
                            type="number"
                            min="1"
                            max="100"
                            class="w-full text-sm mt-1"
                            required
                        />
                    </div>
                </div>

                <div v-if="isEditingClient">
                    <label class="text-xs font-semibold text-base-content/80">Estado de la Cuenta *</label>
                    <Select
                        v-model="clientForm.status"
                        :options="[
                            { label: 'Activa', value: 'active' },
                            { label: 'Suspendida', value: 'suspended' },
                            { label: 'Cancelada', value: 'cancelled' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full text-sm mt-1"
                        required
                    />
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                    <Button
                        label="Cancelar"
                        severity="secondary"
                        text
                        size="small"
                        type="button"
                        @click="clientDialogVisible = false"
                    />
                    <Button
                        :label="isEditingClient ? 'Actualizar Cuenta' : 'Crear Cuenta Principal'"
                        type="submit"
                        severity="primary"
                        size="small"
                        :loading="clientForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Modal Gestión de Cuentas Principales -->
        <Dialog
            v-model:visible="clientsListDialogVisible"
            modal
            header="Cuentas Principales Registradas (Clientes SaaS)"
            :style="{ width: '650px' }"
            class="p-fluid"
        >
            <div class="space-y-4 pt-2">
                <div class="flex items-center justify-between pb-3 border-b border-base-300">
                    <div>
                        <p class="text-xs text-base-content/70">
                            Cuentas corporativas registradas en la plataforma. Cada una puede poseer múltiples empresas asociadas.
                        </p>
                    </div>
                    <Button
                        label="Nueva Cuenta"
                        icon="pi pi-plus"
                        size="small"
                        severity="primary"
                        @click="openCreateClientDialog"
                    />
                </div>

                <div v-if="!clients || !clients.length" class="text-center py-8 text-base-content/50 text-sm">
                    No hay cuentas principales registradas aún.
                </div>

                <div v-else class="space-y-2.5 max-h-[420px] overflow-y-auto pr-1">
                    <div
                        v-for="c in clients"
                        :key="c.id"
                        class="flex items-center justify-between p-3.5 rounded-xl border border-base-300 bg-base-200/40 hover:bg-base-200/70 transition-all"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-base-content">{{ c.name }}</span>
                                <span
                                    class="badge badge-xs uppercase font-mono text-[9px]"
                                    :class="{
                                        'badge-primary': c.plan === 'enterprise',
                                        'badge-info': c.plan === 'pro',
                                        'badge-ghost': c.plan === 'basic'
                                    }"
                                >
                                    {{ c.plan }}
                                </span>
                                <span
                                    class="badge badge-xs text-[9px]"
                                    :class="c.status === 'active' ? 'badge-success' : 'badge-warning'"
                                >
                                    {{ c.status === 'active' ? 'Activa' : c.status }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-base-content/60">
                                <span v-if="c.document_number" class="font-mono">RFC: {{ c.document_number }}</span>
                                <span v-if="c.billing_email">{{ c.billing_email }}</span>
                                <span v-if="c.phone">{{ c.phone }}</span>
                                <span class="text-primary font-semibold">
                                    Empresas: {{ c.companies_count ?? 0 }} / {{ c.max_companies ?? 10 }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <Button
                                icon="pi pi-pencil"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                title="Editar cuenta"
                                @click="openEditClientDialog(c)"
                            />
                            <Button
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                title="Eliminar cuenta"
                                :disabled="(c.companies_count ?? 0) > 0"
                                @click="deleteClient(c)"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-base-300">
                    <Button
                        label="Cerrar"
                        severity="secondary"
                        size="small"
                        @click="clientsListDialogVisible = false"
                    />
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
