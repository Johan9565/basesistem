<script setup>
import { computed, useAttrs } from 'vue';
import Select from 'primevue/select';
import { wrappingSelectPanelOverlayClass as overlayPresets } from './wrappingSelectPresets.js';

const props = defineProps({
    /** Passthrough de PrimeVue; se fusiona encima de los estilos de texto multilínea. */
    pt: {
        type: Object,
        default: () => ({}),
    },
    /**
     * Ancho máximo del panel desplegable.
     * - `filter` / `dialog`: presets (barra de filtros vs diálogo ancho fijo).
     * - Cualquier otro string no vacío: clase Tailwind/CSS completa para el overlay.
     * - `null` o `''`: no limita ancho (solo salto de línea en opciones).
     */
    panelPreset: {
        type: String,
        default: null,
        validator: (v) => v == null || v === '' || typeof v === 'string',
    },
});

const model = defineModel();

const attrs = useAttrs();

const attrsWithoutPt = computed(() => {
    const { pt: _ignored, ...rest } = attrs;
    return rest;
});

const baseWrapPt = {
    header: { class: 'min-w-0' },
    listContainer: { class: 'min-w-0 max-w-full' },
    list: { class: 'min-w-0 max-w-full' },
    option: { class: '!items-start !whitespace-normal gap-2 py-2' },
    optionLabel: {
        class: 'min-w-0 flex-1 !whitespace-normal break-words text-left leading-snug',
    },
    filterInput: { class: 'min-w-0 w-full' },
    label: { class: 'line-clamp-2 whitespace-normal break-words text-left' },
};

function mergeSection(base, override) {
    if (!override) {
        return base ? { ...base } : undefined;
    }
    if (!base) {
        return { ...override };
    }
    const out = { ...base, ...override };
    if (base.class != null || override.class != null) {
        out.class = [base.class, override.class].filter(Boolean).join(' ').trim();
    }
    if (base.style != null || override.style != null) {
        out.style = { ...(base.style || {}), ...(override.style || {}) };
    }
    return out;
}

function mergePt(base, extra) {
    const keys = new Set([...Object.keys(base || {}), ...Object.keys(extra || {})]);
    const out = {};
    for (const k of keys) {
        const merged = mergeSection(base?.[k], extra?.[k]);
        if (merged !== undefined) {
            out[k] = merged;
        }
    }
    return out;
}

const resolvedOverlayClass = computed(() => {
    const p = props.panelPreset;
    if (p === 'filter') {
        return overlayPresets.filter;
    }
    if (p === 'dialog') {
        return overlayPresets.dialog;
    }
    if (p && p !== '') {
        return p;
    }
    return 'min-w-0 overflow-hidden';
});

const wrappingPt = computed(() => ({
    ...baseWrapPt,
    overlay: {
        class: resolvedOverlayClass.value,
    },
}));

const mergedPt = computed(() => mergePt(wrappingPt.value, props.pt));
</script>

<template>
    <Select
        v-bind="attrsWithoutPt"
        v-model="model"
        :pt="mergedPt"
    >
        <template v-for="(_, name) in $slots" :key="name" #[name]="scope">
            <slot :name="name" v-bind="scope ?? {}" />
        </template>
    </Select>
</template>
