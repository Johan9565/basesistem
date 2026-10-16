<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
    products: Object,
    branches: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const selectedBranch = ref(props.filters?.branch_id ?? '');
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingProductId = ref(null);

const branchOptions = computed(() => [
    { label: '🏢 Todas las sucursales', value: '' },
    ...(props.branches || []).map((b) => ({
        label: `${b.name} ${b.is_main ? '(Matriz)' : ''}`,
        value: b.id,
    })),
]);

const form = useForm({
    branch_id: '',
    sku: '',
    name: '',
    description: '',
    category: '',
    price: 0,
    cost: 0,
    stock: 0,
    min_stock: 0,
    is_active: true,
});

function openCreateDialog() {
    isEditing.value = false;
    editingProductId.value = null;
    form.reset();
    form.clearErrors();
    const mainBranch = props.branches?.find((b) => b.is_main) || props.branches?.[0];
    form.branch_id = mainBranch ? mainBranch.id : '';
    form.is_active = true;
    dialogVisible.value = true;
}

function openEditDialog(product) {
    isEditing.value = true;
    editingProductId.value = product.id || product._id;
    form.reset();
    form.clearErrors();
    form.branch_id = product.branch_id ?? '';
    form.sku = product.sku ?? '';
    form.name = product.name ?? '';
    form.description = product.description ?? '';
    form.category = product.category ?? '';
    form.price = Number(product.price ?? 0);
    form.cost = Number(product.cost ?? 0);
    form.stock = Number(product.stock ?? 0);
    form.min_stock = Number(product.min_stock ?? 0);
    form.is_active = Boolean(product.is_active ?? true);
    dialogVisible.value = true;
}

function submitForm() {
    if (isEditing.value) {
        form.patch(route('products.update', editingProductId.value), {
            onSuccess: () => { dialogVisible.value = false; },
        });
    } else {
        form.post(route('products.store'), {
            onSuccess: () => { dialogVisible.value = false; },
        });
    }
}

function deleteProduct(product) {
    if (confirm(`¿Estás seguro de eliminar el producto "${product.name}"?`)) {
        router.delete(route('products.destroy', product.id || product._id));
    }
}

