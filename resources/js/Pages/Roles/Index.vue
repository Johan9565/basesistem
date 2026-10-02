<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    allpermissions: {
        type: Array,
        default: () => [],
    },
    modules: {
        type: Array,
        default: () => [],
    },
});

const toast = useToast();

// Vistas: 'studio' (Configurador por Rol) o 'matrix' (Matriz Global)
const currentView = ref('studio');

// Rol actualmente seleccionado
const selectedRoleId = ref(props.roles.length > 0 ? props.roles[0].id : null);

// Filtros y búsqueda
const searchQuery = ref('');
const selectedGroupFilter = ref('all');
const permissionStateFilter = ref('all'); // 'all', 'granted', 'revoked'

// Estado reactivo local de permisos por rol para actualización inmediata (0ms)
const rolePermsMap = ref({});
function initRolePermsMap() {
    const map = {};
    props.roles.forEach((r) => {
        const ids = (r.permission_ids || (r.permissions || []).map((p) => p.id)).map(String);
        map[r.id] = new Set(ids);
    });
    rolePermsMap.value = map;
}
initRolePermsMap();

watch(
    () => props.roles,
    () => {
        initRolePermsMap();
        if (!selectedRoleId.value && props.roles.length > 0) {
            selectedRoleId.value = props.roles[0].id;
        }
    },
    { deep: true },
);

// Rol activo
const activeRole = computed(() => {
    return props.roles.find((r) => r.id === selectedRoleId.value) || props.roles[0] || null;
});

// Agrupación principal de permisos por grupo lógico del sistema
const groupedPermissions = computed(() => {
    const groupsMap = new Map();

    props.allpermissions.forEach((perm) => {
        const groupKey = perm.group_key || 'general';
        const groupName = perm.group_name || 'General';
        const groupOrder = perm.group_order ?? 99;

        if (!groupsMap.has(groupKey)) {
            groupsMap.set(groupKey, {
                key: groupKey,
                name: groupName,
                order: groupOrder,
                permissions: [],
            });
        }
        groupsMap.get(groupKey).permissions.push(perm);
    });

    return Array.from(groupsMap.values()).sort((a, b) => a.order - b.order);
});

// Opciones para el selector de filtro por grupo
const groupFilterOptions = computed(() => {
    const list = [{ label: 'Todos los Grupos', value: 'all' }];
    groupedPermissions.value.forEach((group) => {
        list.push({ label: group.name, value: group.key });
    });
    return list;
});

// Grupos y permisos filtrados según búsqueda y estado
const filteredGroups = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const groupFilter = selectedGroupFilter.value;
    const stateFilter = permissionStateFilter.value;
    const activePerms = activeRole.value ? rolePermsMap.value[activeRole.value.id] || new Set() : new Set();

    return groupedPermissions.value
        .map((group) => {
            if (groupFilter !== 'all' && group.key !== groupFilter) {
                return null;
            }

            const perms = group.permissions.filter((p) => {
                // Búsqueda por texto
                const matchesSearch =
                    !q ||
                    (p.name && p.name.toLowerCase().includes(q)) ||
                    (p.description && p.description.toLowerCase().includes(q)) ||
                    group.name.toLowerCase().includes(q);

                if (!matchesSearch) return false;

                // Filtro por estado para el rol activo
                if (stateFilter === 'granted') {
                    return activePerms.has(String(p.id));
                } else if (stateFilter === 'revoked') {
                    return !activePerms.has(String(p.id));
                }

                return true;
            });

            if (perms.length === 0) return null;

            return {
                ...group,
                permissions: perms,
            };
        })
        .filter(Boolean);
});

// Estado de guardado por rol
const isSaving = ref({});

