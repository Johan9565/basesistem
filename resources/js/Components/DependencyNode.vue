<script setup>
import { computed } from 'vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

defineOptions({ name: 'DependencyNode' });

const props = defineProps({
    dependency: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['edit', 'delete']);

const id = computed(() => props.dependency?.id ?? '');
const hasChildren = computed(
    () => Array.isArray(props.dependency?.children) && props.dependency.children.length > 0,
);

const statusLabel = computed(() => {
    const status = props.dependency?.status;
    return status == 1 ? 'Activo' : 'Inactivo';
});

const statusSeverity = computed(() => {
    const status = props.dependency?.status;
    return status == 1 ? 'success' : 'danger';
});

function onEdit() {
    emit('edit', id.value);
}

function onDelete() {
    emit('delete', id.value);
}
</script>

<template>
    <li>
        <details v-if="hasChildren">
            <summary
                class="cursor-pointer list-none flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <svg
                        class="chevron h-4 w-4 shrink-0 text-base-content/60"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                            clip-rule="evenodd"
                        />
                    </svg>

                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-base-content">
                            {{ dependency.name }}
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <Tag
                                :value="statusLabel"
                                :severity="statusSeverity"
                                class="py-0!"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <Button
                        type="button"
                        icon="pi pi-pencil"
                        label="Editar"
                        size="small"
                        severity="info"
                        @click.stop="onEdit"
                    />
                    <!-- <Button
                        type="button"
                        icon="pi pi-trash"
                        label="Eliminar"
                        size="small"
                        severity="danger"
                        @click.stop="onDelete"
                    /> -->
                </div>
            </summary>
            <ul class="ml-4 mt-3 space-y-2 border-l border-base-300 pl-4">
                <DependencyNode
                    v-for="child in dependency.children"
                    :key="child.id"
                    :dependency="child"
                    @edit="emit('edit', $event)"
                    @delete="emit('delete', $event)"
                />
            </ul>
        </details>

        <div
            v-else
            class="flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2"
        >
            <div class="flex min-w-0 items-center gap-3">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-base-content/20"></span>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-base-content">
                        {{ dependency.name }}
                    </div>
                    <div class="mt-1 flex items-center gap-2">
                            <Tag
                                :value="statusLabel"
                                :severity="statusSeverity"
                                class="py-0!"
                            />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <Button
                    type="button"
                    icon="pi pi-pencil"
                    label="Editar"
                    size="small"
                    severity="info"
                    @click.stop="onEdit"
                />
                     <!-- <Button
                    type="button"
                    icon="pi pi-trash"
                    label="Eliminar"
                    size="small"
                    severity="danger"
                    @click.stop="onDelete"
                /> -->
            </div>
        </div>
    </li>
</template>

<style scoped>
/* Oculta marker nativo para que el chevron sea el único indicador */
details > summary::-webkit-details-marker {
    display: none;
}

details[open] .chevron {
    transform: rotate(90deg);
}

.chevron {
    transition: transform 0.15s ease-in-out;
}
</style>

