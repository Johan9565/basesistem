<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DependencyNode from '@/Components/DependencyNode.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import WrappingSelect from '@/Components/WrappingSelect.vue';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

const props = defineProps({
    dependencies: Array,
});

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const deleteDialogVisible = ref(false);

const selectedDependencyId = ref(null);
const isDeleting = ref(false);

const statusOptions = [
    { label: 'Activo', value: 1 },
    { label: 'Inactivo', value: 0 },
];

const parentSelectPt = {
    panel: { class: 'rounded-xl border border-base-300 shadow-lg' },
    header: { class: 'p-2' },
    list: { class: 'py-1' },
    option: { class: 'px-3 py-2 text-sm leading-5' },
    filterInput: { class: 'w-full' },
};

const form = useForm({
    name: '',
    status: 1,
    parent_id: null,
});

const flatDependencies = computed(() => {
    const out = [];
    const walk = (nodes) => {
        (nodes ?? []).forEach((n) => {
            if (!n) return;
            out.push(n);
            if (Array.isArray(n.children) && n.children.length) {
                walk(n.children);
            }
        });
    };
    walk(props.dependencies);
    return out;
});

const selectedDependency = computed(() => {
    if (!selectedDependencyId.value) return null;
    return flatDependencies.value.find((d) => d.id === selectedDependencyId.value) ?? null;
});

function collectDescendantIds(rootId) {
    const root = flatDependencies.value.find((d) => d.id === rootId);
    const excluded = new Set();
    const walk = (node) => {
        (node?.children ?? []).forEach((child) => {
            if (!excluded.has(child.id)) {
                excluded.add(child.id);
                walk(child);
            }
        });
    };
    walk(root);
    return excluded;
}

const parentOptionsForCreate = computed(() => {
    return [
        { label: 'Sin padre', value: null },
        ...flatDependencies.value.map((d) => ({ label: d.name, value: d.id })),
    ];
});

const parentOptionsForEdit = computed(() => {
    if (!selectedDependencyId.value) return parentOptionsForCreate.value;

    const excluded = new Set([selectedDependencyId.value]);
    const descendantIds = collectDescendantIds(selectedDependencyId.value);
    descendantIds.forEach((id) => excluded.add(id));

    return [
        { label: 'Sin padre', value: null },
        ...flatDependencies.value
            .filter((d) => !excluded.has(d.id))
            .map((d) => ({ label: d.name, value: d.id })),
    ];
});

function openCreateDialog() {
    selectedDependencyId.value = null;
    form.reset();
    form.name = '';
    form.status = 1;
    form.parent_id = null;
    form.clearErrors();
    createDialogVisible.value = true;
}

function closeCreateDialog() {
    createDialogVisible.value = false;
    form.clearErrors();
    form.reset();
}

function openEditDialog(id) {
    selectedDependencyId.value = id;
    const dep = flatDependencies.value.find((d) => d.id === id);

    form.name = dep?.name ?? '';
    form.status = dep?.status ?? 1;
    form.parent_id = dep?.parent_id ?? null;

    form.clearErrors();
    editDialogVisible.value = true;
}

function closeEditDialog() {
    editDialogVisible.value = false;
    selectedDependencyId.value = null;
    form.clearErrors();
    form.reset();
}

function openDeleteDialog(id) {
    selectedDependencyId.value = id;
    deleteDialogVisible.value = true;
}

function closeDeleteDialog() {
    deleteDialogVisible.value = false;
    selectedDependencyId.value = null;
}

function submitCreate() {
    form.post(route('dependencies.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Creado',
                detail: 'La dependencia se creó correctamente.',
                life: 3000,
            });
            closeCreateDialog();
        },
    });
}

function submitEdit() {
    if (!selectedDependencyId.value) return;

    form.patch(route('dependencies.update', selectedDependencyId.value), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Actualizado',
                detail: 'La dependencia se actualizó correctamente.',
                life: 3000,
            });
            closeEditDialog();
        },
    });
}

function submitDelete() {
    if (!selectedDependencyId.value) return;
    isDeleting.value = true;

    router.delete(route('dependencies.destroy', selectedDependencyId.value), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Eliminado',
                detail: 'La dependencia se eliminó correctamente.',
                life: 3000,
            });
            closeDeleteDialog();
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}

