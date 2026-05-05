<script setup>
import { computed, onMounted, watch, toRaw } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { PageBuilder, getPageBuilder, sharedPageBuilderStore } from '@myissue/vue-website-page-builder';
import PageBuilderMediaLibrary from '@/Components/PageBuilderMediaLibrary.vue';
import BusinessCardTemplatesPicker from '@/ComponentsPageBuilder/BusinessCardTemplatesPicker.vue';

const props = defineProps({
    portfolio: { type: Object, required: true },
    slug: { type: String, default: '' },
    publicUrl: { type: String, default: null },
    businessCardUrl: { type: String, default: null },
});

function clonePortfolio(value) {
    const raw = toRaw(value);
    try {
        return structuredClone(raw);
    } catch (e) {
        return JSON.parse(JSON.stringify(raw ?? {}));
    }
}

const form = useForm({
    portfolio: clonePortfolio(props.portfolio),
});

const pageBuilderService = getPageBuilder();
const pbStore = sharedPageBuilderStore;

const builderComponents = computed(() => {
    const s = form.portfolio?.business_card_builder_state;
    if (!s) return [];
    if (Array.isArray(s)) return s;
    if (Array.isArray(s.components)) return s.components;
    return [];
});

function buildConfig() {
    const slug = (form.portfolio?.slug ?? '').trim() || 'business-card';
    return {
        updateOrCreate: {
            formType: 'update',
            formName: 'business_card',
        },
        resourceData: { title: slug },
        userSettings: {
            theme: 'auto',
            language: { default: 'es' },
            autoSave: true,
        },
        settings: {
            brandColor: form.portfolio?.config?.primary_color ?? '#ff5733',
        },
    };
}

async function startBuilderFromPortfolio() {
    const cfg = buildConfig();
    const comps = builderComponents.value;
    await pageBuilderService.startBuilder(cfg, comps);
}

async function saveAll() {
    await pageBuilderService.handleManualSave(true);

    const key = pbStore.getLocalStorageItemName;
    let comps = [];
    if (key) {
        try {
            const raw = window.localStorage?.getItem?.(key);
            const parsed = raw ? JSON.parse(raw) : null;
            if (parsed && Array.isArray(parsed.components)) {
                comps = parsed.components;
            }
        } catch (e) {
            // fallback below
        }
    }
    if (!Array.isArray(comps) || comps.length === 0) {
        const storeComps = pbStore.getComponents ?? [];
        comps = Array.isArray(storeComps) ? storeComps : [];
    }

    form.portfolio.business_card_builder_state = { components: comps };

    form.patch(route('businesscard.update'), { preserveScroll: true });
}

watch(
    () => props.portfolio,
    (p) => {
        if (p) {
            form.portfolio = clonePortfolio(p);
            startBuilderFromPortfolio();
        }
    },
);

onMounted(async () => {
    await startBuilderFromPortfolio();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tarjeta de negocio" />

        <div class="mx-auto px-4 py-6">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h1 class="text-2xl font-semibold">Tarjeta de negocio</h1>
                </div>
                <div class="flex gap-2">
                    <a v-if="publicUrl" class="btn btn-sm btn-ghost" :href="publicUrl" target="_blank" rel="noreferrer">Ver landing</a>
                    <a v-if="businessCardUrl" class="btn btn-sm btn-primary" :href="businessCardUrl" target="_blank" rel="noreferrer">Link tarjeta</a>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body gap-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm opacity-70">
                            Diseña aquí la apariencia de tu tarjeta de negocio.
                        </div>

                        <div class="flex items-end justify-end gap-2">
                            <BusinessCardTemplatesPicker />
                            <button type="button" class="btn btn-primary" :disabled="form.processing" @click="saveAll">
                                Guardar Tarjeta
                            </button>
                        </div>
                    </div>

                    <div class="rounded border border-base-300 overflow-hidden">
                        <PageBuilder :CustomMediaLibraryComponent="PageBuilderMediaLibrary" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
