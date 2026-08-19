<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    result: {
        type: Object,
        default: null,
    },
    languages: {
        type: Array,
        default: () => [],
    },
    tools: {
        type: Object,
        default: () => ({
            pdftotext: false,
            pdftoppm: false,
            tesseract: false,
            pdfinfo: false,
        }),
    },
});

const toast = useToast();
const fileInput = ref(null);
const dragging = ref(false);
const copied = ref(false);
const selectedPage = ref('all');

const modeOptions = [
    { label: 'Automático (texto nativo o OCR)', value: 'auto' },
    { label: 'Solo texto embebido del PDF', value: 'text' },
    { label: 'Forzar OCR (PDFs escaneados)', value: 'ocr' },
];

const form = useForm({
    pdf: null,
    language: 'spa+eng',
    mode: 'auto',
});

const selectedFileName = ref('');

const toolsReady = computed(
    () => props.tools?.pdftotext && props.tools?.pdftoppm && props.tools?.tesseract,
);

const pageOptions = computed(() => {
    const pages = props.result?.pages ?? [];
    return [
        { label: 'Todas las páginas', value: 'all' },
        ...pages.map((p) => ({
            label: `Página ${p.number}`,
            value: String(p.number),
        })),
    ];
});

const displayedText = computed(() => {
    if (!props.result) return '';
    if (selectedPage.value === 'all') {
        return props.result.text ?? '';
    }
    const page = (props.result.pages ?? []).find(
        (p) => String(p.number) === String(selectedPage.value),
    );
    return page?.text ?? '';
});

const methodLabel = computed(() => {
    const method = props.result?.method;
    if (method === 'native') return 'Texto embebido';
    if (method === 'ocr') return 'OCR';
    if (method === 'mixed') return 'Mixto (texto + OCR)';
    return method || '—';
});

watch(
    () => props.result,
    () => {
        selectedPage.value = 'all';
        copied.value = false;
    },
);

function onDragOver(event) {
    event.preventDefault();
    dragging.value = true;
}

function onDragLeave() {
    dragging.value = false;
}

function onDrop(event) {
    event.preventDefault();
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) {
        assignFile(file);
    }
}

function onFileChange(event) {
    const file = event.target.files?.[0];
    if (file) {
        assignFile(file);
    }
}

function assignFile(file) {
    if (file.type && file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        toast.add({
            severity: 'warn',
            summary: 'Archivo no válido',
            detail: 'Selecciona un PDF.',
            life: 3500,
        });
        return;
    }

    form.pdf = file;
    selectedFileName.value = file.name;
    form.clearErrors('pdf');
}

function clearFile() {
    form.pdf = null;
    selectedFileName.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function submit() {
    if (!form.pdf) {
        toast.add({
            severity: 'warn',
            summary: 'Falta el PDF',
            detail: 'Arrastra o selecciona un archivo para extraer el texto.',
            life: 3500,
        });
        return;
    }

    form.post(route('ocr.extract'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Listo',
                detail: 'Se extrajo el texto del PDF.',
                life: 3500,
            });
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'No se pudo extraer',
                detail: errors.pdf || 'Revisa el archivo e inténtalo de nuevo.',
                life: 5000,
            });
        },
    });
}

async function copyText() {
    const text = displayedText.value;
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        toast.add({
            severity: 'success',
            summary: 'Copiado',
            detail: 'El texto se copió al portapapeles.',
            life: 2500,
        });
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        toast.add({
            severity: 'error',
            summary: 'No se pudo copiar',
            detail: 'Copia el texto manualmente.',
            life: 3500,
        });
    }
}

