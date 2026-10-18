<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
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
const imagePreview = ref(null);
const fileInputRef = ref(null);

// Vistas: 'grid' (Catálogo Visual) y 'list' (Tabla Detallada)
const viewMode = ref('grid');
const imageModalVisible = ref(false);
const selectedProductForImage = ref(null);

onMounted(() => {
    const saved = localStorage.getItem('products_view_mode');
    if (saved === 'list' || saved === 'grid') {
        viewMode.value = saved;
    }
});

function setViewMode(mode) {
    viewMode.value = mode;
    localStorage.setItem('products_view_mode', mode);
}

function openImageModal(product) {
    selectedProductForImage.value = product;
    imageModalVisible.value = true;
}

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
    image: null,
    image_url: '',
    remove_image: false,
    is_active: true,
});

function openCreateDialog() {
    isEditing.value = false;
    editingProductId.value = null;
    imagePreview.value = null;
    form.reset();
    form.clearErrors();
    form.image = null;
    form.image_url = '';
    form.remove_image = false;
    if (fileInputRef.value) fileInputRef.value.value = '';
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
    form.image = null;
    form.image_url = product.image_url ?? '';
    form.remove_image = false;
    imagePreview.value = product.image_url || null;
    if (fileInputRef.value) fileInputRef.value.value = '';
    form.is_active = Boolean(product.is_active ?? true);
    dialogVisible.value = true;
}

function onFileSelect(e) {
    const file = e.target.files?.[0];
    if (file) {
        form.image = file;
        form.remove_image = false;
        imagePreview.value = URL.createObjectURL(file);
    }
}