// Guardar permisos en backend
function savePermissions(roleId, newIdsArray, successMsg = 'Permisos actualizados') {
    isSaving.value[roleId] = true;

    router.patch(
        route('roles.permissions.update', { role: roleId }),
        { permission_ids: newIdsArray },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['roles'],
            onSuccess: () => {
                isSaving.value[roleId] = false;
                toast.add({
                    severity: 'success',
                    summary: 'Guardado',
                    detail: successMsg,
                    life: 2000,
                });
            },
            onError: () => {
                isSaving.value[roleId] = false;
                initRolePermsMap();
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'No se pudieron guardar los permisos',
                    life: 3000,
                });
            },
        },
    );
}

// Alternar permiso individual
function togglePermission(roleId, permissionId) {
    const permIdStr = String(permissionId);
    const currentSet = new Set(rolePermsMap.value[roleId] || []);

    if (currentSet.has(permIdStr)) {
        currentSet.delete(permIdStr);
    } else {
        currentSet.add(permIdStr);
    }

    rolePermsMap.value[roleId] = currentSet;
    savePermissions(roleId, Array.from(currentSet), 'Permiso actualizado');
}

// Verificar si un rol tiene un permiso
function hasPermission(roleId, permissionId) {
    const currentSet = rolePermsMap.value[roleId];
    return currentSet ? currentSet.has(String(permissionId)) : false;
}

// Asignar todos los permisos a un rol
function grantAllPermissions(roleId) {
    const allIds = props.allpermissions.map((p) => String(p.id));
    rolePermsMap.value[roleId] = new Set(allIds);
    savePermissions(roleId, allIds, 'Todos los permisos asignados');
}

// Quitar todos los permisos a un rol
function revokeAllPermissions(roleId) {
    rolePermsMap.value[roleId] = new Set();
    savePermissions(roleId, [], 'Permisos revocados');
}

// Asignar o quitar todos los permisos de un grupo para un rol
function toggleGroupPermissions(roleId, groupKey, shouldGrant) {
    const group = groupedPermissions.value.find((g) => g.key === groupKey);
    if (!group) return;

    const currentSet = new Set(rolePermsMap.value[roleId] || []);
    group.permissions.forEach((p) => {
        const idStr = String(p.id);
        if (shouldGrant) {
            currentSet.add(idStr);
        } else {
            currentSet.delete(idStr);
        }
    });

    rolePermsMap.value[roleId] = currentSet;
    savePermissions(
        roleId,
        Array.from(currentSet),
        shouldGrant ? `Grupo ${group.name} activado` : `Grupo ${group.name} desactivado`,
    );
}

// Verificar si todos los permisos de un grupo están activos para un rol
function isGroupFullyGranted(roleId, groupKey) {
    const group = groupedPermissions.value.find((g) => g.key === groupKey);
    if (!group || !group.permissions.length) return false;
    const currentSet = rolePermsMap.value[roleId] || new Set();
    return group.permissions.every((p) => currentSet.has(String(p.id)));
}

// Contar permisos concedidos en un grupo para un rol
function getGroupGrantedCount(roleId, groupKey) {
    const group = groupedPermissions.value.find((g) => g.key === groupKey);
    if (!group) return 0;
    const currentSet = rolePermsMap.value[roleId] || new Set();
    return group.permissions.filter((p) => currentSet.has(String(p.id))).length;
}

// Cobertura porcentual de permisos para un rol
function getRoleCoverage(roleId) {
    if (!props.allpermissions.length) return 0;
    const count = (rolePermsMap.value[roleId] || new Set()).size;
    return Math.round((count / props.allpermissions.length) * 100);
}

// DIALOGS: Crear Rol
const createDialogVisible = ref(false);
const createForm = useForm({
    role: '',
    name: '',
    clone_from_role_id: '',
});

function openCreateDialog() {
    createForm.reset();
    createForm.clearErrors();
    createDialogVisible.value = true;
}

function autoSlugRole() {
    if (!createForm.name && createForm.role) {
        createForm.name = createForm.role
            .toLowerCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }
}

