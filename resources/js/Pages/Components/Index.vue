<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import CheckboxToggle from '@/Components/CheckboxToggle.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import PrimeCheckbox from 'primevue/checkbox';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import ThemeSelector from '@/Components/ThemeSelector.vue';
const props = defineProps({
    theme: {
        type: Object,
        default: () => ({}),
    },
    activeTheme: {
        type: String,
        default: 'dark',
    },
    branding: {
        type: Object,
        default: () => ({
            logo_url: null,
            auth_side_image_url: null,
            auth_side_image_pos_x: 50,
            auth_side_image_pos_y: 50,
        }),
    },
});

const localBranding = ref({
    logo_url: props.branding?.logo_url ?? '',
    auth_side_image_url: props.branding?.auth_side_image_url ?? '',
    auth_side_image_pos_x: Number(props.branding?.auth_side_image_pos_x ?? 50),
    auth_side_image_pos_y: Number(props.branding?.auth_side_image_pos_y ?? 50),
});

watch(
    () => [
        props.branding?.logo_url ?? '',
        props.branding?.auth_side_image_url ?? '',
        Number(props.branding?.auth_side_image_pos_x ?? 50),
        Number(props.branding?.auth_side_image_pos_y ?? 50),
    ],
    ([logo, side, posX, posY]) => {
        localBranding.value.logo_url = logo;
        localBranding.value.auth_side_image_url = side;
        localBranding.value.auth_side_image_pos_x = posX;
        localBranding.value.auth_side_image_pos_y = posY;
    },
);

const brandingUploadProcessing = ref(false);
const logoPickPreviewUrl = ref(null);
const sidePickPreviewUrl = ref(null);

const logoPreviewSrc = computed(
    () => logoPickPreviewUrl.value || localBranding.value.logo_url || '',
);
const sidePreviewSrc = computed(
    () => sidePickPreviewUrl.value || localBranding.value.auth_side_image_url || '',
);

const tabs = [
    { id: 'branding', label: 'Branding' },
    { id: 'tema', label: 'Tema' },
    { id: 'botones', label: 'Botones' },
    { id: 'formularios', label: 'Formularios' },
    { id: 'primevue', label: 'PrimeVue' },
    { id: 'navegacion', label: 'Navegación' },
    { id: 'otros', label: 'Otros' },
];
const activeTab = ref('branding');

function resolveBrandingSrc(value) {
    if (!value) return '';
    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:')) return value;
    if (value.startsWith('/')) return value;
    return `/storage/${value}`;
}

function clearPreview(which) {
    if (which === 'logo' && logoPickPreviewUrl.value) {
        URL.revokeObjectURL(logoPickPreviewUrl.value);
        logoPickPreviewUrl.value = null;
    }
    if (which === 'side' && sidePickPreviewUrl.value) {
        URL.revokeObjectURL(sidePickPreviewUrl.value);
        sidePickPreviewUrl.value = null;
    }
}

onBeforeUnmount(() => {
    clearPreview('logo');
    clearPreview('side');
});

function saveBranding() {
    router.patch(route('components.branding.update'), {
        logo_url: localBranding.value.logo_url ?? '',
        auth_side_image_url: localBranding.value.auth_side_image_url ?? '',
        auth_side_image_pos_x: Number(localBranding.value.auth_side_image_pos_x ?? 50),
        auth_side_image_pos_y: Number(localBranding.value.auth_side_image_pos_y ?? 50),
    }, {
        preserveScroll: true,
    });
}

function uploadBrandingFile(asset, event) {
    const input = event.target;
    const file = input.files?.[0];
    if (!file) return;

    if (asset === 'logo') {
        clearPreview('logo');
        logoPickPreviewUrl.value = URL.createObjectURL(file);
    } else {
        clearPreview('side');
        sidePickPreviewUrl.value = URL.createObjectURL(file);
    }

    brandingUploadProcessing.value = true;
    router.post(route('components.branding.upload'), {
        asset,
        upload: file,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            brandingUploadProcessing.value = false;
            input.value = '';
        },
        onSuccess: () => {
            if (asset === 'logo') clearPreview('logo');
            else clearPreview('side');
        },
    });
}