function removeSelectedImage() {
    form.image = null;
    form.image_url = '';
    form.remove_image = true;
    imagePreview.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function submitForm() {
    if (isEditing.value) {
        if (form.image) {
            form.transform((data) => ({
                ...data,
                _method: 'patch',
            })).post(route('products.update', editingProductId.value), {
                onSuccess: () => { dialogVisible.value = false; },
            });
        } else {
            form.patch(route('products.update', editingProductId.value), {
                onSuccess: () => { dialogVisible.value = false; },
            });
        }
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
                        Gestiona productos con fotos, control de existencias y envío automatizado de imágenes en el bot.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        label="Nuevo Producto"
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
                <!-- Barra de Búsqueda, Filtro de Sucursal y Selector de Vista -->
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-xs">
                    <div class="flex flex-1 flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <div class="relative flex-1">
                            <InputText
                                v-model="search"
                                placeholder="Buscar por nombre, SKU o categoría..."
                                class="w-full text-sm"
                                @keydown.enter="applyFilters"
                            />
                        </div>
                        <div v-if="branches && branches.length > 0" class="w-full sm:w-60">
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

                    <!-- Selector de Vista: Catálogo Visual vs Lista Detallada -->
                    <div class="flex items-center gap-1 p-1 bg-base-200/80 rounded-xl border border-base-300 self-end md:self-auto shrink-0">
                        <button
                            type="button"
                            @click="setViewMode('grid')"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-all"
                            :class="viewMode === 'grid'
                                ? 'bg-primary text-primary-content shadow-xs'
                                : 'text-base-content/70 hover:text-base-content hover:bg-base-100/50'"
                            title="Vista en cuadrícula con fotos destacadas para clientes y equipo"
                        >
                            <i class="pi pi-th-large text-xs"></i>
                            <span class="hidden sm:inline">Catálogo Visual</span>
                            <span class="sm:hidden">Fotos</span>
                        </button>
                        <button
                            type="button"
                            @click="setViewMode('list')"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-all"
                            :class="viewMode === 'list'
                                ? 'bg-primary text-primary-content shadow-xs'
                                : 'text-base-content/70 hover:text-base-content hover:bg-base-100/50'"
                            title="Vista en tabla detallada para administración de inventario"
                        >
                            <i class="pi pi-list text-xs"></i>
                            <span class="hidden sm:inline">Lista Detallada</span>
                            <span class="sm:hidden">Tabla</span>
                        </button>
                    </div>
                </div>

                <!-- VISTA 1: CATÁLOGO VISUAL (Tarjetas con fotos destacadas para usuarios y clientes) -->
                <div v-if="viewMode === 'grid'" class="space-y-6">
                    <div
                        v-if="products.data && products.data.length > 0"
                        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5"
                    >
                        <div
                            v-for="product in products.data"
                            :key="product.id || product._id"
                            class="group relative flex flex-col rounded-2xl border border-base-300 bg-base-100 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-primary/40 overflow-hidden"
                        >
                            <!-- Imagen y Badges -->
                            <div class="relative aspect-square w-full bg-base-200 overflow-hidden cursor-pointer" @click="openImageModal(product)">
                                <img
                                    v-if="product.image_url"
                                    :src="product.image_url"
                                    :alt="product.name"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    loading="lazy"
                                />
                                <div v-else class="h-full w-full flex flex-col items-center justify-center text-base-content/30 gap-2">
                                    <span class="text-4xl">📦</span>
                                    <span class="text-xs">Sin imagen</span>
                                </div>

                                <!-- Overlay sutil en hover -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-circle bg-base-100/90 hover:bg-base-100 text-base-content border-none shadow-lg transform scale-90 group-hover:scale-100 transition-transform"
                                        title="Ver foto en tamaño completo"
                                    >
                                        <i class="pi pi-search-plus text-sm"></i>
                                    </button>
                                </div>

                                <!-- Badge Categoría superior izquierda -->
                                <div class="absolute top-3 left-3 flex flex-col gap-1 items-start">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-neutral/80 text-neutral-content backdrop-blur-md shadow-xs">
                                        {{ product.category || 'General' }}
                                    </span>
                                </div>

                                <!-- Badge Stock superior derecha -->
                                <div class="absolute top-3 right-3">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold backdrop-blur-md shadow-xs"
                                        :class="product.stock <= (product.min_stock || 0)
                                            ? 'bg-rose-950/80 text-rose-200 border border-rose-500/30'
                                            : 'bg-emerald-950/80 text-emerald-200 border border-emerald-500/30'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="product.stock <= (product.min_stock || 0) ? 'bg-rose-400 animate-pulse' : 'bg-emerald-400'"></span>
                                        {{ product.stock }} en stock
                                    </span>
                                </div>
                            </div>

                            <!-- Contenido de la Tarjeta -->
                            <div class="flex flex-1 flex-col p-4">
                                <!-- SKU y Sucursal -->
                                <div class="flex items-center justify-between text-xs text-base-content/60 gap-2 mb-1.5">
                                    <span class="font-mono font-medium text-primary text-[11px]">{{ product.sku }}</span>
                                    <span class="inline-flex items-center gap-1 truncate text-[11px]">
                                        <i class="pi pi-building text-[10px]"></i>
                                        {{ product.branch_name }}
                                    </span>
                                </div>

                                <!-- Nombre -->
                                <h3 class="font-bold text-base-content text-sm line-clamp-1 mb-1" :title="product.name">
                                    {{ product.name }}
                                </h3>

                                <!-- Descripción -->
                                <p class="text-xs text-base-content/60 line-clamp-2 mb-3 min-h-[2rem]">
                                    {{ product.description || 'Sin descripción adicional registrada.' }}
                                </p>

                                <!-- Estado del Bot WhatsApp -->
                                <div class="mt-auto pt-2 border-t border-base-200 flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] uppercase font-semibold text-base-content/50 block">Precio</span>
                                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">
                                            ${{ Number(product.price).toFixed(2) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            size="small"
                                            severity="secondary"
                                            title="Editar producto"
                                            @click="openEditDialog(product)"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            size="small"
                                            severity="danger"
                                            title="Eliminar producto"
                                            @click="deleteProduct(product)"
                                        />
                                    </div>
                                </div>

                                <div v-if="product.image_url" class="mt-2 pt-1.5 flex items-center gap-1 text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">
                                    <i class="pi pi-whatsapp text-xs"></i>
                                    <span>Envío de foto activo para el bot</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mensaje si no hay productos -->
                    <div v-else class="rounded-2xl border border-base-300 bg-base-100 p-12 text-center text-sm text-base-content/50 shadow-sm">
                        No hay productos registrados en el inventario para los filtros seleccionados.
                    </div>
                </div>

                <!-- VISTA 2: LISTA DETALLADA (Tabla completa para administración de inventario) -->
                <div v-else-if="viewMode === 'list'" class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-xl">
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
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="h-12 w-12 rounded-xl bg-base-200 border border-base-300 flex items-center justify-center overflow-hidden shrink-0 shadow-xs cursor-pointer group/thumb relative"
                                                @click="openImageModal(product)"
                                                title="Click para agrandar imagen"
                                            >
                                                <img
                                                    v-if="product.image_url"
                                                    :src="product.image_url"
                                                    :alt="product.name"
                                                    class="h-full w-full object-cover group-hover/thumb:scale-110 transition-transform"
                                                    loading="lazy"
                                                />
                                                <span v-else class="text-xl opacity-40">📦</span>
                                                <div v-if="product.image_url" class="absolute inset-0 bg-black/30 opacity-0 group-hover/thumb:opacity-100 flex items-center justify-center transition-opacity text-white text-[10px]">
                                                    <i class="pi pi-eye"></i>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-base-content truncate">{{ product.name }}</div>
                                                <div class="text-xs font-mono text-primary flex items-center gap-1.5">
                                                    <span>{{ product.sku }}</span>
                                                    <span v-if="product.image_url" class="badge badge-xs badge-success text-[9px] gap-0.5">
                                                        <i class="pi pi-image text-[8px]"></i> con foto
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
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

                <!-- Paginación común para ambas vistas -->
                <div v-if="products.links && products.links.length > 3" class="flex flex-wrap justify-center items-center gap-1.5 pt-4">
                    <template v-for="(link, i) in products.links" :key="i">
                        <span
                            v-if="!link.url"
                            class="px-3.5 py-2 text-xs text-base-content/40 rounded-xl border border-transparent select-none"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="px-3.5 py-2 text-xs font-semibold rounded-xl border transition-all duration-200"
                            :class="link.active
                                ? 'bg-primary text-primary-content border-primary shadow-xs'
                                : 'bg-base-100 hover:bg-base-200 border-base-300 text-base-content hover:border-base-content/20'"
                            v-html="link.label"
                            preserve-state
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal Lightbox para ver foto ampliada del producto -->
        <Dialog
            v-model:visible="imageModalVisible"
            modal
            :header="selectedProductForImage?.name || 'Detalle del Producto'"
            :style="{ width: '560px' }"
            class="p-fluid overflow-hidden"
        >
            <div v-if="selectedProductForImage" class="space-y-4 pt-1">
                <div class="relative w-full aspect-square rounded-2xl overflow-hidden bg-base-200 border border-base-300 shadow-inner">
                    <img
                        v-if="selectedProductForImage.image_url"
                        :src="selectedProductForImage.image_url"
                        :alt="selectedProductForImage.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-base-content/30 gap-2">
                        <span class="text-6xl">📦</span>
                        <span class="text-sm">Sin imagen disponible</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs text-primary font-bold">
                            {{ selectedProductForImage.sku }}
                        </span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-base-200 border border-base-300">
                            {{ selectedProductForImage.category || 'General' }}
                        </span>
                    </div>

                    <p class="text-sm text-base-content/80">
                        {{ selectedProductForImage.description || 'Sin descripción registrada.' }}
                    </p>

                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-base-300">
                        <div class="p-3 rounded-xl bg-base-200/50 border border-base-300">
                            <span class="text-[11px] text-base-content/60 block">Precio de Venta</span>
                            <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                                ${{ Number(selectedProductForImage.price).toFixed(2) }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-base-200/50 border border-base-300">
                            <span class="text-[11px] text-base-content/60 block">Disponibilidad</span>
                            <span class="text-sm font-semibold flex items-center gap-1.5 mt-1" :class="selectedProductForImage.stock <= (selectedProductForImage.min_stock || 0) ? 'text-rose-500' : 'text-emerald-600 dark:text-emerald-400'">
                                <span class="h-2 w-2 rounded-full" :class="selectedProductForImage.stock <= (selectedProductForImage.min_stock || 0) ? 'bg-rose-500' : 'bg-emerald-500'"></span>
                                {{ selectedProductForImage.stock }} unidades ({{ selectedProductForImage.branch_name }})
                            </span>
                        </div>
                    </div>

                    <div v-if="selectedProductForImage.image_url" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2">
                        <i class="pi pi-whatsapp text-base"></i>
                        <span>Esta fotografía está lista y se enviará por WhatsApp cuando un cliente solicite fotos de este producto al asistente virtual.</span>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <Button label="Cerrar" severity="secondary" size="small" @click="imageModalVisible = false" />
                </div>
            </div>
        </Dialog>

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

                <!-- Imagen del Producto -->
                <div>
                    <label class="text-xs font-semibold text-base-content/80 block mb-1">
                        Fotografía / Imagen del Producto
                    </label>
                    <div class="flex items-center gap-4 p-3 rounded-xl border border-base-300 bg-base-200/40">
                        <div class="h-16 w-16 rounded-xl border border-base-300 bg-base-100 flex items-center justify-center overflow-hidden shrink-0 shadow-xs relative">
                            <img
                                v-if="imagePreview"
                                :src="imagePreview"
                                alt="Vista previa"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="text-2xl opacity-30">📷</span>
                        </div>
                        <div class="flex-1 min-w-0 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <label class="btn btn-xs btn-primary gap-1 cursor-pointer">
                                    <i class="pi pi-upload text-[10px]"></i>
                                    <span>{{ imagePreview ? 'Cambiar Foto' : 'Subir Foto' }}</span>
                                    <input
                                        ref="fileInputRef"
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="onFileSelect"
                                    />
                                </label>
                                <button
                                    v-if="imagePreview"
                                    type="button"
                                    class="btn btn-xs btn-ghost text-error hover:bg-error/10"
                                    @click="removeSelectedImage"
                                >
                                    <i class="pi pi-trash text-[10px]"></i>
                                    Quitar
                                </button>
                            </div>
                            <p class="text-[11px] text-base-content/50">
                                Sube una imagen (JPG, PNG, WEBP). El bot podrá enviarla por WhatsApp cuando los clientes la soliciten.
                            </p>
                        </div>
                    </div>
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
