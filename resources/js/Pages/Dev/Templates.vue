<script setup>
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { photographerTemplates } from '@/ComponentsPageBuilder/photographerTemplates';

const templates = photographerTemplates;
const selectedTitle = ref(templates?.[0]?.title ?? null);
const draftHtml = ref('');
const previewRootEl = ref(null);

const selectedTemplate = computed(() => templates.find((t) => t.title === selectedTitle.value) ?? null);

watch(
    selectedTemplate,
    (t) => {
        draftHtml.value = t?.html_code ?? '';
    },
    { immediate: true }
);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Templates Playground" />

        <div class="max-w-7xl mx-auto px-4 py-6 space-y-4">
            <div>
                <h1 class="text-2xl font-semibold">Templates Playground</h1>
                <p class="text-sm opacity-70">
                    Vista de desarrollador para modificar HTML y ver preview.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <div class="space-y-2">
                        <div class="text-sm font-medium opacity-80">Selecciona una plantilla</div>
                        <div class="space-y-2">
                            <button
                                v-for="t in templates"
                                :key="t.title"
                                type="button"
                                class="btn w-full justify-start"
                                :class="selectedTitle === t.title ? 'btn-neutral' : 'btn-ghost'"
                                @click="selectedTitle = t.title"
                            >
                                {{ t.title }}
                            </button>
                        </div>
                    </div>


                </div>


            </div>
            <div class="space-y-2">
                    <div class="text-sm font-medium opacity-80">Preview</div>
                    <div ref="previewRootEl" class="rounded border border-base-300 bg-base-100 p-3 min-h-[520px] overflow-auto">
                        <div v-if="draftHtml" v-html="draftHtml" />
                        <div v-else class="text-sm opacity-70">Selecciona una plantilla para previsualizar.</div>
                    </div>
                </div>
        </div>
    </AuthenticatedLayout>
</template>

