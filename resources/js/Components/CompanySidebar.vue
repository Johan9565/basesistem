<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
    menu: {
        type: Array,
        default: () => [],
    },
});

const isCollapsed = ref(false);

onMounted(() => {
    try {
        const saved = localStorage.getItem('company_sidebar_collapsed');
        if (saved !== null) {
            isCollapsed.value = saved === 'true';
        }
    } catch {
        // localStorage no disponible
    }
});

function toggleCollapse() {
    isCollapsed.value = !isCollapsed.value;
    try {
        localStorage.setItem('company_sidebar_collapsed', String(isCollapsed.value));
    } catch {
        // ignore
    }
}

function isRouteActive(routeName) {
    if (!routeName) return false;
    try {
        if (route().current(routeName)) return true;
        if (route().current(`${routeName}.*`)) return true;
        const current = route().current();
        if (current && typeof current === 'string') {
            return current === routeName || current.startsWith(`${routeName}.`);
        }
    } catch {
        return false;
    }
    return false;
}
</script>

<template>
    <aside
        class="hidden lg:flex flex-col shrink-0 sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto border-r border-base-300 bg-base-100/95 backdrop-blur-md transition-all duration-300 ease-in-out z-20 select-none"
        :class="isCollapsed ? 'w-20' : 'w-64'"
    >
        <!-- Cabecera de Empresa Activa -->
        <div class="p-3 border-b border-base-300/80">
            <div
                class="flex items-center gap-3 rounded-xl p-2 transition-colors duration-150"
                :class="isCollapsed ? 'justify-center' : 'bg-base-200/50'"
                :title="isCollapsed ? company.name + ' (Empresa Activa)' : undefined"
            >
                <!-- Avatar / Logo de Empresa -->
                <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20 shadow-xs font-bold text-base">
                    <span>{{ company.name?.charAt(0)?.toUpperCase() ?? 'E' }}</span>
                    <!-- Punto indicador activo -->
                    <span class="absolute -bottom-0.5 -right-0.5 flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-success border-2 border-base-100"></span>
                    </span>
                </div>

                <!-- Detalles de la Empresa (si no está colapsado) -->
                <div v-if="!isCollapsed" class="flex-1 min-w-0">
                    <div class="truncate text-xs font-bold text-base-content leading-tight">
                        {{ company.name }}
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-base-content/60 font-medium">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-success"></span>
                        <span class="truncate">Empresa Activa</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegación de Módulos Operativos -->
        <div class="flex-1 px-2.5 py-3 space-y-4">
            <div
                v-for="section in menu"
                :key="section.key"
                class="space-y-1"
            >
                <!-- Título de Sección -->
                <div
                    v-if="!isCollapsed"
                    class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-base-content/40 flex items-center justify-between"
                >
                    <span>{{ section.title }}</span>
                </div>
                <div
                    v-else
                    class="h-px bg-base-300/60 my-2 mx-1"
                ></div>

                <!-- Enlaces de la Sección -->
                <div class="space-y-0.5">
                    <Link
                        v-for="item in section.items"
                        :key="item.id || item.route"
                        :href="route(item.route)"
                        class="group flex items-center gap-3 rounded-xl px-2.5 py-2 text-xs font-medium transition-all duration-150"
                        :class="[
                            isRouteActive(item.route)
                                ? 'bg-primary/15 text-primary font-semibold shadow-xs'
                                : 'text-base-content/70 hover:bg-base-200/80 hover:text-base-content',
                            isCollapsed ? 'justify-center' : ''
                        ]"
                        :title="isCollapsed ? item.name : undefined"
                    >
                        <!-- Iconos Específicos por Módulo -->
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center transition-transform duration-150 group-hover:scale-110"
                            :class="isRouteActive(item.route) ? 'text-primary' : 'text-base-content/60 group-hover:text-base-content'"
                        >
                            <!-- WhatsApp Instancias -->
                            <svg v-if="item.route === 'whatsapp.instances'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                                <path d="M12 18h.01"/>
                            </svg>

                            <!-- WhatsApp Conversaciones -->
                            <svg v-else-if="item.route === 'whatsapp.conversations'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>

                            <!-- WhatsApp Enviar Mensaje -->
                            <svg v-else-if="item.route === 'whatsapp.send'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m22 2-7 20-4-9-9-4Z"/>
                                <path d="M22 2 11 13"/>
                            </svg>

                            <!-- Calendario de Citas -->
                            <svg v-else-if="item.route === 'whatsapp.calendar'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M8 2v4"/>
                                <path d="M16 2v4"/>
                                <rect width="18" height="18" x="3" y="4" rx="2"/>
                                <path d="M3 10h18"/>
                            </svg>

                            <!-- Inventario de Productos -->
                            <svg v-else-if="item.route === 'products'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m7.5 4.27 9 5.15"/>
                                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                <path d="m3.3 7 8.7 5 8.7-5"/>
                                <path d="M12 22V12"/>
                            </svg>

                            <!-- Servicios -->
                            <svg v-else-if="item.route === 'services'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="20" height="14" x="2" y="7" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>

                            <!-- Empleados -->
                            <svg v-else-if="item.route === 'employees'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>

                            <!-- Icono Genérico -->
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="7" height="7" x="3" y="3" rx="1"/>
                                <rect width="7" height="7" x="14" y="3" rx="1"/>
                                <rect width="7" height="7" x="14" y="14" rx="1"/>
                                <rect width="7" height="7" x="3" y="14" rx="1"/>
                            </svg>
                        </span>

                        <!-- Nombre del Módulo -->
                        <span v-if="!isCollapsed" class="truncate">
                            {{ item.name }}
                        </span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Botón Inferior: Colapsar / Expandir Sidebar -->
        <div class="p-2 border-t border-base-300/80">
            <button
                type="button"
                @click="toggleCollapse"
                class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-xs font-semibold text-base-content/60 transition-colors duration-150 hover:bg-base-200 hover:text-base-content"
                :class="isCollapsed ? 'justify-center' : 'justify-between'"
                :title="isCollapsed ? 'Expandir menú lateral' : 'Colapsar menú lateral'"
            >
                <span v-if="!isCollapsed" class="truncate">Colapsar menú</span>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 transition-transform duration-200"
                    :class="{ 'rotate-180': isCollapsed }"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="m15 18-6-6 6-6"/>
                </svg>
            </button>
        </div>
    </aside>
</template>
