<script setup>
import { ref, computed } from 'vue';
import { getPageBuilder } from '@myissue/vue-website-page-builder';
import { photographerTemplates } from '@/ComponentsPageBuilder/photographerTemplates';

const pageBuilderService = getPageBuilder();
const open = ref(false);

const templates = photographerTemplates;
const selectedTitle = ref(templates?.[0]?.title ?? null);

const selectedTemplate = computed(() => templates.find((t) => t.title === selectedTitle.value) ?? null);

async function insertTemplate(t) {
    if (!t) return;
    await pageBuilderService.addComponent({
        id: null,
        title: t.title,
        html_code: t.html_code,
    });
    open.value = false;
}
</script>

<template>
    <div>
        <button type="button" class="btn" @click="open = true">Plantillas</button>

        <dialog class="modal" :open="open">
            <div class="modal-box max-w-full min-h-full flex flex-col">
                <h3 class="font-semibold text-lg">Plantillas</h3>
                <p class="text-sm opacity-70 mt-1">Selecciona una plantilla para previsualizar e insertarla en tu landing.</p>

                <div class="mt-4 flex gap-6 flex-1 overflow-hidden">
                    <div class="w-80 space-y-2 border-r border-base-200 pr-4 overflow-y-auto">
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

                    <div class="flex-1 flex flex-col space-y-4 overflow-hidden">
                        <div class="flex-1 rounded border border-base-300 bg-base-100 p-3 overflow-auto">
                            <div v-if="selectedTemplate?.html_code" v-html="selectedTemplate.html_code" />
                            <div v-else class="text-sm opacity-70">Selecciona una plantilla para previsualizar.</div>
                        </div>

                        <div class="flex justify-end gap-2 shrink-0 pt-2">
                            <button type="button" class="btn" @click="open = false">Cerrar</button>
                            <button type="button" class="btn btn-primary" @click="insertTemplate(selectedTemplate)" :disabled="!selectedTemplate">
                                Insertar plantilla
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop" @submit.prevent="open = false">
                <button>close</button>
            </form>
        </dialog>
    </div>
</template>
