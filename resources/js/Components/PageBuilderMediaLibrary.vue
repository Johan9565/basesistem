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
    <div class="space-y-3">
        <div class="text-sm opacity-70">Selecciona una imagen existente o sube una nueva.</div>

        <div class="flex gap-2">
            <input v-model="query" class="input input-bordered w-full" placeholder="Buscar por nombre..." />
            <button type="button" class="btn btn-ghost" :disabled="loading" @click="loadMedia">Recargar</button>
        </div>

        <input
            class="file-input file-input-bordered w-full"
            type="file"
            accept="image/*"
            :disabled="uploading"
            @change="(e) => uploadAndApply(e?.target?.files?.[0])"
        />

        <div v-if="loading" class="text-sm opacity-70">Cargando…</div>
        <div v-if="errorMsg" class="text-error text-sm">{{ errorMsg }}</div>
        <div v-if="uploading" class="text-sm opacity-70">Subiendo…</div>

        <div class="grid grid-cols-3 md:grid-cols-4 gap-2">
            <button
                v-for="it in filtered"
                :key="it.path"
                type="button"
                class="rounded border border-base-300 overflow-hidden hover:border-primary transition"
                @click="applyUrl(it.url)"
                :title="it.path"
            >
                <img :src="it.url" alt="" class="w-full h-20 object-cover" />
            </button>
        </div>

        <div v-if="!loading && !filtered.length" class="text-sm opacity-70">Sin imágenes.</div>
    </div>
</template>

