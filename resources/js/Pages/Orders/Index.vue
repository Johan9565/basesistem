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

const props = defineProps({
    orders: Object,
    branches: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({
            total_orders: 0,
            pending_orders: 0,
            completed_orders: 0,
            total_revenue: 0,
        }),
    },
    filters: Object,
    can: {
        type: Object,
        default: () => ({
            create: false,
            edit: false,
            delete: false,
        }),
    },
});

const search = ref(props.filters?.search ?? '');
const selectedBranch = ref(props.filters?.branch_id ?? '');
const selectedStatus = ref(props.filters?.status ?? '');

const createDialogVisible = ref(false);
const viewDialogVisible = ref(false);
const selectedOrder = ref(null);

const branchOptions = computed(() => [
    { label: '🏢 Todas las sucursales', value: '' },
    ...(props.branches || []).map((b) => ({
        label: `${b.name} ${b.is_main ? '(Matriz)' : ''}`,
        value: b.id,
    })),
]);

const statusOptions = [
    { label: 'Todos los estados', value: '' },
    { label: 'Pendiente', value: 'pending' },
    { label: 'Completado', value: 'completed' },
    { label: 'Cancelado', value: 'cancelled' },
];

const createForm = useForm({
    branch_id: '',
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    status: 'completed',
    payment_status: 'paid',
    payment_method: 'cash',
    discount: 0,
    tax: 0,
    notes: '',
    items: [],
});

const editStatusForm = useForm({
    status: 'pending',
    payment_status: 'unpaid',
    notes: '',
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    branch_id: '',
});

function openCreateDialog() {
    createForm.reset();
    createForm.clearErrors();
    const mainBranch = props.branches?.find((b) => b.is_main) || props.branches?.[0];
    createForm.branch_id = mainBranch ? mainBranch.id : '';
    createForm.status = 'completed';
    createForm.payment_status = 'paid';
    createForm.payment_method = 'cash';
    createForm.discount = 0;
    createForm.tax = 0;
    createForm.items = [
        {
            product_id: '',
            quantity: 1,
            unit_price: 0,
        },
    ];
    createDialogVisible.value = true;
}

function addItemRow() {
    createForm.items.push({
        product_id: '',
        quantity: 1,
        unit_price: 0,
    });
}

function removeItemRow(index) {
    if (createForm.items.length > 1) {
        createForm.items.splice(index, 1);
    }
}

function onProductSelected(index, productId) {
    const p = props.products.find((prod) => prod.id === productId);
    if (p) {
        createForm.items[index].unit_price = Number(p.price || 0);
    }
}

function getProductStock(productId) {
    const p = props.products.find((prod) => prod.id === productId);
    return p ? p.stock : 0;
}

const calculatedSubtotal = computed(() => {
    return createForm.items.reduce((acc, item) => {
        const qty = Number(item.quantity) || 0;
        const price = Number(item.unit_price) || 0;
        return acc + (qty * price);
    }, 0);
});

const calculatedTotal = computed(() => {
    const discount = Number(createForm.discount) || 0;
    const tax = Number(createForm.tax) || 0;
    return Math.max(0, calculatedSubtotal.value - discount + tax);
});

function submitCreateOrder() {
    createForm.post(route('orders.store'), {
        onSuccess: () => {
            createDialogVisible.value = false;
        },
    });
}

function openViewDialog(order) {
    selectedOrder.value = order;
    editStatusForm.status = order.status;
    editStatusForm.payment_status = order.payment_status;
    editStatusForm.notes = order.notes ?? '';
    editStatusForm.customer_name = order.customer_name;
    editStatusForm.customer_email = order.customer_email ?? '';
    editStatusForm.customer_phone = order.customer_phone ?? '';
    editStatusForm.branch_id = order.branch_id ?? '';
    viewDialogVisible.value = true;
}

function submitUpdateOrderStatus() {
    if (!selectedOrder.value) return;
    editStatusForm.patch(route('orders.update', selectedOrder.value.id), {
        onSuccess: () => {
            viewDialogVisible.value = false;
        },
    });
}

function deleteOrder(order) {
    if (confirm(`¿Estás seguro de eliminar el pedido "${order.order_number}"? Si el inventario fue descontado, se restaurará automáticamente.`)) {
        router.delete(route('orders.destroy', order.id));
    }
}