function cssBackgroundImageStyle(url) {
    const u = String(url ?? '').replace(/'/g, "\\'");
    const x = Number(localBranding.value.auth_side_image_pos_x ?? 50);
    const y = Number(localBranding.value.auth_side_image_pos_y ?? 50);
    const clampedX = Number.isFinite(x) ? Math.min(100, Math.max(0, x)) : 50;
    const clampedY = Number.isFinite(y) ? Math.min(100, Math.max(0, y)) : 50;
    return {
        backgroundImage: `url('${u}')`,
        backgroundPosition: `${clampedX}% ${clampedY}%`,
    };
}

const isDraggingSideImage = ref(false);
function dragSideImage(event) {
    const el = event.currentTarget;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    localBranding.value.auth_side_image_pos_x = Math.min(100, Math.max(0, Number(x.toFixed(2))));
    localBranding.value.auth_side_image_pos_y = Math.min(100, Math.max(0, Number(y.toFixed(2))));
}

function startDragSideImage(event) {
    isDraggingSideImage.value = true;
    dragSideImage(event);
}

function stopDragSideImage() {
    isDraggingSideImage.value = false;
}

const THEME_KEYS = [
    { key: '--btn-primary-bg', label: 'Botón primario - fondo', default: '#1f2937' },
    { key: '--btn-primary-hover', label: 'Botón primario - hover', default: '#374151' },
    { key: '--btn-primary-text', label: 'Botón primario - texto', default: '#ffffff' },
    { key: '--btn-primary-ring', label: 'Botón primario - ring focus', default: '#6366f1' },
    { key: '--btn-secondary-bg', label: 'Botón secundario - fondo', default: '#ffffff' },
    { key: '--btn-secondary-border', label: 'Botón secundario - borde', default: '#d1d5db' },
    { key: '--btn-secondary-text', label: 'Botón secundario - texto', default: '#374151' },
    { key: '--btn-danger-bg', label: 'Botón peligro - fondo', default: '#dc2626' },
    { key: '--btn-danger-hover', label: 'Botón peligro - hover', default: '#b91c1c' },

    { key: '--checkbox-checked-bg', label: 'Checkbox - fondo marcado', default: '#6366f1' },
    { key: '--checkbox-border', label: 'Checkbox - borde', default: '#d1d5db' },
    { key: '--toggle-checked-bg', label: 'Toggle - fondo activo', default: '#6366f1' },
    { key: '--toggle-bg', label: 'Toggle - fondo inactivo', default: '#d1d5db' },

    { key: '--badge-primary', label: 'Badge primario - fondo', default: '#6366f1' },
];

const SECTION_GROUPS = [
    {
        id: 'botones',
        title: 'Botones',
        keys: THEME_KEYS.filter((k) => k.key.startsWith('--btn-')),
    },
    {
        id: 'formularios',
        title: 'Formularios',
        keys: THEME_KEYS.filter((k) =>
            k.key.startsWith('--input-') || k.key.startsWith('--checkbox-') || k.key.startsWith('--toggle-')
        ),
    },
    {
        id: 'otras',
        title: 'Otros',
        keys: THEME_KEYS.filter((k) =>
            k.key.startsWith('--badge-')
        ),
    },
];

const localTheme = ref({ ...props.theme });
const isSyncingFromProps = ref(false);
const showModal = ref(false);
const saveTimeout = ref(null);
const demoInput = ref('');
const demoCheckbox = ref(true);
const demoPrimeInput = ref('');
const demoPrimeCheckbox = ref(false);
const demoPrimeSelect = ref(null);
const primeDialogVisible = ref(false);
const selectOptions = [
    { label: 'Opción A', value: 'a' },
    { label: 'Opción B', value: 'b' },
    { label: 'Opción C', value: 'c' },
];

// Inicializar valores faltantes con defaults
THEME_KEYS.forEach(({ key, default: d }) => {
    if (localTheme.value[key] === undefined || localTheme.value[key] === '') {
        localTheme.value[key] = d;
    }
});

function saveTheme() {
    router.patch(route('components.theme.update'), {
        styles: localTheme.value,
    }, {
        preserveScroll: true,
    });
}

watch(localTheme, () => {
    applyToDocument();
    if (isSyncingFromProps.value) return;
    if (saveTimeout.value) clearTimeout(saveTimeout.value);
    saveTimeout.value = setTimeout(saveTheme, 500);
}, { deep: true });

function applyToDocument() {
    const root = document.documentElement;
    Object.entries(localTheme.value).forEach(([key, val]) => {
        if (key && val) root.style.setProperty(key, val);
    });
}

// Aplicar tema al cargar (para preview en tiempo real) - sin disparar saveTheme
// Usamos setTimeout(0) en vez de nextTick para asegurar que el watcher de localTheme
// corra antes de resetear el flag (evita bucle theme/components)
watch(() => props.theme, (t) => {
    isSyncingFromProps.value = true;
    localTheme.value = { ...localTheme.value, ...t };
    applyToDocument();
    setTimeout(() => { isSyncingFromProps.value = false; }, 0);
}, { immediate: true });
</script>

<template>
    <Head title="Componentes del Sistema" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-base-content">
                    Componentes del Sistema
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg bg-base-100 p-3 shadow sm:p-4">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm transition"
                            :class="activeTab === tab.id
                                ? 'bg-primary text-primary-content'
                                : 'bg-base-200 text-base-content hover:bg-base-300'"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </section>

                <section v-show="activeTab === 'branding'" class="rounded-lg bg-base-100 p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold text-base-content">Logo e imágenes de acceso</h3>
                    <div class="grid gap-8 lg:grid-cols-2">
                        <div>
                            <h4 class="text-sm font-medium text-base-content">Logo</h4>
                            <input
                                v-model="localBranding.logo_url"
                                type="text"
                                class="mt-2 w-full rounded border border-base-300 bg-base-100 px-3 py-2 text-sm shadow-xs focus:border-primary focus:ring-primary"
                                placeholder="https://... o /storage/..."
                            />
                            <input
                                type="file"
                                accept="image/*"
                                class="mt-3 block w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-base-200 file:px-2 file:py-1"
                                :disabled="brandingUploadProcessing"
                                @change="uploadBrandingFile('logo', $event)"
                            />
                            <img
                                v-if="logoPreviewSrc"
                                :src="resolveBrandingSrc(logoPreviewSrc)"
                                alt="Preview logo"
                                class="mt-3 max-h-24 w-auto object-contain"
                            />
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-base-content">Imagen lateral (login, password required, cuenta desactivada)</h4>
                            <input
                                v-model="localBranding.auth_side_image_url"
                                type="text"
                                class="mt-2 w-full rounded border border-base-300 bg-base-100 px-3 py-2 text-sm shadow-xs focus:border-primary focus:ring-primary"
                                placeholder="https://... o /storage/..."
                            />
                            <input
                                type="file"
                                accept="image/*"
                                class="mt-3 block w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-base-200 file:px-2 file:py-1"
                                :disabled="brandingUploadProcessing"
                                @change="uploadBrandingFile('auth_side', $event)"
                            />
                            <div
                                v-if="sidePreviewSrc"
                                class="mt-3 min-h-[220px] rounded bg-base-200 bg-cover bg-no-repeat"
                                :class="isDraggingSideImage ? 'cursor-grabbing' : 'cursor-grab'"
                                :style="cssBackgroundImageStyle(resolveBrandingSrc(sidePreviewSrc))"
                                @mousedown.prevent="startDragSideImage"
                                @mousemove.prevent="isDraggingSideImage && dragSideImage($event)"
                                @mouseup="stopDragSideImage"
                                @mouseleave="stopDragSideImage"
                            />


                            <p class="mt-1 text-xs text-base-content/60">
                                Arrastra la imagen para mover el encuadre. Guarda para aplicar en login.
                            </p>
                        </div>
                    </div>
                    <div class="mt-5">
                        <PrimaryButton type="button" @click="saveBranding">Guardar branding</PrimaryButton>
                    </div>
                </section>

                <!-- Selector de tema y Custom -->
                <section v-show="activeTab === 'tema'" class="rounded-lg bg-base-100 p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold text-base-content">Tema de la aplicación</h3>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-base-content/70">Cambiar tema:</span>
                            <ThemeSelector />
                        </div>
                        <span class="text-xs text-base-content/60">
                            (También disponible en la barra de navegación)
                        </span>
                    </div>
                    <div class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-4">
                        <h4 class="mb-2 text-sm font-medium text-warning-content">Crear tema personalizado</h4>
                        <ol class="list-inside list-decimal space-y-1 text-sm text-warning-content/90">
                            <li>Selecciona <strong>Personalizado</strong> en el selector de temas</li>
                            <li>Edita los colores en las secciones de abajo (Botones, Formularios, Otros)</li>
                            <li>Los cambios se guardan automáticamente y se aplican en toda la app</li>
                        </ol>
                    </div>
                </section>

                <!-- Botones -->
                <section v-show="activeTab === 'botones'" class="rounded-lg bg-base-100 p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold text-base-content">Botones</h3>
                    <div class="flex flex-wrap gap-4">
                        <div class="flex flex-col items-center gap-2">
                            <PrimaryButton>Primario</PrimaryButton>
                            <span class="text-xs text-base-content/60">PrimaryButton</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <SecondaryButton type="button">Secundario</SecondaryButton>
                            <span class="text-xs text-base-content/60">SecondaryButton</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <DangerButton>Peligro</DangerButton>
                            <span class="text-xs text-base-content/60">DangerButton</span>
                        </div>
                    </div>
                    <form class="mt-6 border-t border-base-300 pt-6">
                        <h4 class="mb-3 text-sm font-medium text-base-content">Colores de botones</h4>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="item in (SECTION_GROUPS.find(s => s.id === 'botones')?.keys ?? [])" :key="item.key" class="flex items-center gap-3">
                                <label class="min-w-0 flex-1 truncate text-xs text-base-content/70">{{ item.label }}</label>
                                <input
                                    :value="localTheme[item.key]"
                                    type="text"
                                    class="w-24 rounded border border-base-300 bg-base-100 px-2 py-1.5 text-xs shadow-xs focus:border-primary focus:ring-primary"
                                    :placeholder="item.default"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                                <input
                                    :value="localTheme[item.key] || item.default"
                                    type="color"
                                    class="h-8 w-10 shrink-0 cursor-pointer rounded border border-base-300 bg-base-100 p-0.5"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Formularios -->
                <section v-show="activeTab === 'formularios'" class="rounded-lg bg-base-100 p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold text-base-content">Formularios</h3>
                    <div class="max-w-md space-y-4">
                        <div>
                            <InputLabel value="InputLabel + TextInput" />
                            <TextInput v-model="demoInput" class="mt-1 block w-full" />
                            <InputError message="Mensaje de error de ejemplo" />
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox v-model:checked="demoCheckbox" />
                            <span class="text-sm">Checkbox</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <CheckboxToggle :checked="true" @change="console.log('change')" />
                            <span class="text-sm">CheckboxToggle</span>
                        </div>
                    </div>
                    <form class="mt-6 border-t border-base-300 pt-6">
                        <h4 class="mb-3 text-sm font-medium text-base-content">Colores de inputs</h4>
                        <div class="flex flex-wrap gap-3">
                            <div v-for="item in (SECTION_GROUPS.find(s => s.id === 'formularios')?.keys ?? [])" :key="item.key" class="flex items-center gap-3">
                                <label class="min-w-[140px] text-xs text-base-content/70">{{ item.label }}</label>
                                <input
                                    :value="localTheme[item.key]"
                                    type="text"
                                    class="w-24 rounded border border-base-300 bg-base-100 px-2 py-1.5 text-xs shadow-xs focus:border-primary focus:ring-primary"
                                    :placeholder="item.default"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                                <input
                                    :value="localTheme[item.key] || item.default"
                                    type="color"
                                    class="h-8 w-10 shrink-0 cursor-pointer rounded border border-base-300 bg-base-100 p-0.5"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                            </div>
                        </div>
                    </form>
                </section>

                <!-- PrimeVue -->
                <section v-show="activeTab === 'primevue'" class="rounded-lg bg-base-100 p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold text-base-content">PrimeVue</h3>
                    <p class="mb-4 text-xs text-base-content/60">
                        Componentes de la librería PrimeVue (tema Aura). Se estilizan con el preset configurado en app.js.
                    </p>

                    <h4 class="mb-3 text-sm font-medium text-base-content">Button</h4>
                    <div class="mb-6 flex flex-wrap gap-3">
                        <div class="flex flex-col items-center gap-2">
                            <Button label="Primary" />
                            <span class="text-xs text-base-content/60">severity="primary"</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Button label="Secondary" severity="secondary" />
                            <span class="text-xs text-base-content/60">secondary</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Button label="Success" severity="success" />
                            <span class="text-xs text-base-content/60">success</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Button label="Danger" severity="danger" />
                            <span class="text-xs text-base-content/60">danger</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Button label="Outlined" outlined />
                            <span class="text-xs text-base-content/60">outlined</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Button label="Text" text />
                            <span class="text-xs text-base-content/60">text</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Button icon="pi pi-check" rounded />
                            <span class="text-xs text-base-content/60">icon rounded</span>
                        </div>
                    </div>

                    <h4 class="mb-3 text-sm font-medium text-base-content">InputText</h4>
                    <div class="mb-6 max-w-md space-y-3">
                        <div class="flex flex-col gap-2">
                            <InputText v-model="demoPrimeInput" placeholder="Placeholder" class="w-full" />
                            <span class="text-xs text-base-content/60">InputText básico</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <InputText v-model="demoPrimeInput" placeholder="Small" size="small" class="w-full" />
                            <span class="text-xs text-base-content/60">size="small"</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <InputText v-model="demoPrimeInput" placeholder="Disabled" disabled class="w-full" />
                            <span class="text-xs text-base-content/60">disabled</span>
                        </div>
                    </div>

                    <h4 class="mb-3 text-sm font-medium text-base-content">Select</h4>
                    <div class="mb-6 max-w-md">
                        <Select
                            v-model="demoPrimeSelect"
                            :options="selectOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Seleccionar..."
                            class="w-full"
                        />
                        <span class="mt-2 block text-xs text-base-content/60">Select (dropdown)</span>
                    </div>

                    <h4 class="mb-3 text-sm font-medium text-base-content">Checkbox</h4>
                    <div class="mb-6 flex flex-wrap gap-6">
                        <div class="flex items-center gap-2">
                            <PrimeCheckbox v-model="demoPrimeCheckbox" :binary="true" input-id="pv-cb1" />
                            <label for="pv-cb1" class="text-sm">Checkbox PrimeVue</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <PrimeCheckbox :model-value="true" :binary="true" disabled input-id="pv-cb2" />
                            <label for="pv-cb2" class="text-sm">Disabled checked</label>
                        </div>
                    </div>

                    <h4 class="mb-3 text-sm font-medium text-base-content">Tag</h4>
                    <div class="mb-6 flex flex-wrap gap-3">
                        <div class="flex flex-col items-center gap-2">
                            <Tag value="Primary" severity="primary" />
                            <span class="text-xs text-base-content/60">primary</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Tag value="Success" severity="success" />
                            <span class="text-xs text-base-content/60">success</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Tag value="Info" severity="info" />
                            <span class="text-xs text-base-content/60">info</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Tag value="Warning" severity="warn" />
                            <span class="text-xs text-base-content/60">warn</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <Tag value="Danger" severity="danger" />
                            <span class="text-xs text-base-content/60">danger</span>
                        </div>
                    </div>

                    <h4 class="mb-3 text-sm font-medium text-base-content">Dialog</h4>
                    <div class="mb-4">
                        <Button label="Abrir Dialog" @click="primeDialogVisible = true" />
                        <span class="ml-2 text-xs text-base-content/60">Modal de PrimeVue</span>
                    </div>
                    <Dialog
                        v-model:visible="primeDialogVisible"
                        modal
                        header="Título del Dialog"
                        :style="{ width: '28rem' }"
                    >
                        <p class="text-sm text-base-content/70">
                            Contenido del modal PrimeVue. Se cierra con el botón X o haciendo clic fuera.
                        </p>
                        <template #footer>
                            <Button label="Cerrar" severity="secondary" @click="primeDialogVisible = false" />
                        </template>
                    </Dialog>
                </section>

                <!-- Navegación -->
                <section v-show="activeTab === 'navegacion'" class="rounded-lg bg-base-100 p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold text-base-content">Navegación</h3>
                    <p class="mb-4 text-xs text-base-content/60">Sin variables de color personalizables (usa estilos de Tailwind/DaisyUI).</p>
                    <div class="flex flex-wrap gap-4">
                        <NavLink :href="'#'" :active="false">NavLink</NavLink>
                        <Dropdown align="left" width="48">
                            <template #trigger>
                                <button class="inline-flex items-center rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm font-medium text-base-content shadow-xs hover:bg-base-200">
                                    Dropdown
                                    <svg class="ms-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('dashboard')">Opción 1</DropdownLink>
                                <DropdownLink :href="route('profile.edit')">Opción 2</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </section>

                <!-- Logo y otros -->
                <section v-show="activeTab === 'otros'" class="rounded-lg bg-base-100 p-6 shadow">
                    <h3 class="mb-4 text-lg font-semibold text-base-content">Otros</h3>
                    <div class="flex flex-wrap items-center gap-8">
                        <div class="flex flex-col items-center gap-2">
                            <ApplicationLogo class="h-12 w-auto fill-current text-base-content" />
                            <span class="text-xs text-base-content/60">ApplicationLogo</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <span class="badge badge-primary badge-outline gap-2 p-4">Badge primario</span>
                            <span class="text-xs text-base-content/60">badge-primary</span>
                        </div>
                    </div>
                    <form class="mt-6 border-t border-base-300 pt-6">
                        <h4 class="mb-3 text-sm font-medium text-base-content">Colores de badges</h4>
                        <div class="flex flex-wrap gap-3">
                            <div v-for="item in (SECTION_GROUPS.find(s => s.id === 'otras')?.keys ?? [])" :key="item.key" class="flex items-center gap-3">
                                <label class="min-w-[180px] text-xs text-base-content/70">{{ item.label }}</label>
                                <input
                                    :value="localTheme[item.key]"
                                    type="text"
                                    class="w-24 rounded border border-base-300 bg-base-100 px-2 py-1.5 text-xs shadow-xs focus:border-primary focus:ring-primary"
                                    :placeholder="item.default"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                                <input
                                    :value="localTheme[item.key] || item.default"
                                    type="color"
                                    class="h-8 w-10 shrink-0 cursor-pointer rounded border border-base-300 bg-base-100 p-0.5"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                            </div>
                        </div>
                    </form>
                </section>
            </div>
        </div>


    </AuthenticatedLayout>
</template>
