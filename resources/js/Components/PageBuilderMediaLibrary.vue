<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import { getPageBuilder, sharedPageBuilderStore } from '@myissue/vue-website-page-builder';

const closeMediaLibraryModal = inject('closeMediaLibraryModal', () => {});

const uploading = ref(false);
const loading = ref(false);
const errorMsg = ref('');
const items = ref([]);
const query = ref('');

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return items.value;
    return items.value.filter((it) => String(it.path ?? '').toLowerCase().includes(q));
});

async function loadMedia() {
    loading.value = true;
    errorMsg.value = '';
    try {
        const res = await window.axios.get(route('userpage.media'));
        items.value = Array.isArray(res?.data?.items) ? res.data.items : [];
    } catch (e) {
        errorMsg.value = e?.message ?? 'Error al cargar la librería.';
        items.value = [];
    } finally {
        loading.value = false;
    }
}

async function applyUrl(url) {
    if (!url) return;
    const pb = getPageBuilder();

    // Algunos componentes usan <img>, otros usan background-image o data-image.
    const el = sharedPageBuilderStore.getElement;
    if (el && el instanceof HTMLElement) {
        const tag = (el.tagName || '').toUpperCase();
        if (tag === 'IMG') {
            el.setAttribute('src', url);
            await pb.applySelectedImage({ src: url });
        } else {
            // Si seleccionaron el contenedor, pero dentro hay imágenes, aplicar al/los <img> internos
            const imgs = Array.from(el.querySelectorAll('img'));
            if (imgs.length === 1) {
                imgs[0].setAttribute('src', url);
            } else if (imgs.length > 1) {
                // Caso como "Three Square Images": reemplazar placeholders de todas las imágenes internas
                imgs.forEach((img) => {
                    const src = img.getAttribute('src') ?? '';
                    const isPlaceholder =
                        src.startsWith('data:image/svg+xml') ||
                        src === '' ||
                        src === '#' ||
                        src === 'about:blank';
                    if (isPlaceholder) {
                        img.setAttribute('src', url);
                    }
                });
            } else if (el.hasAttribute('data-image')) {
                el.setAttribute('data-image', url);
            } else {
                // fallback: background-image en cualquier contenedor
                el.style.backgroundImage = `url("${url}")`;
            }
            // Asegurar que el store conozca la imagen base cuando aplique
            await pb.applySelectedImage({ src: url });
        }
        // Fuerza persistencia del DOM actual al draft/localStorage del builder
        await pb.handleManualSave(true);
    } else {
        // Fallback: si no hay elemento seleccionado, usar el método estándar
        await pb.applySelectedImage({ src: url });
        await pb.handleManualSave(true);
    }

    closeMediaLibraryModal?.();
}

async function uploadAndApply(file) {
    errorMsg.value = '';
    if (!file) return;

    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('image', file);

        const res = await window.axios.post(route('userpage.upload'), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const url = res?.data?.url;
        if (!url) {
            throw new Error('Upload failed: missing url');
        }

        // refrescar librería y aplicar
        await loadMedia();
        await applyUrl(url);
    } catch (e) {
        errorMsg.value = e?.message ?? 'Error al subir imagen.';
    } finally {
        uploading.value = false;
    }
}

onMounted(loadMedia);
</script>

<template>
    <div class="space-y-4 p-2 max-h-[80vh] flex flex-col">
        <div>
            <div class="text-xl font-bold text-base-content">Biblioteca de Medios</div>
            <div class="text-sm text-base-content/70 mt-1">Selecciona una imagen existente o sube una nueva para tu diseño.</div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <input v-model="query" class="input input-bordered w-full pr-10 shadow-sm" placeholder="Buscar por nombre..." />
                <span class="absolute right-3 top-3 opacity-50">🔍</span>
            </div>
            <button type="button" class="btn btn-outline" :disabled="loading" @click="loadMedia">
                <span v-if="loading" class="loading loading-spinner loading-sm"></span>
                <span v-else>↻ Recargar</span>
            </button>
        </div>

        <div class="bg-base-200/50 rounded-xl p-6 border-2 border-base-300 border-dashed text-center hover:bg-base-200 transition-colors">
            <label class="cursor-pointer flex flex-col items-center justify-center gap-3">
                <div class="bg-primary/10 text-primary rounded-full p-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                </div>
                <div>
                    <span class="font-semibold text-primary">Haz clic para subir</span> o arrastra una imagen
                </div>
                <input
                    class="hidden"
                    type="file"
                    accept="image/*"
                    :disabled="uploading"
                    @change="(e) => uploadAndApply(e?.target?.files?.[0])"
                />
            </label>
            <div v-if="uploading" class="text-sm text-primary font-medium mt-3 flex items-center justify-center gap-2">
                <span class="loading loading-spinner loading-xs"></span> Subiendo tu imagen...
            </div>
        </div>

        <div v-if="errorMsg" class="alert alert-error text-sm shadow-sm">{{ errorMsg }}</div>

        <div class="flex-1 overflow-y-auto pr-2 pb-2 min-h-[200px]">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <button
                    v-for="it in filtered"
                    :key="it.path"
                    type="button"
                    class="group relative rounded-xl border-2 border-transparent bg-base-200 overflow-hidden hover:border-primary hover:shadow-md transition-all aspect-square flex items-center justify-center p-2"
                    @click="applyUrl(it.url)"
                    :title="it.path"
                >
                    <img :src="it.url" alt="" class="w-full h-full object-contain drop-shadow-sm group-hover:scale-105 transition-transform duration-300" />
                    <div class="absolute inset-0 bg-base-content/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center text-base-100 gap-1 backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="text-sm font-bold">Aplicar</span>
                    </div>
                </button>
            </div>

            <div v-if="!loading && !filtered.length" class="text-center p-10 bg-base-200/50 rounded-xl border border-base-300 mt-4">
                <div class="text-5xl mb-3 opacity-50">🖼️</div>
                <div class="font-semibold text-base-content/80">Aún no hay imágenes</div>
                <div class="text-sm text-base-content/60 mt-1">Sube la primera imagen para comenzar a personalizar tu diseño.</div>
            </div>
        </div>
    </div>
</template>

