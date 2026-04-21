<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';

const page = usePage();
const serverTheme = computed(() => page.props.activeTheme ?? 'dark');
// Estado local para feedback inmediato al cambiar tema
const displayedTheme = ref(serverTheme.value);

// Temas con paleta oscura (para Tailwind dark: y PrimeVue)
const DARK_THEMES = ['dark', 'forest', 'business', 'cyberpunk', 'custom'];

function applyTheme(theme) {
    const t = theme ?? displayedTheme.value;
    const dataTheme = t === 'custom' ? 'dark' : t;
    document.documentElement.setAttribute('data-theme', dataTheme);
    // Activar Tailwind dark: y PrimeVue según si el tema es oscuro
    document.documentElement.classList.toggle('dark', DARK_THEMES.includes(t));
}

// Sincronizar con props del servidor
watch(serverTheme, (v) => {
    displayedTheme.value = v;
    applyTheme(v);
}, { immediate: true });

onMounted(() => applyTheme(displayedTheme.value));

const THEMES = [
    { id: 'light', label: 'Claro', icon: '☀️' },
    { id: 'dark', label: 'Oscuro', icon: '🌙' },
    { id: 'cupcake', label: 'Cupcake', icon: '🧁' },
    { id: 'forest', label: 'Forest', icon: '🌲' },
    { id: 'business', label: 'Business', icon: '💼' },
    { id: 'retro', label: 'Retro', icon: '📻' },
    { id: 'cyberpunk', label: 'Cyberpunk', icon: '🤖' },
    { id: 'valentine', label: 'Valentine', icon: '💝' },
    { id: 'custom', label: 'Personalizado', icon: '🎨' },
];

function setTheme(themeId) {
    displayedTheme.value = themeId;
    applyTheme(themeId);

    router.post(route('theme.update'), { active_theme: themeId }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Dropdown align="right" width="48">
        <template #trigger>
            <button
                type="button"
                class="inline-flex items-center rounded-md p-2 text-base-content/70 transition hover:bg-base-200 hover:text-base-content focus:outline-hidden"
                title="Cambiar tema"
            >
                <span class="text-lg">{{ displayedTheme === 'light' ? '☀️' : displayedTheme === 'custom' ? '🎨' : '🌙' }}</span>
            </button>
        </template>
        <template #content>
            <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-base-content/60">
                Tema
            </div>
            <button
                v-for="t in THEMES"
                :key="t.id"
                type="button"
                class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-base-content transition hover:bg-base-200"
                :class="{ 'bg-base-200': displayedTheme === t.id }"
                @click="setTheme(t.id)"
            >
                <span>{{ t.icon }}</span>
                <span>{{ t.label }}</span>
                <span v-if="displayedTheme === t.id" class="ms-auto text-indigo-500">✓</span>
            </button>
        </template>
    </Dropdown>
</template>