function applyFilters() {
    router.get(route('products'), {
        search: search.value,
        branch_id: selectedBranch.value,
    }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Inventario de Productos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-base-content flex items-center gap-2">
                        <span>📦</span>
                        <span>Inventario y Catálogo de Productos</span>
                    </h2>
                    <p class="text-xs text-base-content/60">
                        Gestiona el stock global de la empresa identificado y segmentado por sucursal operativa.
                    </p>
                </div>
                <Button
                    label="Nuevo Producto"
                    icon="pi pi-plus"
                    size="small"
                    severity="primary"
                    @click="openCreateDialog"
                />
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Barra de Búsqueda y Filtro de Sucursal -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="relative flex-1">
                        <InputText
                            v-model="search"
                            placeholder="Buscar por nombre, SKU o categoría..."
                            class="w-full text-sm"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div v-if="branches && branches.length > 0" class="w-full sm:w-64">
                        <Select
                            v-model="selectedBranch"
                            :options="branchOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Filtrar por sucursal"
                            class="w-full text-sm"
                            @change="applyFilters"
                        />
                    </div>
                    <Button
                        label="Buscar"
                        icon="pi pi-search"
                        size="small"
                        severity="secondary"
                        @click="applyFilters"
                    />
                </div>

                <!-- Tabla de Productos -->
                <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-base-content">
                            <thead class="border-b border-base-300 bg-base-200/60 text-xs font-semibold uppercase tracking-wider text-base-content/70">
                                <tr>
                                    <th class="px-6 py-4">SKU / Producto</th>
                                    <th class="px-6 py-4">Sucursal</th>
                                    <th class="px-6 py-4">Categoría</th>
                                    <th class="px-6 py-4">Precio</th>
                                    <th class="px-6 py-4">Stock</th>
                                    <th class="px-6 py-4">Estado</th>
                                    <th class="px-6 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-300">
                                <tr
                                    v-for="product in products.data"
                                    :key="product.id || product._id"
                                    class="transition-colors hover:bg-base-200/40"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-base-content">{{ product.name }}</div>
                                        <div class="text-xs font-mono text-primary">{{ product.sku }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                            <i class="pi pi-building text-[10px]"></i>
                                            {{ product.branch_name }}
                                            <span v-if="product.branch_code" class="text-[10px] opacity-70">({{ product.branch_code }})</span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-md bg-base-300 px-2 py-1 text-xs font-medium text-base-content/80">
                                            {{ product.category || 'General' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-emerald-600 dark:text-emerald-400">
                                        ${{ Number(product.price).toFixed(2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="product.stock <= (product.min_stock || 0)
                                                ? 'bg-rose-500/15 text-rose-500'
                                                : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="product.stock <= (product.min_stock || 0) ? 'bg-rose-500' : 'bg-emerald-500'"></span>
                                            {{ product.stock }} unidades
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="badge badge-sm"
                                            :class="product.is_active ? 'badge-success' : 'badge-ghost'"
                                        >
                                            {{ product.is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            size="small"
                                            severity="secondary"
                                            @click="openEditDialog(product)"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            size="small"
                                            severity="danger"
                                            @click="deleteProduct(product)"
                                        />
                                    </td>
                                </tr>

                                <tr v-if="!products.data || products.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-base-content/50">
                                        No hay productos registrados en el inventario para los filtros seleccionados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Crear / Editar -->
        <Dialog
            v-model:visible="dialogVisible"
            modal
            :header="isEditing ? 'Editar Producto' : 'Nuevo Producto en Inventario'"
            :style="{ width: '520px' }"
            class="p-fluid"
        >
            <form @submit.prevent="submitForm" class="space-y-4 pt-2">
                <div v-if="branches && branches.length > 0">
                    <label class="text-xs font-semibold text-base-content/80">Sucursal Asignada *</label>
                    <Select
                        v-model="form.branch_id"
                        :options="branches"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Selecciona una sucursal"
                        class="w-full text-sm mt-1"
                        required
                    >
                        <template #option="slotProps">
                            <div class="flex items-center justify-between gap-2 w-full">
                                <span class="font-medium">{{ slotProps.option.name }}</span>
                                <span v-if="slotProps.option.is_main" class="badge badge-xs badge-primary">Matriz</span>
                            </div>
                        </template>
                    </Select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">SKU *</label>
                        <InputText v-model="form.sku" class="w-full text-sm mt-1" placeholder="PROD-001" required />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Categoría</label>
                        <InputText v-model="form.category" class="w-full text-sm mt-1" placeholder="Ej. Insumos" />
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Nombre del Producto *</label>
                    <InputText v-model="form.name" class="w-full text-sm mt-1" placeholder="Nombre completo" required />
                </div>

                <div>
                    <label class="text-xs font-semibold text-base-content/80">Descripción</label>
                    <Textarea v-model="form.description" rows="2" class="w-full text-sm mt-1" placeholder="Detalles o especificaciones" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Precio Venta ($) *</label>
                        <InputNumber v-model="form.price" :min="0" :minFractionDigits="2" class="w-full text-sm mt-1" required />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Costo ($)</label>
                        <InputNumber v-model="form.cost" :min="0" :minFractionDigits="2" class="w-full text-sm mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Stock Inicial *</label>
                        <InputNumber v-model="form.stock" :min="0" class="w-full text-sm mt-1" required />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Stock Mínimo</label>
                        <InputNumber v-model="form.min_stock" :min="0" class="w-full text-sm mt-1" />
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-xs font-semibold text-base-content/80">Producto Activo</span>
                    <ToggleSwitch v-model="form.is_active" />
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                    <Button label="Cancelar" severity="secondary" text size="small" @click="dialogVisible = false" />
                    <Button :label="isEditing ? 'Actualizar' : 'Guardar Producto'" type="submit" severity="primary" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AuthenticatedLayout>
</template>