function submitCreateRole() {
    let permissionIds = [];
    if (createForm.clone_from_role_id) {
        const sourceRole = props.roles.find((r) => r.id === createForm.clone_from_role_id);
        if (sourceRole) {
            permissionIds = sourceRole.permission_ids || (sourceRole.permissions || []).map((p) => p.id);
        }
    }

    createForm.transform((data) => ({
        ...data,
        permission_ids: permissionIds,
    })).post(route('roles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createDialogVisible.value = false;
            toast.add({
                severity: 'success',
                summary: 'Rol Creado',
                detail: 'El nuevo rol ha sido registrado correctamente.',
                life: 3000,
            });
        },
    });
}

// DIALOGS: Editar Rol
const editDialogVisible = ref(false);
const editingRole = ref(null);
const editForm = useForm({
    role: '',
    name: '',
    status: 1,
});

function openEditDialog(role) {
    editingRole.value = role;
    editForm.reset();
    editForm.clearErrors();
    editForm.role = role.role || role.name;
    editForm.name = role.name || '';
    editForm.status = role.status ?? 1;
    editDialogVisible.value = true;
}

function submitEditRole() {
    if (!editingRole.value) return;

    editForm.patch(route('roles.update', { role: editingRole.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            editDialogVisible.value = false;
            toast.add({
                severity: 'success',
                summary: 'Rol Actualizado',
                detail: 'La información del rol se guardó con éxito.',
                life: 3000,
            });
        },
    });
}

// DIALOGS: Eliminar Rol
const deleteDialogVisible = ref(false);
const deletingRole = ref(null);
const isDeleting = ref(false);

function openDeleteDialog(role) {
    if (role.is_system) {
        toast.add({
            severity: 'warn',
            summary: 'Aviso',
            detail: 'Los roles del sistema son esenciales y no pueden eliminarse.',
            life: 3000,
        });
        return;
    }
    deletingRole.value = role;
    deleteDialogVisible.value = true;
}

function confirmDeleteRole() {
    if (!deletingRole.value) return;
    isDeleting.value = true;

    router.delete(route('roles.destroy', { role: deletingRole.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleting.value = false;
            deleteDialogVisible.value = false;
            if (selectedRoleId.value === deletingRole.value.id && props.roles.length > 1) {
                const remaining = props.roles.filter((r) => r.id !== deletingRole.value.id);
                selectedRoleId.value = remaining[0]?.id || null;
            }
            toast.add({
                severity: 'success',
                summary: 'Rol Eliminado',
                detail: 'El rol ha sido removido del sistema.',
                life: 3000,
            });
        },
        onError: () => {
            isDeleting.value = false;
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'No se pudo eliminar el rol.',
                life: 3000,
            });
        },
    });
}
</script>

