<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Checkbox from 'primevue/checkbox';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    item: {
        type: Object,
        default: null,
    },
    type: {
        type: String,
        default: 'task', // 'task' | 'reminder' | 'event' | 'note'
    },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const toast = useToast();
const isSaving = ref(false);
const currentType = ref('task');
const newTagInput = ref('');

// Form state
const form = ref({
    title: '',
    description: '',
    content: '',
    start_time: '',
    end_time: '',
    scheduled_at: '',
    location: '',
    priority: 'medium',
    status: 'pending',
    category: 'General',
    tags: [],
    is_pinned: false,
});

const entityLabels = {
    event: { name: 'Cita / Evento', icon: 'pi-calendar', color: 'text-blue-500' },
    reminder: { name: 'Recordatorio', icon: 'pi-bell', color: 'text-amber-500' },
    task: { name: 'Tarea / Pendiente', icon: 'pi-check-square', color: 'text-emerald-500' },
    note: { name: 'Nota / Bitácora', icon: 'pi-file-edit', color: 'text-purple-500' },
};

const priorities = [
    { value: 'low', label: 'Baja', severity: 'secondary' },
    { value: 'medium', label: 'Media', severity: 'warn' },
    { value: 'high', label: 'Alta', severity: 'danger' },
];

const categories = [
    'General',
    'Desarrollo/Tech',
    'Personal/Compras',
    'Casa/Admin',
];

function toDatetimeLocal(val) {
    if (!val) return '';
    try {
        const d = new Date(val);
        if (isNaN(d.getTime())) {
            return String(val).replace(' ', 'T').slice(0, 16);
        }
        const pad = (n) => String(n).padStart(2, '0');
        const year = d.getFullYear();
        const month = pad(d.getMonth() + 1);
        const day = pad(d.getDate());
        const hours = pad(d.getHours());
        const minutes = pad(d.getMinutes());
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    } catch {
        return '';
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen && props.item) {
            currentType.value = props.type || (props.item.start_time ? 'event' : (props.item.scheduled_at ? 'reminder' : (props.item.tags?.includes('todo') ? 'task' : 'note')));
            
            const rawTags = props.item.tags;
            const parsedTags = Array.isArray(rawTags) 
                ? [...rawTags] 
                : (typeof rawTags === 'string' ? (JSON.parse(rawTags || '[]') || [rawTags]) : []);

            form.value = {
                title: props.item.title || '',
                description: props.item.description || props.item.content || '',
                content: props.item.content || props.item.description || '',
                start_time: toDatetimeLocal(props.item.start_time || props.item.start_time_iso),
                end_time: toDatetimeLocal(props.item.end_time || props.item.end_time_iso),
                scheduled_at: toDatetimeLocal(props.item.scheduled_at || props.item.scheduled_at_iso || props.item.remind_at),
                location: props.item.location || '',
                priority: props.item.priority || 'medium',
                status: props.item.status || 'pending',
                category: props.item.category || 'General',
                tags: parsedTags,
                is_pinned: Boolean(props.item.is_pinned),
            };
            newTagInput.value = '';
        }
    },
    { immediate: true }
);

