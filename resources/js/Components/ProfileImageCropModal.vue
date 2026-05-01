<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    imageSrc: {
        type: String,
        default: '',
    },
    /** Ancho / alto del recorte (p. ej. 3 = banner 3:1, 1 = avatar cuadrado). */
    aspectRatio: {
        type: Number,
        default: 1,
    },
    title: {
        type: String,
        default: 'Ajustar imagen',
    },
    kind: {
        type: String,
        default: 'banner',
        validator: (v) => ['banner', 'avatar'].includes(v),
    },
});

const emit = defineEmits(['update:visible', 'applied']);

const imgRef = ref(null);
const previewRef = ref(null);
let cropper = null;

const previewLabel = computed(() =>
    props.kind === 'banner' ? 'Vista previa del banner' : 'Vista previa del avatar',
);

const previewClass = computed(() =>
    props.kind === 'banner'
        ? 'h-[88px] w-full overflow-hidden rounded-lg border-2 border-gray-300 bg-slate-600 dark:border-gray-600'
        : 'mx-auto h-[100px] w-[100px] overflow-hidden rounded-full border-2 border-gray-300 bg-slate-600 dark:border-gray-600',
);

function destroyCropper() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
}

watch(
    () => [props.visible, props.imageSrc],
    async ([vis, src]) => {
        destroyCropper();
        if (!vis || !src) {
            return;
        }
        try {
            await nextTick();
            const el = imgRef.value;
            if (!el) {
                return;
            }
            if (!el.complete || el.naturalWidth === 0) {
                try {
                    await new Promise((resolve, reject) => {
                        el.onload = () => resolve();
                        el.onerror = () => reject(new Error('Imagen inválida'));
                    });
                } catch {
                    return;
                }
            }
            if (!el.naturalWidth) {
                return;
            }
            await nextTick();
            cropper = new Cropper(el, {
                aspectRatio: props.aspectRatio,
                viewMode: 2,
                dragMode: 'move',
                autoCropArea: 0.9,
                restore: false,
                guides: true,
                center: true,
                highlight: true,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                responsive: true,
                preview: previewRef.value || undefined,
            });
        } catch {
            destroyCropper();
        }
    },
    { flush: 'post' },
);

onBeforeUnmount(destroyCropper);

function close() {
    emit('update:visible', false);
}

function apply() {
    if (!cropper) {
        return;
    }
    const maxW = props.kind === 'banner' ? 2000 : 800;
    const maxH = props.kind === 'banner' ? Math.round(2000 / props.aspectRatio) : 800;
    const canvas = cropper.getCroppedCanvas({
        maxWidth: maxW,
        maxHeight: maxH,
        imageSmoothingQuality: 'high',
    });
    canvas.toBlob(
        (blob) => {
            if (!blob) {
                return;
            }
            const name = props.kind === 'banner' ? 'banner.jpg' : 'avatar.jpg';
            const file = new File([blob], name, { type: 'image/jpeg' });
            emit('applied', file);
            emit('update:visible', false);
        },
        'image/jpeg',
        0.92,
    );
}
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :header="title"
        class="profile-crop-dialog"
        :style="{ width: 'min(96vw, 760px)' }"
        @update:visible="(v) => emit('update:visible', v)"
    >
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            Arrastra la imagen para encuadrarla. Usa la rueda del ratón o los bordes del recuadro para acercar o
            alejar. El marco mantiene la misma forma que verás en tu perfil.
        </p>

        <div class="overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-900">
            <img
                ref="imgRef"
                :src="imageSrc"
                alt=""
                class="block max-h-[min(50vh,420px)] max-w-full"
            />
        </div>

        <div class="mt-4">
            <p class="mb-2 text-xs font-medium text-gray-700 dark:text-gray-300">
                {{ previewLabel }}
            </p>
            <div ref="previewRef" :class="previewClass" />
        </div>

        <template #footer>
            <Button type="button" label="Cancelar" severity="secondary" class="p-button-text" @click="close" />
            <Button type="button" label="Guardar imagen" icon="pi pi-check" @click="apply" />
        </template>
    </Dialog>
</template>

<style scoped>
:deep(.cropper-container) {
    max-height: min(50vh, 420px);
}
:deep(.cropper-crop-box),
:deep(.cropper-view-box) {
    border-radius: 0;
}
</style>
