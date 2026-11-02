<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';

const MAX_IMAGES = 5;

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

// Vistas: 'grid' (Catálogo Visual) y 'list' (Tabla Detallada)
const viewMode = ref('grid');
const imageModalVisible = ref(false);
const selectedProductForImage = ref(null);
const lightboxIndex = ref(0);

// ── Multi-imagen ──
const newImageFiles = ref([]);       // File[] — archivos nuevos seleccionados
const newImagePreviews = ref([]);    // { file, url }[] — previews de nuevos archivos
const existingImages = ref([]);      // Imágenes ya guardadas en BD
const removedImageIds = ref([]);     // IDs de imágenes existentes a eliminar
const primaryImageId = ref(null);    // ID de imagen principal
const dragOverZone = ref(false);
const fileInputRef = ref(null);

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

// ── Lightbox con galería ──
function openImageModal(product, index = 0) {
    selectedProductForImage.value = product;
    lightboxIndex.value = index;
    imageModalVisible.value = true;
}

function lightboxImages() {
    const product = selectedProductForImage.value;
    if (!product) return [];
    if (product.images && product.images.length > 0) {
        return product.images.filter(img => img.url).sort((a, b) => a.order - b.order);
    }
    if (product.image_url) {
        return [{ url: product.image_url, is_primary: true }];
    }
    return [];
}

function lightboxPrev() {
    const imgs = lightboxImages();
    if (imgs.length <= 1) return;
    lightboxIndex.value = (lightboxIndex.value - 1 + imgs.length) % imgs.length;
}

function lightboxNext() {
    const imgs = lightboxImages();
    if (imgs.length <= 1) return;
    lightboxIndex.value = (lightboxIndex.value + 1) % imgs.length;
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
    is_active: true,
    images: [],
    remove_image_ids: [],
    image_order: [],
    primary_image_id: null,
});

// ── Conteo total de imágenes ──
const totalImageCount = computed(() => {
    return existingImages.value.filter(img => !removedImageIds.value.includes(img.id)).length + newImageFiles.value.length;
});

const remainingSlots = computed(() => MAX_IMAGES - totalImageCount.value);

// ── Todas las imágenes para mostrar en el editor (existentes + nuevas) ──
const allEditorImages = computed(() => {
    const existing = existingImages.value
        .filter(img => !removedImageIds.value.includes(img.id))
        .map(img => ({
            type: 'existing',
            id: img.id,
            url: img.url,
            order: img.order,
            is_primary: img.id === primaryImageId.value,
            is_processed: img.is_processed,
        }));

    const newOnes = newImagePreviews.value.map((preview, idx) => ({
        type: 'new',
        index: idx,
        url: preview.url,
        fileName: preview.file.name,
        is_primary: existingImages.value.filter(img => !removedImageIds.value.includes(img.id)).length === 0 && idx === 0 && !primaryImageId.value,
    }));

    return [...existing, ...newOnes];
});

function openCreateDialog() {
    isEditing.value = false;
    editingProductId.value = null;
    resetImageState();
    form.reset();
    form.clearErrors();
    form.is_active = true;
    if (fileInputRef.value) fileInputRef.value.value = '';
    const mainBranch = props.branches?.find((b) => b.is_main) || props.branches?.[0];
    form.branch_id = mainBranch ? mainBranch.id : '';
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

    // Cargar imágenes existentes
    resetImageState();
    if (product.images && product.images.length > 0) {
        existingImages.value = product.images.map(img => ({ ...img }));
        const primary = product.images.find(img => img.is_primary);
        primaryImageId.value = primary ? primary.id : (product.images[0]?.id || null);
    }

    if (fileInputRef.value) fileInputRef.value.value = '';
    dialogVisible.value = true;
}

function resetImageState() {
    // Revocar URLs de previews anteriores
    newImagePreviews.value.forEach(p => URL.revokeObjectURL(p.url));
    newImageFiles.value = [];
    newImagePreviews.value = [];
    existingImages.value = [];
    removedImageIds.value = [];
    primaryImageId.value = null;
    dragOverZone.value = false;
}