function applyFilters() {
    router.get(route('orders'), {
        search: search.value,
        branch_id: selectedBranch.value,
        status: selectedStatus.value,
    }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Gestión de Pedidos & Despacho" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-base-content flex items-center gap-2">
                        <i class="pi pi-shopping-cart text-primary"></i>
                        <span>Módulo de Pedidos & Despacho de Inventario</span>
                    </h2>
                    <p class="text-xs text-base-content/60">
                        Gestiona las órdenes de venta con descuento automático del stock en el catálogo de inventario.
                    </p>
                </div>
                <Button
                    v-if="can?.create"
                    label="Nuevo Pedido"
                    icon="pi pi-plus"
                    size="small"
                    severity="primary"
                    @click="openCreateDialog"
                />
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Métricas Principales -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-base-content/50">Total Pedidos</div>
                            <div class="text-2xl font-black text-base-content mt-1">{{ metrics.total_orders }}</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <i class="pi pi-shopping-bag text-lg"></i>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-base-content/50">Completados</div>
                            <div class="text-2xl font-black text-success mt-1">{{ metrics.completed_orders }}</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-success/10 text-success flex items-center justify-center">
                            <i class="pi pi-check-circle text-lg"></i>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-base-content/50">Pendientes</div>
                            <div class="text-2xl font-black text-warning mt-1">{{ metrics.pending_orders }}</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-warning/10 text-warning flex items-center justify-center">
                            <i class="pi pi-clock text-lg"></i>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-base-content/50">Facturación</div>
                            <div class="text-2xl font-black text-base-content mt-1">${{ Number(metrics.total_revenue || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-info/10 text-info flex items-center justify-center">
                            <i class="pi pi-dollar text-lg"></i>
                        </div>
                    </div>
                </div>

                <!-- Barra de Búsqueda y Filtros -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="relative flex-1">
                        <InputText
                            v-model="search"
                            placeholder="Buscar por número de pedido, cliente o teléfono..."
                            class="w-full text-sm"
                            @keydown.enter="applyFilters"
                        />
                    </div>
                    <div v-if="branches && branches.length > 0" class="w-full sm:w-56">
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
                    <div class="w-full sm:w-44">
                        <Select
                            v-model="selectedStatus"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Estado"
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

                <!-- Tabla de Pedidos -->
                <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-base-content">
                            <thead class="border-b border-base-300 bg-base-200/60 text-xs font-semibold uppercase tracking-wider text-base-content/70">
                                <tr>
                                    <th class="px-6 py-4">Pedido / Fecha</th>
                                    <th class="px-6 py-4">Cliente</th>
                                    <th class="px-6 py-4">Sucursal</th>
                                    <th class="px-6 py-4">Artículos Descontados</th>
                                    <th class="px-6 py-4">Total</th>
                                    <th class="px-6 py-4">Estado Pedido</th>
                                    <th class="px-6 py-4">Inventario</th>
                                    <th class="px-6 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-300">
                                <tr
                                    v-for="order in orders?.data || []"
                                    :key="order.id"
                                    class="transition-colors hover:bg-base-200/40"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-bold font-mono text-primary flex items-center gap-1.5">
                                            <i class="pi pi-receipt text-xs"></i>
                                            <span>{{ order.order_number }}</span>
                                        </div>
                                        <div class="text-[11px] text-base-content/50 mt-0.5">
                                            {{ order.created_at }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-base-content">{{ order.customer_name }}</div>
                                        <div v-if="order.customer_phone" class="text-xs text-base-content/60 font-mono">
                                            {{ order.customer_phone }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="badge badge-sm badge-ghost font-medium">
                                            {{ order.branch_name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1 max-w-xs">
                                            <span
                                                v-for="(item, idx) in order.items || []"
                                                :key="idx"
                                                class="badge badge-sm badge-neutral text-[10px]"
                                            >
                                                {{ item.quantity }}x {{ item.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-base-content">
                                        ${{ Number(order.total || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="badge badge-sm"
                                            :class="{
                                                'badge-success text-success-content': order.status === 'completed',
                                                'badge-warning text-warning-content': order.status === 'pending' || order.status === 'processing',
                                                'badge-error text-error-content': order.status === 'cancelled',
                                            }"
                                        >
                                            {{ order.status === 'completed' ? 'Completado' : (order.status === 'cancelled' ? 'Cancelado' : 'Pendiente') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            v-if="order.stock_deducted"
                                            class="inline-flex items-center gap-1 text-xs text-success font-medium"
                                        >
                                            <i class="pi pi-check text-[11px]"></i> Descontado
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 text-xs text-base-content/40 font-medium"
                                        >
                                            <i class="pi pi-minus-circle text-[11px]"></i> No descontado
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                icon="pi pi-eye"
                                                size="small"
                                                text
                                                rounded
                                                severity="secondary"
                                                title="Ver detalle / Cambiar estado"
                                                @click="openViewDialog(order)"
                                            />
                                            <Button
                                                v-if="can?.delete"
                                                icon="pi pi-trash"
                                                size="small"
                                                text
                                                rounded
                                                severity="danger"
                                                title="Eliminar pedido"
                                                @click="deleteOrder(order)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!orders?.data || orders.data.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-base-content/50">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <i class="pi pi-inbox text-3xl text-base-content/30"></i>
                                            <p class="text-sm font-medium">No se encontraron pedidos registrados.</p>
                                            <Button
                                                v-if="can?.create"
                                                label="Crear el primer pedido"
                                                icon="pi pi-plus"
                                                size="small"
                                                severity="primary"
                                                class="mt-2"
                                                @click="openCreateDialog"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog Nuevo Pedido -->
        <Dialog
            v-model:visible="createDialogVisible"
            modal
            header="Nuevo Pedido & Descuento de Inventario"
            :style="{ width: '750px' }"
            class="p-fluid"
        >
            <form @submit.prevent="submitCreateOrder" class="space-y-4 pt-2">
                <!-- Datos del Cliente y Sucursal -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Nombre del Cliente *</label>
                        <InputText v-model="createForm.customer_name" class="w-full text-sm mt-1" placeholder="Ej. Juan Pérez" required />
                    </div>
                    <div v-if="branches && branches.length > 0">
                        <label class="text-xs font-semibold text-base-content/80">Sucursal de Despacho *</label>
                        <Select
                            v-model="createForm.branch_id"
                            :options="branchOptions.filter(b => b.value !== '')"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm mt-1"
                            required
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Teléfono</label>
                        <InputText v-model="createForm.customer_phone" class="w-full text-sm mt-1" placeholder="+52 55 1234 5678" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Correo Electrónico</label>
                        <InputText v-model="createForm.customer_email" type="email" class="w-full text-sm mt-1" placeholder="cliente@ejemplo.com" />
                    </div>
                </div>

                <!-- Selección de Artículos del Inventario -->
                <div class="border-t border-base-300 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-base-content/70">
                            Productos a descontar del Inventario *
                        </label>
                        <Button
                            label="Agregar Producto"
                            icon="pi pi-plus"
                            size="small"
                            text
                            class="!text-xs text-primary"
                            @click="addItemRow"
                        />
                    </div>

                    <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                        <div
                            v-for="(item, idx) in createForm.items"
                            :key="idx"
                            class="flex items-center gap-2 p-2 rounded-xl bg-base-200/50 border border-base-300/60"
                        >
                            <div class="flex-1">
                                <Select
                                    v-model="item.product_id"
                                    :options="products"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Selecciona producto..."
                                    class="w-full text-xs"
                                    @change="onProductSelected(idx, $event.value)"
                                    required
                                >
                                    <template #option="slotProps">
                                        <div class="flex items-center justify-between w-full text-xs">
                                            <span>{{ slotProps.option.name }} ({{ slotProps.option.sku }})</span>
                                            <span class="badge badge-xs" :class="slotProps.option.stock > 0 ? 'badge-success' : 'badge-error'">
                                                Stock: {{ slotProps.option.stock }}
                                            </span>
                                        </div>
                                    </template>
                                </Select>
                            </div>

                            <div class="w-20">
                                <InputNumber
                                    v-model="item.quantity"
                                    :min="1"
                                    placeholder="Cant."
                                    class="w-full text-xs"
                                    required
                                />
                            </div>

                            <div class="w-24">
                                <InputNumber
                                    v-model="item.unit_price"
                                    mode="currency"
                                    currency="USD"
                                    locale="es-MX"
                                    placeholder="Precio"
                                    class="w-full text-xs"
                                    required
                                />
                            </div>

                            <div class="w-20 text-right font-mono font-semibold text-xs text-base-content">
                                ${{ ((item.quantity || 0) * (item.unit_price || 0)).toFixed(2) }}
                            </div>

                            <Button
                                icon="pi pi-times"
                                size="small"
                                text
                                rounded
                                severity="danger"
                                :disabled="createForm.items.length <= 1"
                                @click="removeItemRow(idx)"
                            />
                        </div>
                    </div>

                    <div v-if="createForm.errors.items" class="text-xs text-error mt-1 font-medium">
                        {{ createForm.errors.items }}
                    </div>
                </div>

                <!-- Estado y Pago -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 border-t border-base-300 pt-3">
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Estado del Pedido</label>
                        <Select
                            v-model="createForm.status"
                            :options="[
                                { label: 'Completado (Descuenta Stock)', value: 'completed' },
                                { label: 'Pendiente (Descuenta Stock)', value: 'pending' },
                                { label: 'Cancelado (Sin descontar)', value: 'cancelled' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs mt-1"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Estado del Pago</label>
                        <Select
                            v-model="createForm.payment_status"
                            :options="[
                                { label: 'Pagado', value: 'paid' },
                                { label: 'Pendiente', value: 'unpaid' },
                                { label: 'Parcial', value: 'partial' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs mt-1"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Método de Pago</label>
                        <Select
                            v-model="createForm.payment_method"
                            :options="[
                                { label: 'Efectivo', value: 'cash' },
                                { label: 'Tarjeta', value: 'card' },
                                { label: 'Transferencia', value: 'transfer' },
                                { label: 'Otro', value: 'other' },
                            ]"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-xs mt-1"
                        />
                    </div>
                </div>

                <!-- Resumen de Totales -->
                <div class="rounded-xl bg-base-200/60 p-3 flex justify-between items-center text-sm font-semibold">
                    <span class="text-base-content/70">Total a Pagar / Despachar:</span>
                    <span class="text-lg font-black text-primary">
                        ${{ calculatedTotal.toLocaleString('es-MX', { minimumFractionDigits: 2 }) }}
                    </span>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-base-300">
                    <Button label="Cancelar" severity="secondary" text size="small" @click="createDialogVisible = false" />
                    <Button label="Registrar Pedido & Descontar Stock" type="submit" severity="primary" size="small" :loading="createForm.processing" />
                </div>
            </form>
        </Dialog>

        <!-- Dialog Ver y Editar Estado de Pedido -->
        <Dialog
            v-model:visible="viewDialogVisible"
            modal
            :header="`Detalle de Pedido - ${selectedOrder?.order_number}`"
            :style="{ width: '600px' }"
            class="p-fluid"
        >
            <div v-if="selectedOrder" class="space-y-4 pt-2 text-sm">
                <!-- Cliente & Sucursal -->
                <div class="grid grid-cols-2 gap-3 rounded-xl bg-base-200/50 p-3">
                    <div>
                        <div class="text-xs text-base-content/50 uppercase font-semibold">Cliente</div>
                        <div class="font-bold text-base-content">{{ selectedOrder.customer_name }}</div>
                        <div v-if="selectedOrder.customer_phone" class="text-xs text-base-content/70">{{ selectedOrder.customer_phone }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-base-content/50 uppercase font-semibold">Sucursal</div>
                        <div class="font-bold text-base-content">{{ selectedOrder.branch_name }}</div>
                        <div class="text-xs text-base-content/70">{{ selectedOrder.created_at }}</div>
                    </div>
                </div>

                <!-- Artículos del Pedido -->
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-base-content/50 mb-2">Artículos en la Orden</div>
                    <div class="rounded-xl border border-base-300 divide-y divide-base-300 overflow-hidden">
                        <div
                            v-for="(item, idx) in selectedOrder.items || []"
                            :key="idx"
                            class="flex justify-between items-center p-2.5 text-xs"
                        >
                            <div>
                                <span class="font-bold text-base-content">{{ item.quantity }}x</span>
                                <span class="ml-1 text-base-content/90">{{ item.name }}</span>
                                <span v-if="item.sku" class="text-[10px] text-base-content/50 font-mono ml-1">({{ item.sku }})</span>
                            </div>
                            <div class="font-mono font-semibold text-base-content">
                                ${{ Number(item.subtotal || 0).toFixed(2) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between items-center p-2 font-bold text-sm">
                        <span>Total:</span>
                        <span class="text-primary font-black">${{ Number(selectedOrder.total || 0).toFixed(2) }}</span>
                    </div>
                </div>

                <!-- Formulario Cambio de Estado -->
                <form @submit.prevent="submitUpdateOrderStatus" class="border-t border-base-300 pt-3 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-base-content/80">Estado del Pedido</label>
                            <Select
                                v-model="editStatusForm.status"
                                :options="[
                                    { label: 'Completado', value: 'completed' },
                                    { label: 'Pendiente', value: 'pending' },
                                    { label: 'Cancelado (Restaura Stock)', value: 'cancelled' },
                                ]"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full text-xs mt-1"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-base-content/80">Estado de Pago</label>
                            <Select
                                v-model="editStatusForm.payment_status"
                                :options="[
                                    { label: 'Pagado', value: 'paid' },
                                    { label: 'Pendiente', value: 'unpaid' },
                                    { label: 'Parcial', value: 'partial' },
                                ]"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full text-xs mt-1"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-base-content/80">Notas de Entrega / Despacho</label>
                        <Textarea v-model="editStatusForm.notes" rows="2" class="w-full text-xs mt-1" placeholder="Observaciones adicionales..." />
                    </div>

                    <div v-if="editStatusForm.errors.status" class="text-xs text-error font-medium">
                        {{ editStatusForm.errors.status }}
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button label="Cerrar" severity="secondary" text size="small" @click="viewDialogVisible = false" />
                        <Button v-if="can?.edit" label="Guardar Cambios" type="submit" severity="primary" size="small" :loading="editStatusForm.processing" />
                    </div>
                </form>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