function addTag() {
    const clean = newTagInput.value.trim().toLowerCase().replace(/^#/, '');
    if (clean && !form.value.tags.includes(clean)) {
        form.value.tags.push(clean);
    }
    newTagInput.value = '';
}

function removeTag(tagToRemove) {
    form.value.tags = form.value.tags.filter((t) => t !== tagToRemove);
}

function closeDialog() {
    emit('update:modelValue', false);
}

async function saveChanges() {
    if (!form.value.title.trim()) {
        toast.add({
            severity: 'warn',
            summary: 'Título requerido',
            detail: 'El título del elemento es obligatorio.',
            life: 3000,
        });
        return;
    }

    const id = props.item?.id || props.item?._id;
    if (!id) return;

    isSaving.value = true;

    // Construct clean payload based on entity type
    const payload = {
        title: form.value.title.trim(),
    };

    if (currentType.value === 'event') {
        payload.start_time = form.value.start_time;
        if (form.value.end_time) payload.end_time = form.value.end_time;
        if (form.value.location) payload.location = form.value.location;
        payload.description = form.value.description;
        payload.status = form.value.status;
    } else if (currentType.value === 'reminder') {
        payload.scheduled_at = form.value.scheduled_at;
        payload.priority = form.value.priority;
        payload.status = form.value.status;
        if (form.value.description) payload.description = form.value.description;
    } else if (currentType.value === 'task') {
        payload.content = form.value.content || form.value.description || form.value.title;
        payload.status = form.value.status;
        payload.priority = form.value.priority;
        payload.category = form.value.category;
        payload.tags = Array.from(new Set([...form.value.tags, 'todo', 'pendiente']));
        payload.is_pinned = form.value.is_pinned;
    } else {
        // note
        payload.content = form.value.content || form.value.description || form.value.title;
        payload.tags = form.value.tags;
        payload.is_pinned = form.value.is_pinned;
        if (form.value.status) payload.status = form.value.status;
    }

    try {
        const endpoint = `/api/v1/assistant/entities/${currentType.value}/${id}`;
        const response = await axios.patch(endpoint, payload);

        if (response.data && response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Actualizado',
                detail: response.data.message || 'Registro modificado con éxito.',
                life: 3000,
            });

            emit('saved', {
                type: currentType.value,
                id: id,
                data: response.data.data,
            });

            closeDialog();
        } else {
            throw new Error(response.data?.message || 'Error al guardar los cambios.');
        }
    } catch (error) {
        console.error('Error al actualizar registro:', error);
        toast.add({
            severity: 'error',
            summary: 'Error al actualizar',
            detail: error.response?.data?.message || error.message || 'No se pudo guardar la modificación.',
            life: 4000,
        });
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Dialog
        :visible="modelValue"
        @update:visible="emit('update:modelValue', $event)"
        modal
        :style="{ width: '92vw', maxWidth: '520px' }"
        class="!rounded-2xl overflow-hidden shadow-2xl border border-surface-200 dark:border-surface-800"
        :closable="!isSaving"
    >
        <template #header>
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                    <i :class="['pi text-sm', entityLabels[currentType]?.icon || 'pi-pencil', entityLabels[currentType]?.color || 'text-primary-500']"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-100 m-0">
                        Editar {{ entityLabels[currentType]?.name || 'Registro' }}
                    </h3>
                    <p class="text-[11px] text-surface-500 dark:text-surface-400 m-0">
                        Modifica los detalles del registro seleccionado
                    </p>
                </div>
            </div>
        </template>

        <form @submit.prevent="saveChanges" class="space-y-4 py-2 text-xs">
            <!-- Título Común -->
            <div class="space-y-1.5">
                <label class="font-medium text-surface-700 dark:text-surface-300 flex items-center justify-between">
                    <span>Título *</span>
                    <span class="text-[10px] text-surface-400">Requerido</span>
                </label>
                <InputText
                    v-model="form.title"
                    required
                    placeholder="Título descriptivo..."
                    class="w-full text-xs"
                    autofocus
                />
            </div>

            <!-- CAMPOS DINÁMICOS SEGÚN TIPO -->

            <!-- 1. EVENTO / CITA -->
            <div v-if="currentType === 'event'" class="space-y-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Fecha y Hora Inicio *
                        </label>
                        <input
                            v-model="form.start_time"
                            type="datetime-local"
                            required
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden focus:ring-1 focus:ring-primary-500"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Fecha y Hora Fin
                        </label>
                        <input
                            v-model="form.end_time"
                            type="datetime-local"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden focus:ring-1 focus:ring-primary-500"
                        />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-surface-700 dark:text-surface-300">
                        Ubicación o Enlace Virtual
                    </label>
                    <InputText
                        v-model="form.location"
                        placeholder="ej. Sala A, Google Meet..."
                        class="w-full text-xs"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-surface-700 dark:text-surface-300">
                        Descripción o Notas adicionales
                    </label>
                    <Textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Detalles sobre los acuerdos o temas a tratar..."
                        class="w-full text-xs"
                    />
                </div>
            </div>

            <!-- 2. RECORDATORIO -->
            <div v-else-if="currentType === 'reminder'" class="space-y-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Fecha y Hora Programada *
                        </label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            required
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden focus:ring-1 focus:ring-primary-500"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Prioridad
                        </label>
                        <select
                            v-model="form.priority"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden"
                        >
                            <option v-for="p in priorities" :key="p.value" :value="p.value">
                                {{ p.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Estado
                        </label>
                        <select
                            v-model="form.status"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden"
                        >
                            <option value="pending">Pendiente</option>
                            <option value="completed">Completado</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Categoría
                        </label>
                        <select
                            v-model="form.category"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden"
                        >
                            <option v-for="cat in categories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-surface-700 dark:text-surface-300">
                        Notas o Detalles adicionales
                    </label>
                    <Textarea
                        v-model="form.description"
                        rows="2"
                        placeholder="Detalles adicionales del recordatorio..."
                        class="w-full text-xs"
                    />
                </div>
            </div>

            <!-- 3. TAREA / PENDIENTE -->
            <div v-else-if="currentType === 'task'" class="space-y-3.5">
                <div class="space-y-1.5">
                    <label class="font-medium text-surface-700 dark:text-surface-300">
                        Descripción detallada
                    </label>
                    <Textarea
                        v-model="form.content"
                        rows="3"
                        placeholder="Describe la tarea o pendiente a realizar..."
                        class="w-full text-xs"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Prioridad
                        </label>
                        <select
                            v-model="form.priority"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden"
                        >
                            <option v-for="p in priorities" :key="p.value" :value="p.value">
                                {{ p.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Categoría
                        </label>
                        <select
                            v-model="form.category"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden"
                        >
                            <option v-for="cat in categories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="font-medium text-surface-700 dark:text-surface-300">
                            Estado
                        </label>
                        <select
                            v-model="form.status"
                            class="w-full text-xs rounded-md border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-2.5 py-2 text-surface-900 dark:text-surface-100 focus:outline-hidden"
                        >
                            <option value="pending">Pendiente</option>
                            <option value="completed">Completado</option>
                        </select>
                    </div>
                </div>

                <!-- Etiquetas Tags -->
                <div class="space-y-1.5">
                    <label class="font-medium text-surface-700 dark:text-surface-300">
                        Etiquetas (#tags)
                    </label>
                    <div class="flex items-center gap-1.5">
                        <InputText
                            v-model="newTagInput"
                            placeholder="Agregar etiqueta y presionar Enter..."
                            class="flex-1 text-xs"
                            @keydown.enter.prevent="addTag"
                        />
                        <Button
                            type="button"
                            icon="pi pi-plus"
                            size="small"
                            severity="secondary"
                            @click="addTag"
                            class="!text-xs shrink-0"
                        />
                    </div>
                    <div v-if="form.tags.length" class="flex flex-wrap gap-1 pt-1">
                        <span
                            v-for="t in form.tags"
                            :key="t"
                            class="inline-flex items-center gap-1 text-[11px] font-mono bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 rounded px-2 py-0.5"
                        >
                            #{{ t }}
                            <button
                                type="button"
                                @click="removeTag(t)"
                                class="text-surface-400 hover:text-red-500 cursor-pointer ml-0.5"
                            >
                                &times;
                            </button>
                        </span>
                    </div>
                </div>
            </div>

            <!-- 4. NOTA / BITÁCORA -->
            <div v-else-if="currentType === 'note'" class="space-y-3.5">
                <div class="space-y-1.5">
                    <label class="font-medium text-surface-700 dark:text-surface-300">
                        Contenido / Cuerpo de la nota *
                    </label>
                    <Textarea
                        v-model="form.content"
                        rows="5"
                        required
                        placeholder="Escribe el contenido o apunte de la nota..."
                        class="w-full text-xs font-sans leading-relaxed"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-surface-700 dark:text-surface-300">
                        Etiquetas Temáticas (#tags)
                    </label>
                    <div class="flex items-center gap-1.5">
                        <InputText
                            v-model="newTagInput"
                            placeholder="ej. desarrollo, finanzas..."
                            class="flex-1 text-xs"
                            @keydown.enter.prevent="addTag"
                        />
                        <Button
                            type="button"
                            icon="pi pi-plus"
                            size="small"
                            severity="secondary"
                            @click="addTag"
                            class="!text-xs shrink-0"
                        />
                    </div>
                    <div v-if="form.tags.length" class="flex flex-wrap gap-1 pt-1">
                        <span
                            v-for="t in form.tags"
                            :key="t"
                            class="inline-flex items-center gap-1 text-[11px] font-mono bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-300 rounded px-2 py-0.5"
                        >
                            #{{ t }}
                            <button
                                type="button"
                                @click="removeTag(t)"
                                class="text-surface-400 hover:text-red-500 cursor-pointer ml-0.5"
                            >
                                &times;
                            </button>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <Checkbox
                        v-model="form.is_pinned"
                        :binary="true"
                        inputId="edit_is_pinned"
                    />
                    <label for="edit_is_pinned" class="text-xs text-surface-700 dark:text-surface-300 cursor-pointer select-none">
                        Fijar nota en la parte superior (marcador prioritario)
                    </label>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-surface-200 dark:border-surface-800 mt-2">
                <Button
                    label="Cancelar"
                    severity="secondary"
                    text
                    size="small"
                    type="button"
                    :disabled="isSaving"
                    @click="closeDialog"
                    class="!text-xs"
                />
                <Button
                    label="Guardar cambios"
                    icon="pi pi-check"
                    size="small"
                    type="submit"
                    :loading="isSaving"
                    class="!text-xs"
                />
            </div>
        </form>
    </Dialog>
</template>