// ── Manejo de archivos ──
function onFilesSelect(e) {
    const files = Array.from(e.target?.files || []);
    addFiles(files);
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function addFiles(files) {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxSize = 10 * 1024 * 1024; // 10MB

    for (const file of files) {
        if (totalImageCount.value + 1 > MAX_IMAGES) {
            break;
        }

        if (!allowedTypes.includes(file.type)) {
            continue;
        }

        if (file.size > maxSize) {
            continue;
        }

        newImageFiles.value.push(file);
        newImagePreviews.value.push({
            file,
            url: URL.createObjectURL(file),
        });
    }
}

function removeNewImage(index) {
    URL.revokeObjectURL(newImagePreviews.value[index].url);
    newImageFiles.value.splice(index, 1);
    newImagePreviews.value.splice(index, 1);
}

function removeExistingImage(imageId) {
    removedImageIds.value.push(imageId);
    // Si se quitó la primaria, reasignar
    if (primaryImageId.value === imageId) {
        const remaining = existingImages.value.filter(img => !removedImageIds.value.includes(img.id));
        primaryImageId.value = remaining.length > 0 ? remaining[0].id : null;
    }
}

function setPrimary(imageId) {
    primaryImageId.value = imageId;
}

// ── Drag & Drop ──
function onDragOver(e) {
    e.preventDefault();
    dragOverZone.value = true;
}

function onDragLeave() {
    dragOverZone.value = false;
}

function onDrop(e) {
    e.preventDefault();
    dragOverZone.value = false;
    const files = Array.from(e.dataTransfer?.files || []);
    addFiles(files);
}

// ── Reordenar ──
const dragItem = ref(null);
const dragOverItem = ref(null);

function onImageDragStart(idx) {
    dragItem.value = idx;
}

function onImageDragOver(e, idx) {
    e.preventDefault();
    dragOverItem.value = idx;
}

function onImageDrop(idx) {
    if (dragItem.value === null || dragItem.value === idx) {
        dragItem.value = null;
        dragOverItem.value = null;
        return;
    }

    // Reordenar el array visual de imágenes existentes
    const allImgs = [...allEditorImages.value];
    const moved = allImgs.splice(dragItem.value, 1)[0];
    allImgs.splice(idx, 0, moved);

    // Reconstruir el orden de existentes
    const reorderedExisting = allImgs.filter(i => i.type === 'existing');
    reorderedExisting.forEach((img, i) => {
        const found = existingImages.value.find(e => e.id === img.id);
        if (found) found.order = i + 1;
    });

    // Reconstruir el array de nuevos archivos en el orden correcto
    const reorderedNew = allImgs.filter(i => i.type === 'new');
    const newFiles = [];
    const newPreviews = [];
    reorderedNew.forEach(img => {
        newFiles.push(newImageFiles.value[img.index]);
        newPreviews.push(newImagePreviews.value[img.index]);
    });
    newImageFiles.value = newFiles;
    newImagePreviews.value = newPreviews;

    dragItem.value = null;
    dragOverItem.value = null;
}

// ── Submit ──
function submitForm() {
    // Preparar los archivos y metadatos de imágenes
    form.images = newImageFiles.value;
    form.remove_image_ids = removedImageIds.value;
    form.primary_image_id = primaryImageId.value;

    // Orden de imágenes existentes (sin las eliminadas)
    const ordered = existingImages.value
        .filter(img => !removedImageIds.value.includes(img.id))
        .sort((a, b) => a.order - b.order)
        .map(img => img.id);
    form.image_order = ordered;

    if (isEditing.value) {
        form.transform((data) => ({
            ...data,
            _method: 'patch',
        })).post(route('products.update', editingProductId.value), {
            forceFormData: true,
            onSuccess: () => { dialogVisible.value = false; },
        });
    } else {
        form.post(route('products.store'), {
            forceFormData: true,
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

// ── Helpers de vista ──
function getProductPrimaryUrl(product) {
    if (product.images && product.images.length > 0) {
        const primary = product.images.find(i => i.is_primary) || product.images[0];
        return primary?.url || null;
    }
    return product.image_url || null;
}

function getProductImageCount(product) {
    return product.images?.length || (product.image_url ? 1 : 0);
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
                                    v-if="getProductPrimaryUrl(product)"
                                    :src="getProductPrimaryUrl(product)"
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

                                <!-- Badge conteo de fotos -->
                                <div v-if="getProductImageCount(product) > 1" class="absolute bottom-3 right-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-black/60 text-white backdrop-blur-md">
                                        <i class="pi pi-images text-[10px]"></i>
                                        {{ getProductImageCount(product) }}
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

                                <div v-if="getProductPrimaryUrl(product)" class="mt-2 pt-1.5 flex items-center gap-1 text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">
                                    <i class="pi pi-whatsapp text-xs"></i>
                                    <span>Envío de {{ getProductImageCount(product) > 1 ? getProductImageCount(product) + ' fotos activo' : 'foto activo' }} para el bot</span>
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
                                                    v-if="getProductPrimaryUrl(product)"
                                                    :src="getProductPrimaryUrl(product)"
                                                    :alt="product.name"
                                                    class="h-full w-full object-cover group-hover/thumb:scale-110 transition-transform"
                                                    loading="lazy"
                                                />
                                                <span v-else class="text-xl opacity-40">📦</span>
                                                <div v-if="getProductPrimaryUrl(product)" class="absolute inset-0 bg-black/30 opacity-0 group-hover/thumb:opacity-100 flex items-center justify-center transition-opacity text-white text-[10px]">
                                                    <i class="pi pi-eye"></i>
                                                </div>
                                                <!-- Conteo de fotos en el thumb -->
                                                <div v-if="getProductImageCount(product) > 1" class="absolute -bottom-0.5 -right-0.5 bg-primary text-primary-content text-[9px] font-bold rounded-full w-4 h-4 flex items-center justify-center shadow">
                                                    {{ getProductImageCount(product) }}
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-base-content truncate">{{ product.name }}</div>
                                                <div class="text-xs font-mono text-primary flex items-center gap-1.5">
                                                    <span>{{ product.sku }}</span>
                                                    <span v-if="getProductPrimaryUrl(product)" class="badge badge-xs badge-success text-[9px] gap-0.5">
                                                        <i class="pi pi-image text-[8px]"></i> {{ getProductImageCount(product) }} foto{{ getProductImageCount(product) > 1 ? 's' : '' }}
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

        <!-- Modal Lightbox para ver galería de fotos del producto -->
        <Dialog
            v-model:visible="imageModalVisible"
            modal
            :header="selectedProductForImage?.name || 'Detalle del Producto'"
            :style="{ width: '620px' }"
            class="p-fluid overflow-hidden"
        >
            <div v-if="selectedProductForImage" class="space-y-4 pt-1">
                <!-- Galería principal con navegación -->
                <div class="relative w-full aspect-square rounded-2xl overflow-hidden bg-base-200 border border-base-300 shadow-inner">
                    <template v-if="lightboxImages().length > 0">
                        <img
                            :src="lightboxImages()[lightboxIndex]?.url"
                            :alt="selectedProductForImage.name"
                            class="w-full h-full object-cover transition-all duration-300"
                        />
                        <!-- Controles de navegación -->
                        <template v-if="lightboxImages().length > 1">
                            <button
                                type="button"
                                class="absolute left-3 top-1/2 -translate-y-1/2 btn btn-sm btn-circle bg-black/50 hover:bg-black/70 text-white border-none shadow-lg"
                                @click="lightboxPrev"
                            >
                                <i class="pi pi-chevron-left text-sm"></i>
                            </button>
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 btn btn-sm btn-circle bg-black/50 hover:bg-black/70 text-white border-none shadow-lg"
                                @click="lightboxNext"
                            >
                                <i class="pi pi-chevron-right text-sm"></i>
                            </button>
                            <!-- Indicador de posición -->
                            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-1.5 bg-black/50 backdrop-blur-md rounded-full px-3 py-1.5">
                                <button
                                    v-for="(img, idx) in lightboxImages()"
                                    :key="idx"
                                    type="button"
                                    class="h-2 w-2 rounded-full transition-all"
                                    :class="idx === lightboxIndex ? 'bg-white scale-125' : 'bg-white/40 hover:bg-white/60'"
                                    @click="lightboxIndex = idx"
                                ></button>
                            </div>
                        </template>
                    </template>
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-base-content/30 gap-2">
                        <span class="text-6xl">📦</span>
                        <span class="text-sm">Sin imagen disponible</span>
                    </div>
                </div>

                <!-- Miniaturas de la galería -->
                <div v-if="lightboxImages().length > 1" class="flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="(img, idx) in lightboxImages()"
                        :key="idx"
                        type="button"
                        class="h-16 w-16 rounded-xl overflow-hidden border-2 shrink-0 transition-all"
                        :class="idx === lightboxIndex ? 'border-primary shadow-md scale-105' : 'border-base-300 opacity-60 hover:opacity-100'"
                        @click="lightboxIndex = idx"
                    >
                        <img :src="img.url" :alt="`Foto ${idx + 1}`" class="h-full w-full object-cover" />
                    </button>
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

                    <div v-if="getProductPrimaryUrl(selectedProductForImage)" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2">
                        <i class="pi pi-whatsapp text-base"></i>
                        <span>
                            {{ getProductImageCount(selectedProductForImage) > 1
                                ? `Las ${getProductImageCount(selectedProductForImage)} fotografías están listas y se enviarán por WhatsApp cuando un cliente solicite fotos de este producto al asistente virtual.`
                                : 'Esta fotografía está lista y se enviará por WhatsApp cuando un cliente solicite fotos de este producto al asistente virtual.'
                            }}
                        </span>
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
            :style="{ width: '600px' }"
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

                <!-- ═══════════════════════════════════════════════ -->
                <!-- GALERÍA DE IMÁGENES (Hasta 5 fotos)            -->
                <!-- ═══════════════════════════════════════════════ -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-base-content/80">
                            📷 Fotografías del Producto
                        </label>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                            :class="totalImageCount >= MAX_IMAGES
                                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                : 'bg-base-200 text-base-content/60'"
                        >
                            {{ totalImageCount }} de {{ MAX_IMAGES }} imágenes
                        </span>
                    </div>

                    <!-- Grid de imágenes existentes + nuevas -->
                    <div v-if="allEditorImages.length > 0" class="grid grid-cols-5 gap-2 mb-3">
                        <div
                            v-for="(img, idx) in allEditorImages"
                            :key="img.type === 'existing' ? img.id : `new-${img.index}`"
                            class="relative aspect-square rounded-xl overflow-hidden border-2 transition-all group/img cursor-grab"
                            :class="[
                                img.is_primary ? 'border-primary ring-2 ring-primary/30' : 'border-base-300',
                                dragOverItem === idx ? 'scale-105 border-info' : ''
                            ]"
                            draggable="true"
                            @dragstart="onImageDragStart(idx)"
                            @dragover="onImageDragOver($event, idx)"
                            @drop="onImageDrop(idx)"
                        >
                            <img :src="img.url" :alt="`Imagen ${idx + 1}`" class="h-full w-full object-cover" />

                            <!-- Overlay con acciones -->
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover/img:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1">
                                <!-- Hacer portada -->
                                <button
                                    v-if="img.type === 'existing' && !img.is_primary"
                                    type="button"
                                    class="btn btn-xs bg-primary/90 text-white border-none hover:bg-primary"
                                    @click="setPrimary(img.id)"
                                    title="Establecer como portada"
                                >
                                    <i class="pi pi-star text-[10px]"></i>
                                </button>
                                <!-- Eliminar -->
                                <button
                                    type="button"
                                    class="btn btn-xs bg-error/90 text-white border-none hover:bg-error"
                                    @click="img.type === 'existing' ? removeExistingImage(img.id) : removeNewImage(img.index)"
                                    title="Quitar imagen"
                                >
                                    <i class="pi pi-times text-[10px]"></i>
                                </button>
                            </div>

                            <!-- Badge portada -->
                            <div v-if="img.is_primary" class="absolute top-1 left-1">
                                <span class="flex items-center gap-0.5 px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-primary text-primary-content shadow">
                                    <i class="pi pi-star-fill text-[8px]"></i> Portada
                                </span>
                            </div>

                            <!-- Badge procesando -->
                            <div v-if="img.type === 'existing' && !img.is_processed" class="absolute bottom-1 left-1">
                                <span class="flex items-center gap-0.5 px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-amber-500 text-white shadow animate-pulse">
                                    <i class="pi pi-spin pi-spinner text-[8px]"></i> Optimizando
                                </span>
                            </div>

                            <!-- Badge nuevo -->
                            <div v-if="img.type === 'new'" class="absolute bottom-1 left-1">
                                <span class="flex items-center gap-0.5 px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-info text-info-content shadow">
                                    Nueva
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Zona de Drop / Botón de Subir -->
                    <div
                        v-if="totalImageCount < MAX_IMAGES"
                        class="relative rounded-xl border-2 border-dashed transition-all p-4"
                        :class="dragOverZone
                            ? 'border-primary bg-primary/5 scale-[1.01]'
                            : 'border-base-300 bg-base-200/30 hover:border-primary/50 hover:bg-base-200/50'"
                        @dragover="onDragOver"
                        @dragleave="onDragLeave"
                        @drop="onDrop"
                    >
                        <div class="flex flex-col items-center justify-center gap-2 text-center py-2">
                            <div class="flex items-center gap-3">
                                <div class="text-2xl opacity-40">
                                    {{ dragOverZone ? '📥' : '📷' }}
                                </div>
                                <div>
                                    <label class="btn btn-sm btn-primary gap-1.5 cursor-pointer">
                                        <i class="pi pi-upload text-[10px]"></i>
                                        <span>Subir Fotos</span>
                                        <input
                                            ref="fileInputRef"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            multiple
                                            class="hidden"
                                            @change="onFilesSelect"
                                        />
                                    </label>
                                </div>
                            </div>
                            <p class="text-[11px] text-base-content/50 max-w-xs">
                                Arrastra y suelta o selecciona hasta <strong>{{ remainingSlots }}</strong> imagen{{ remainingSlots !== 1 ? 'es' : '' }} más.
                                <span class="block mt-0.5">JPEG, PNG o WebP · Máximo 10MB c/u · Optimizadas para WhatsApp automáticamente.</span>
                            </p>
                        </div>
                    </div>

                    <!-- Límite alcanzado -->
                    <div v-else class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-center">
                        <p class="text-xs text-amber-600 dark:text-amber-400 font-medium flex items-center justify-center gap-1.5">
                            <i class="pi pi-info-circle text-sm"></i>
                            Límite de {{ MAX_IMAGES }} imágenes alcanzado. Elimina una foto para subir otra.
                        </p>
                    </div>

                    <!-- Errores de validación de imágenes -->
                    <div v-if="form.errors.images" class="mt-2 text-xs text-error font-medium flex items-center gap-1">
                        <i class="pi pi-exclamation-triangle text-xs"></i>
                        {{ form.errors.images }}
                    </div>
                    <template v-for="(error, key) in form.errors" :key="key">
                        <div v-if="key.startsWith('images.')" class="mt-1 text-xs text-error font-medium flex items-center gap-1">
                            <i class="pi pi-exclamation-triangle text-xs"></i>
                            {{ error }}
                        </div>
                    </template>
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