function editDependency(id) {
    openEditDialog(id);
}

function deleteDependency(id) {
    openDeleteDialog(id);
}
</script>

<template>
    <Head title="Dependencias" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-base-content">Dependencias</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
                    <div
                        class="flex flex-col gap-3 p-6 border-b border-base-300 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <h3 class="text-lg font-medium text-base-content">Dependencias</h3>
                            <p class="mt-1 text-sm text-base-content/60">
                                {{ flatDependencies.length }} dependencia{{ flatDependencies.length !== 1 ? 's' : '' }}
                                en el árbol jerárquico.
                            </p>
                        </div>

                        <Button
                            label="Agregar dependencia"
                            icon="pi pi-plus"
                            severity="secondary"
                            @click="openCreateDialog"
                        />
                    </div>

                    <div class="p-6">
                        <ul class="space-y-2">
                            <DependencyNode
                                v-for="dep in dependencies"
                                :key="dep.id"
                                :dependency="dep"
                                @edit="editDependency"
                                @delete="deleteDependency"
                            />
                        </ul>
                        <div
                            v-if="!dependencies?.length"
                            class="py-10 text-center text-sm text-base-content/60"
                        >
                            No hay dependencias registradas.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crear -->
        <Dialog
            v-model:visible="createDialogVisible"
            modal
            header="Agregar dependencia"
            :style="{ width: '32rem' }"
            @hide="closeCreateDialog"
        >
            <form @submit.prevent="submitCreate" class="space-y-4">
                <div>
                    <InputLabel value="Nombre" />
                    <InputText v-model="form.name" class="mt-1 block w-full" autocomplete="name" />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <div>
                    <InputLabel value="Estado" />
                    <Select
                        v-model="form.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.status" class="mt-2" />
                </div>

                <div>
                    <InputLabel value="Padre (opcional)" />
                    <WrappingSelect
                        v-model="form.parent_id"
                        panel-preset="!max-w-[min(12rem,calc(100vw-2rem))] min-w-0 overflow-hidden"
                        :options="parentOptionsForCreate"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 block w-full"
                        filter
                        filterPlaceholder="Buscar dependencia..."
                    />
                    <InputError :message="form.errors.parent_id" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text"
                        :disabled="form.processing"
                        @click="closeCreateDialog"
                    />
                    <Button
                        type="submit"
                        label="Guardar"
                        icon="pi pi-check"
                        :disabled="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Editar -->
        <Dialog
            v-model:visible="editDialogVisible"
            modal
            header="Editar dependencia"
            :style="{ width: '32rem' }"
            @hide="closeEditDialog"
        >
            <form @submit.prevent="submitEdit" class="space-y-4">
                <div>
                    <InputLabel value="Nombre" />
                    <InputText v-model="form.name" class="mt-1 block w-full" autocomplete="name" />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <div>
                    <InputLabel value="Estado" />
                    <Select
                        v-model="form.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.status" class="mt-2" />
                </div>

                <div>
                    <InputLabel value="Padre (opcional)" />
                    <WrappingSelect
                        v-model="form.parent_id"
                        :options="parentOptionsForEdit"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        filterPlaceholder="Buscar dependencia..."
                        :pt="parentSelectPt"
                        :virtualScrollerOptions="{ itemSize: 38 }"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.parent_id" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text"
                        :disabled="form.processing"
                        @click="closeEditDialog"
                    />
                    <Button
                        type="submit"
                        label="Guardar"
                        icon="pi pi-check"
                        :disabled="form.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Eliminar -->
        <Dialog
            v-model:visible="deleteDialogVisible"
            modal
            header="Eliminar dependencia"
            :style="{ width: '28rem' }"
            @hide="closeDeleteDialog"
        >
            <div class="space-y-4">
                <p class="text-sm text-base-content/70">
                    ¿Seguro que quieres eliminar
                    <strong>{{ selectedDependency?.name ?? 'esta dependencia' }}</strong>?
                    Se eliminará también todo su subárbol.
                </p>

                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text"
                        :disabled="isDeleting"
                        @click="closeDeleteDialog"
                    />
                    <Button
                        type="button"
                        label="Eliminar"
                        icon="pi pi-trash"
                        severity="danger"
                        :disabled="isDeleting"
                        @click="submitDelete"
                    />
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