function downloadText() {
    const text = displayedText.value;
    if (!text) return;
    const base = (props.result?.filename || selectedFileName.value || 'documento')
        .replace(/\.pdf$/i, '');
    const suffix = selectedPage.value === 'all' ? '' : `-pagina-${selectedPage.value}`;
    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${base}${suffix}.txt`;
    link.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="OCR" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-3xl font-semibold tracking-tight">
                OCR — Extraer texto de PDFs
            </h2>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="!toolsReady"
                    class="mb-6 rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-base-content"
                >
                    Faltan herramientas en el servidor (Tesseract, pdftotext o pdftoppm).
                    Sin ellas no se puede extraer texto de PDFs escaneados.
                </div>

                <div class="grid gap-6 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <div class="overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
                            <div class="border-b border-base-300 px-6 py-4">
                                <h3 class="text-lg font-medium text-base-content">Documento</h3>
                                <p class="mt-1 text-sm text-base-content/60">
                                    Sube un PDF digital o escaneado. Se procesan todas las páginas.
                                </p>
                            </div>

                            <form class="space-y-5 p-6" @submit.prevent="submit">
                                <div>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept="application/pdf,.pdf"
                                        class="hidden"
                                        @change="onFileChange"
                                    />
                                    <button
                                        type="button"
                                        class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-10 text-center transition"
                                        :class="
                                            dragging
                                                ? 'border-primary bg-primary/10'
                                                : 'border-base-300 bg-base-200/40 hover:border-primary/60'
                                        "
                                        @click="fileInput?.click()"
                                        @dragover="onDragOver"
                                        @dragleave="onDragLeave"
                                        @drop="onDrop"
                                    >
                                        <i class="pi pi-file-pdf mb-3 text-3xl text-primary"></i>
                                        <span class="text-sm font-medium text-base-content">
                                            {{ selectedFileName || 'Arrastra un PDF o haz clic para elegir' }}
                                        </span>
                                        <span class="mt-1 text-xs text-base-content/60">
                                            Se detecta texto embebido; si no hay, se usa OCR.
                                        </span>
                                    </button>
                                    <div v-if="selectedFileName" class="mt-2 flex justify-end">
                                        <Button
                                            type="button"
                                            label="Quitar archivo"
                                            icon="pi pi-times"
                                            text
                                            size="small"
                                            @click="clearFile"
                                        />
                                    </div>
                                    <InputError :message="form.errors.pdf" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Idioma" />
                                    <Select
                                        v-model="form.language"
                                        :options="languages"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="mt-1 w-full"
                                    />
                                </div>

                                <div>
                                    <InputLabel value="Modo" />
                                    <Select
                                        v-model="form.mode"
                                        :options="modeOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="mt-1 w-full"
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    label="Extraer texto"
                                    icon="pi pi-search"
                                    class="w-full"
                                    :loading="form.processing"
                                    :disabled="form.processing || !toolsReady"
                                />
                            </form>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <div class="overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
                            <div
                                class="flex flex-col gap-3 border-b border-base-300 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h3 class="text-lg font-medium text-base-content">Texto extraído</h3>
                                    <p v-if="result" class="mt-1 text-sm text-base-content/60">
                                        {{ result.filename }} · {{ result.page_count }} páginas · {{ methodLabel }}
                                    </p>
                                    <p v-else class="mt-1 text-sm text-base-content/60">
                                        El resultado aparecerá aquí después de extraer el PDF.
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        :label="copied ? 'Copiado' : 'Copiar'"
                                        :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
                                        severity="secondary"
                                        :disabled="!displayedText"
                                        @click="copyText"
                                    />
                                    <Button
                                        type="button"
                                        label="Descargar .txt"
                                        icon="pi pi-download"
                                        severity="secondary"
                                        :disabled="!displayedText"
                                        @click="downloadText"
                                    />
                                </div>
                            </div>

                            <div class="space-y-4 p-6">
                                <div v-if="pageOptions.length > 2">
                                    <InputLabel value="Ver página" />
                                    <Select
                                        v-model="selectedPage"
                                        :options="pageOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="mt-1 w-full sm:max-w-xs"
                                    />
                                </div>

                                <Textarea
                                    :modelValue="displayedText"
                                    class="w-full font-mono text-sm"
                                    :rows="18"
                                    placeholder="Aún no hay texto extraído."
                                    readonly
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
