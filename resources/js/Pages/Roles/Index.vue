<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    roles: Array,
    allpermissions: {
        type: Array,
        default: () => [],
    },
});

function updatePermissions(role, permission, event) {
    const isChecked = event.target.checked;
    const currentIds = (role.permissions || []).map((p) => p.id);
    const newIds = isChecked
        ? (currentIds.includes(permission.id) ? currentIds : [...currentIds, permission.id])
        : currentIds.filter((id) => id !== permission.id);

    router.patch(route('roles.permissions.update', { role: role.id }), {
        permission_ids: newIds,
    });
}
</script>

<template>
    <Head title="Gestión de Roles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold tracking-tight text-base-content">
                    Configuración de Roles
                </h2>
                <div class="badge badge-primary badge-outline gap-2 p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block h-4 w-4 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    {{ roles.length }} Roles Activos
                </div>
            </div>
        </template>

        <div class="py-10 bg-base-200/50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="grid grid-cols-1 gap-8">
                    <div v-for="role in roles" :key="role.id"
                         class="card bg-base-100 shadow-sm border border-base-300 overflow-hidden">

                        <div class="card-body p-0">
                            <div class="flex flex-wrap items-center justify-between gap-4 bg-base-200/30 px-6 py-4 border-b border-base-300">
                                <div>
                                    <h3 class="text-lg font-bold flex items-center gap-2">
                                        <span class="w-2 h-6 bg-primary rounded-full"></span>
                                        {{ role.role }}
                                    </h3>
                                    <p class="text-xs text-base-content/60 uppercase tracking-widest mt-1">Nivel de Acceso</p>
                                </div>

                                <div class="flex gap-2">
                                    <div class="stats shadow bg-base-100 border border-base-300">
                                        <div class="stat py-2 px-4">
                                            <div class="stat-title text-[10px]">Permisos</div>
                                            <div class="stat-value text-sm text-primary">
                                                {{ role.permissions?.length || 0 }} / {{ allpermissions.length }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div v-for="permission in allpermissions" :key="permission.id"
                                         class="form-control transition-all duration-200 hover:bg-base-200/50 p-3 rounded-xl border border-transparent hover:border-base-300">

                                        <label class="label cursor-pointer flex justify-between items-center gap-4">
                                            <div class="flex flex-col">
                                                <span class="font-medium text-sm text-base-content leading-tight">
                                                    {{ permission.description || permission.name }}
                                                </span>
                                                <span class="text-[11px] text-base-content/50 italic">
                                                    ID: {{ permission.name.toLowerCase().replace(/\s+/g, '_') }}
                                                </span>
                                            </div>

                                            <input
                                                type="checkbox"
                                                class="toggle toggle-primary toggle-sm"
                                                :checked="role.permissions?.some(p => p.id === permission.id)"
                                                @change="updatePermissions(role, permission, $event)"
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="px-6 py-3 bg-base-200/10 border-t border-base-300 flex justify-end">
                                <span class="text-[10px] text-base-content/40 italic">Última modificación detectada en tiempo real</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