<template>
    <Head title="Gestión de Roles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-base-content">
                        Roles y Permisos
                    </h2>
                    <p class="text-xs text-base-content/70 mt-0.5">
                        Administración de roles y asignación de privilegios por módulo.
                    </p>
                </div>

                <!-- Botones de Acción Superiores -->
                <div class="flex items-center gap-2">
                    <div class="join bg-base-100 p-1 border border-base-300 rounded-lg">
                        <button
                            type="button"
                            class="join-item btn btn-xs transition-all"
                            :class="currentView === 'studio' ? 'btn-primary' : 'btn-ghost text-base-content/70'"
                            @click="currentView = 'studio'"
                        >
                            Vista por Rol
                        </button>
                        <button
                            type="button"
                            class="join-item btn btn-xs transition-all"
                            :class="currentView === 'matrix' ? 'btn-primary' : 'btn-ghost text-base-content/70'"
                            @click="currentView = 'matrix'"
                        >
                            Matriz Global
                        </button>
                    </div>

                    <Button
                        label="Nuevo Rol"
                        icon="pi pi-plus"
                        class="p-button-primary p-button-sm"
                        @click="openCreateDialog"
                    />
                </div>
            </div>
        </template>

        <div class="py-6 bg-base-200/30 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ========================================== -->
                <!-- VISTA 1: CONFIGURADOR POR ROL              -->
                <!-- ========================================== -->
                <div v-if="currentView === 'studio'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Lista Lateral de Roles (4 columnas) -->
                    <div class="lg:col-span-4 space-y-4">
                        <div class="card bg-base-100 border border-base-300 shadow-xs overflow-hidden">
                            <div class="px-4 py-3 border-b border-base-300 bg-base-200/40 flex items-center justify-between">
                                <span class="font-bold text-xs uppercase tracking-wider text-base-content/70">Roles Disponibles</span>
                                <span class="badge badge-sm badge-neutral">{{ roles.length }}</span>
                            </div>

                            <div class="divide-y divide-base-200 max-h-[calc(100vh-260px)] overflow-y-auto">
                                <div
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="p-3.5 cursor-pointer transition-colors duration-150 hover:bg-base-200/40"
                                    :class="selectedRoleId === role.id ? 'bg-primary/5 border-l-4 border-primary' : ''"
                                    @click="selectedRoleId = role.id"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <h4 class="font-bold text-sm truncate" :class="selectedRoleId === role.id ? 'text-primary' : 'text-base-content'">
                                                    {{ role.role || role.name }}
                                                </h4>
                                                <span v-if="role.is_system" class="badge badge-xs badge-ghost text-[10px]">Sistema</span>
                                            </div>
                                            <p class="text-[11px] text-base-content/50 font-mono mt-0.5 truncate">
                                                {{ role.name }}
                                            </p>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="badge badge-sm" :class="selectedRoleId === role.id ? 'badge-primary' : 'badge-ghost'">
                                                {{ (rolePermsMap[role.id] || new Set()).size }} / {{ allpermissions.length }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Barra de cobertura sobria -->
                                    <div class="mt-2.5">
                                        <div class="w-full bg-base-200 rounded-full h-1.5 overflow-hidden">
                                            <div
                                                class="h-full bg-primary rounded-full transition-all duration-300"
                                                :style="{ width: getRoleCoverage(role.id) + '%' }"
                                            ></div>
                                        </div>
                                        <div class="flex justify-between items-center text-[10px] text-base-content/50 mt-1">
                                            <span>Cobertura</span>
                                            <span>{{ getRoleCoverage(role.id) }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Principal de Permisos del Rol Activo (8 columnas) -->
                    <div class="lg:col-span-8 space-y-5">

                        <div v-if="activeRole" class="card bg-base-100 border border-base-300 shadow-xs overflow-hidden">
                            <!-- Encabezado del Rol Seleccionado -->
                            <div class="p-5 border-b border-base-300 bg-base-200/20">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-6 bg-primary rounded-full"></span>
                                            <h3 class="text-lg font-bold text-base-content flex items-center gap-2">
                                                {{ activeRole.role || activeRole.name }}
                                                <span v-if="activeRole.is_system" class="badge badge-sm badge-ghost">Sistema</span>
                                            </h3>
                                        </div>
                                        <p class="text-xs text-base-content/60 mt-0.5 pl-4.5">
                                            Identificador: <span class="font-mono text-base-content/80 font-medium">{{ activeRole.name }}</span>
                                        </p>
                                    </div>

                                    <!-- Acciones del Rol -->
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-outline btn-primary"
                                            :disabled="isSaving[activeRole.id]"
                                            @click="grantAllPermissions(activeRole.id)"
                                        >
                                            Marcar Todos
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-xs btn-ghost text-base-content/70"
                                            :disabled="isSaving[activeRole.id]"
                                            @click="revokeAllPermissions(activeRole.id)"
                                        >
                                            Desmarcar Todos
                                        </button>

                                        <div class="dropdown dropdown-end">
                                            <label tabindex="0" class="btn btn-xs btn-ghost btn-circle">
                                                <i class="pi pi-ellipsis-v text-xs text-base-content/70"></i>
                                            </label>
                                            <ul tabindex="0" class="dropdown-content z-10 menu p-1.5 shadow-md bg-base-100 rounded-lg w-40 border border-base-300 text-xs">
                                                <li>
                                                    <a @click="openEditDialog(activeRole)">
                                                        <i class="pi pi-pencil text-xs"></i>
                                                        Editar Nombre
                                                    </a>
                                                </li>
                                                <li v-if="!activeRole.is_system">
                                                    <a @click="openDeleteDialog(activeRole)" class="text-error">
                                                        <i class="pi pi-trash text-xs"></i>
                                                        Eliminar Rol
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filtros y Búsqueda -->
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-12 gap-2.5">
                                    <div class="sm:col-span-6 relative">
                                        <InputText
                                            v-model="searchQuery"
                                            placeholder="Buscar permiso..."
                                            class="w-full text-xs !pl-8 !py-1.5"
                                        />
                                        <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-base-content/40 text-xs"></i>
                                        <button
                                            v-if="searchQuery"
                                            type="button"
                                            @click="searchQuery = ''"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content"
                                        >
                                            <i class="pi pi-times text-xs"></i>
                                        </button>
                                    </div>

                                    <div class="sm:col-span-3">
                                        <Select
                                            v-model="selectedGroupFilter"
                                            :options="groupFilterOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs"
                                        />
                                    </div>

                                    <div class="sm:col-span-3">
                                        <Select
                                            v-model="permissionStateFilter"
                                            :options="[
                                                { label: 'Todos los estados', value: 'all' },
                                                { label: 'Solo Asignados', value: 'granted' },
                                                { label: 'Solo No Asignados', value: 'revoked' },
                                            ]"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Lista de Grupos y Permisos -->
                            <div class="p-5 space-y-5">
                                <div v-if="filteredGroups.length === 0" class="text-center py-10 text-base-content/60">
                                    <p class="text-sm font-medium">No se encontraron permisos coincidentes</p>
                                </div>

                                <!-- Cada Grupo Lógico (Administración, WhatsApp CRM, Inventario & Servicios, etc.) -->
                                <div
                                    v-for="group in filteredGroups"
                                    :key="group.key"
                                    class="border border-base-300 rounded-xl overflow-hidden bg-base-100"
                                >
                                    <!-- Encabezado del Grupo -->
                                    <div class="bg-base-200/40 px-4 py-2.5 border-b border-base-300 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-xs uppercase tracking-wider text-base-content">
                                                {{ group.name }}
                                            </h4>
                                            <span class="badge badge-sm badge-ghost text-[11px]">
                                                {{ getGroupGrantedCount(activeRole.id, group.key) }} / {{ group.permissions.length }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                v-if="!isGroupFullyGranted(activeRole.id, group.key)"
                                                class="btn btn-xs btn-ghost text-primary text-[11px]"
                                                @click="toggleGroupPermissions(activeRole.id, group.key, true)"
                                            >
                                                Activar Todo el Grupo
                                            </button>
                                            <button
                                                type="button"
                                                v-else
                                                class="btn btn-xs btn-ghost text-base-content/60 text-[11px]"
                                                @click="toggleGroupPermissions(activeRole.id, group.key, false)"
                                            >
                                                Desactivar Todo el Grupo
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Grilla de Permisos del Grupo -->
                                    <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-2.5">
                                        <div
                                            v-for="permission in group.permissions"
                                            :key="permission.id"
                                            class="p-3 rounded-lg border transition-all duration-150 flex items-center justify-between gap-3 cursor-pointer"
                                            :class="hasPermission(activeRole.id, permission.id)
                                                ? 'bg-primary/5 border-primary/30'
                                                : 'bg-base-100 border-base-200 hover:border-base-300 hover:bg-base-200/20'"
                                            @click="togglePermission(activeRole.id, permission.id)"
                                        >
                                            <div class="flex flex-col min-w-0 pr-2">
                                                <span class="font-semibold text-xs text-base-content leading-snug truncate">
                                                    {{ permission.description || permission.name }}
                                                </span>
                                                <span class="text-[10px] text-base-content/50 font-mono mt-0.5 truncate">
                                                    slug: {{ permission.name }}
                                                </span>
                                            </div>

                                            <div class="shrink-0 flex items-center" @click.stop>
                                                <input
                                                    type="checkbox"
                                                    class="toggle toggle-primary toggle-sm"
                                                    :checked="hasPermission(activeRole.id, permission.id)"
                                                    @change="togglePermission(activeRole.id, permission.id)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Informativo -->
                            <div class="px-5 py-2.5 bg-base-200/20 border-t border-base-300 flex items-center justify-between text-xs text-base-content/50">
                                <span>Los cambios se guardan automáticamente.</span>
                                <span v-if="isSaving[activeRole.id]" class="text-primary font-medium flex items-center gap-1.5">
                                    <span class="loading loading-spinner loading-xs"></span>
                                    Guardando...
                                </span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ========================================== -->
                <!-- VISTA 2: MATRIZ GLOBAL COMPARATIVA        -->
                <!-- ========================================== -->
                <div v-else class="card bg-base-100 border border-base-300 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-base-300 bg-base-200/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-base-content">Matriz Global de Permisos</h3>
                            <p class="text-xs text-base-content/60">
                                Comparación general de privilegios entre todos los roles.
                            </p>
                        </div>
                        <div class="relative w-full sm:w-64">
                            <InputText
                                v-model="searchQuery"
                                placeholder="Filtrar permisos..."
                                class="w-full text-xs !pl-8 !py-1.5"
                            />
                            <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-base-content/40 text-xs"></i>
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-[calc(100vh-260px)]">
                        <table class="table table-pin-rows table-pin-cols w-full text-xs">
                            <thead>
                                <tr class="bg-base-200/80 border-b border-base-300">
                                    <th class="bg-base-200 min-w-[220px] font-bold text-xs uppercase tracking-wider text-base-content">
                                        Grupo / Permiso
                                    </th>
                                    <th
                                        v-for="role in roles"
                                        :key="role.id"
                                        class="text-center font-bold text-xs uppercase tracking-wider min-w-[130px]"
                                    >
                                        <div class="flex flex-col items-center gap-0.5 py-1">
                                            <span class="text-base-content">{{ role.role || role.name }}</span>
                                            <span class="badge badge-xs badge-ghost">
                                                {{ (rolePermsMap[role.id] || new Set()).size }} activos
                                            </span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="group in filteredGroups" :key="group.key">
                                    <!-- Fila de Encabezado de Grupo -->
                                    <tr class="bg-base-200/50 font-bold border-t border-b border-base-300">
                                        <td :colspan="roles.length + 1" class="py-2 px-4 text-xs uppercase tracking-wider text-base-content font-bold">
                                            {{ group.name }} ({{ group.permissions.length }} permisos)
                                        </td>
                                    </tr>

                                    <!-- Filas de Permisos -->
                                    <tr
                                        v-for="perm in group.permissions"
                                        :key="perm.id"
                                        class="hover:bg-base-200/20 border-b border-base-200 transition-colors"
                                    >
                                        <td class="bg-base-100 font-medium">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-base-content">
                                                    {{ perm.description || perm.name }}
                                                </span>
                                                <span class="text-[10px] text-base-content/50 font-mono">
                                                    {{ perm.name }}
                                                </span>
                                            </div>
                                        </td>

                                        <td
                                            v-for="role in roles"
                                            :key="role.id"
                                            class="text-center"
                                        >
                                            <input
                                                type="checkbox"
                                                class="toggle toggle-primary toggle-sm"
                                                :checked="hasPermission(role.id, perm.id)"
                                                @change="togglePermission(role.id, perm.id)"
                                            />
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- DIALOG: CREAR NUEVO ROL                    -->
        <!-- ========================================== -->
        <Dialog
            v-model:visible="createDialogVisible"
            modal
            header="Crear Nuevo Rol"
            :style="{ width: '460px' }"
            :breakpoints="{ '640px': '95vw' }"
        >
            <form @submit.prevent="submitCreateRole" class="space-y-4 pt-2">
                <div>
                    <InputLabel for="role_name" value="Nombre del Rol *" />
                    <InputText
                        id="role_name"
                        v-model="createForm.role"
                        placeholder="Ej. Supervisor de Operaciones"
                        class="w-full mt-1 text-sm"
                        @blur="autoSlugRole"
                        required
                    />
                    <InputError :message="createForm.errors.role" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="slug_name" value="Identificador Técnico (Slug) *" />
                    <InputText
                        id="slug_name"
                        v-model="createForm.name"
                        placeholder="Ej. supervisor_operaciones"
                        class="w-full mt-1 font-mono text-xs"
                        required
                    />
                    <p class="text-[11px] text-base-content/50 mt-1">
                        Código único en minúsculas para validaciones internas.
                    </p>
                    <InputError :message="createForm.errors.name" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="clone_from" value="Clonar Permisos Iniciales (Opcional)" />
                    <Select
                        id="clone_from"
                        v-model="createForm.clone_from_role_id"
                        :options="[
                            { label: '— Sin permisos iniciales —', value: '' },
                            ...roles.map((r) => ({ label: `Copiar de: ${r.role || r.name}`, value: r.id })),
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full mt-1 text-sm"
                    />
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-base-300">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text p-button-secondary p-button-sm"
                        @click="createDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Crear Rol"
                        icon="pi pi-check"
                        class="p-button-primary p-button-sm"
                        :loading="createForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- ========================================== -->
        <!-- DIALOG: EDITAR ROL                         -->
        <!-- ========================================== -->
        <Dialog
            v-model:visible="editDialogVisible"
            modal
            header="Editar Rol"
            :style="{ width: '460px' }"
            :breakpoints="{ '640px': '95vw' }"
        >
            <form @submit.prevent="submitEditRole" class="space-y-4 pt-2">
                <div>
                    <InputLabel for="edit_role_name" value="Nombre del Rol *" />
                    <InputText
                        id="edit_role_name"
                        v-model="editForm.role"
                        placeholder="Nombre descriptivo del rol"
                        class="w-full mt-1 text-sm"
                        required
                    />
                    <InputError :message="editForm.errors.role" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="edit_slug_name" value="Identificador Técnico" />
                    <InputText
                        id="edit_slug_name"
                        v-model="editForm.name"
                        :disabled="editingRole?.is_system"
                        class="w-full mt-1 font-mono text-xs"
                    />
                    <p v-if="editingRole?.is_system" class="text-[11px] text-base-content/50 mt-1">
                        El identificador de los roles del sistema no se puede modificar.
                    </p>
                    <InputError :message="editForm.errors.name" class="mt-1" />
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-base-300">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text p-button-secondary p-button-sm"
                        @click="editDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Guardar Cambios"
                        icon="pi pi-check"
                        class="p-button-primary p-button-sm"
                        :loading="editForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <!-- ========================================== -->
        <!-- DIALOG: CONFIRMAR ELIMINACIÓN DE ROL       -->
        <!-- ========================================== -->
        <Dialog
            v-model:visible="deleteDialogVisible"
            modal
            header="Confirmar Eliminación"
            :style="{ width: '400px' }"
        >
            <div class="space-y-3 pt-2">
                <p class="text-xs text-base-content/80">
                    ¿Estás seguro de eliminar el rol <strong class="text-base-content">{{ deletingRole?.role || deletingRole?.name }}</strong>?
                </p>

                <div class="flex justify-end gap-2 pt-3 border-t border-base-300">
                    <Button
                        type="button"
                        label="Cancelar"
                        class="p-button-text p-button-secondary p-button-sm"
                        @click="deleteDialogVisible = false"
                    />
                    <Button
                        type="button"
                        label="Eliminar"
                        icon="pi pi-trash"
                        class="p-button-danger p-button-sm"
                        :loading="isDeleting"
                        @click="confirmDeleteRole"
                    />
                </div>
            </div>
        </Dialog>

    </AuthenticatedLayout>
</template>
