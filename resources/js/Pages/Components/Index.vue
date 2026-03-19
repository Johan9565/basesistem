<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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

const props = defineProps({
    theme: {
        type: Object,
        default: () => ({}),
    },
});

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
const showModal = ref(false);
const saveTimeout = ref(null);
const demoInput = ref('');
const demoCheckbox = ref(true);

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
    if (saveTimeout.value) clearTimeout(saveTimeout.value);
    saveTimeout.value = setTimeout(saveTheme, 500);
}, { deep: true });

function applyToDocument() {
    const root = document.documentElement;
    Object.entries(localTheme.value).forEach(([key, val]) => {
        if (key && val) root.style.setProperty(key, val);
    });
}

// Aplicar tema al cargar (para preview en tiempo real)
watch(() => props.theme, (t) => {
    localTheme.value = { ...localTheme.value, ...t };
    applyToDocument();
}, { immediate: true });
</script>

<template>
    <Head title="Componentes del Sistema" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Componentes del Sistema
                </h2>

            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-12 px-4 sm:px-6 lg:px-8">
                <!-- Botones -->
                <section class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Botones</h3>
                    <div class="flex flex-wrap gap-4">
                        <div class="flex flex-col items-center gap-2">
                            <PrimaryButton>Primario</PrimaryButton>
                            <span class="text-xs text-gray-500">PrimaryButton</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <SecondaryButton type="button">Secundario</SecondaryButton>
                            <span class="text-xs text-gray-500">SecondaryButton</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <DangerButton>Peligro</DangerButton>
                            <span class="text-xs text-gray-500">DangerButton</span>
                        </div>
                    </div>
                    <form class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-600">
                        <h4 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Colores de botones</h4>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="item in (SECTION_GROUPS.find(s => s.id === 'botones')?.keys ?? [])" :key="item.key" class="flex items-center gap-3">
                                <label class="min-w-0 flex-1 truncate text-xs text-gray-600 dark:text-gray-400">{{ item.label }}</label>
                                <input
                                    :value="localTheme[item.key]"
                                    type="text"
                                    class="w-24 rounded border border-gray-300 bg-white px-2 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    :placeholder="item.default"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                                <input
                                    :value="localTheme[item.key] || item.default"
                                    type="color"
                                    class="h-8 w-10 shrink-0 cursor-pointer rounded border border-gray-300 bg-white p-0.5 dark:border-gray-600"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Formularios -->
                <section class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Formularios</h3>
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
                    <form class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-600">
                        <h4 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Colores de inputs</h4>
                        <div class="flex flex-wrap gap-3">
                            <div v-for="item in (SECTION_GROUPS.find(s => s.id === 'formularios')?.keys ?? [])" :key="item.key" class="flex items-center gap-3">
                                <label class="min-w-[140px] text-xs text-gray-600 dark:text-gray-400">{{ item.label }}</label>
                                <input
                                    :value="localTheme[item.key]"
                                    type="text"
                                    class="w-24 rounded border border-gray-300 bg-white px-2 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    :placeholder="item.default"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                                <input
                                    :value="localTheme[item.key] || item.default"
                                    type="color"
                                    class="h-8 w-10 shrink-0 cursor-pointer rounded border border-gray-300 bg-white p-0.5 dark:border-gray-600"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Navegación -->
                <section class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Navegación</h3>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Sin variables de color personalizables (usa estilos de Tailwind/DaisyUI).</p>
                    <div class="flex flex-wrap gap-4">
                        <NavLink :href="'#'" :active="false">NavLink</NavLink>
                        <Dropdown align="left" width="48">
                            <template #trigger>
                                <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
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
                <section class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Otros</h3>
                    <div class="flex flex-wrap items-center gap-8">
                        <div class="flex flex-col items-center gap-2">
                            <ApplicationLogo class="h-12 w-auto fill-current text-gray-800 dark:text-gray-200" />
                            <span class="text-xs text-gray-500">ApplicationLogo</span>
                        </div>
                        <div class="flex flex-col items-center gap-2">
                            <span class="badge badge-primary badge-outline gap-2 p-4">Badge primario</span>
                            <span class="text-xs text-gray-500">badge-primary</span>
                        </div>
                    </div>
                    <form class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-600">
                        <h4 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Colores de badges</h4>
                        <div class="flex flex-wrap gap-3">
                            <div v-for="item in (SECTION_GROUPS.find(s => s.id === 'otras')?.keys ?? [])" :key="item.key" class="flex items-center gap-3">
                                <label class="min-w-[180px] text-xs text-gray-600 dark:text-gray-400">{{ item.label }}</label>
                                <input
                                    :value="localTheme[item.key]"
                                    type="text"
                                    class="w-24 rounded border border-gray-300 bg-white px-2 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    :placeholder="item.default"
                                    @input="localTheme[item.key] = ($event.target).value"
                                />
                                <input
                                    :value="localTheme[item.key] || item.default"
                                    type="color"
                                    class="h-8 w-10 shrink-0 cursor-pointer rounded border border-gray-300 bg-white p-0.5 dark:border-gray-600"
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
