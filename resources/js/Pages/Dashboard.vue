<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const user = computed(() => page.props?.auth?.user ?? {});
const activeCompany = computed(() => page.props?.auth?.active_company ?? null);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return '¡Buenos días';
    if (hour < 19) return '¡Buenas tardes';
    return '¡Buenas noches';
});

function formatDate(dateStr) {
    if (!dateStr) return 'Fecha pendiente';
    try {
        const d = new Date(dateStr);
        return new Intl.DateTimeFormat('es-MX', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(d);
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <Head title="Panel de Control - Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-base-content flex items-center gap-2">
                        <span>{{ greeting }}, {{ user.name?.split(' ')[0] ?? 'Usuario' }}!</span>
                        <span class="text-xl">👋</span>
                    </h1>
                    <p class="text-xs text-base-content/60 mt-0.5">
                        Resumen operativo y métricas en tiempo real de tu espacio de trabajo.
                    </p>
                </div>

                <!-- Insignia de Empresa Activa -->
                <div v-if="activeCompany" class="flex items-center gap-2 rounded-2xl border border-base-300 bg-base-100/80 px-3.5 py-1.5 shadow-xs">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary text-xs font-bold">
                        {{ activeCompany.name?.charAt(0)?.toUpperCase() ?? 'E' }}
                    </span>
                    <div class="text-left">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-base-content/40">Empresa Seleccionada</div>
                        <div class="text-xs font-semibold text-base-content truncate max-w-[160px]">{{ activeCompany.name }}</div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ALERTA SI NO HAY EMPRESA ACTIVA -->
                <div
                    v-if="!activeCompany"
                    class="rounded-2xl border border-warning/30 bg-warning/10 p-5 shadow-xs backdrop-blur-md"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning/20 text-warning">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" x2="12" y1="8" y2="12"/>
                                    <line x1="12" x2="12.01" y1="16" y2="16"/>
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-base-content">No tienes una empresa activa seleccionada</h3>
                                <p class="text-xs text-base-content/70">
                                    Para ver tus métricas operativas de WhatsApp, Inventario y Citas, selecciona una empresa en la barra superior o registra una nueva.
                                </p>
                            </div>
                        </div>
                        <Link
                            v-if="stats.saas?.can_manage"
                            :href="route('companies')"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-warning px-4 py-2 text-xs font-semibold text-warning-content shadow-xs transition hover:brightness-110 shrink-0"
                        >
                            Gestionar Empresas
                        </Link>
                    </div>
                </div>

                <!-- TARJETAS DE MÉTRICAS (KPIs) -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    
                    <!-- KPI 1: WhatsApp CRM -->
                    <div class="group relative overflow-hidden rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs transition-all duration-200 hover:border-primary/40 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">WhatsApp CRM</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-success/10 text-success transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                                    <path d="M12 18h.01"/>
                                </svg>
                            </span>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-extrabold text-base-content">
                                {{ stats.whatsapp?.connected_instances ?? 0 }}
                            </span>
                            <span class="text-xs text-base-content/50">
                                / {{ stats.whatsapp?.total_instances ?? 0 }} conectadas
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-3 border-t border-base-300/60">
                            <span class="text-base-content/60">{{ stats.whatsapp?.total_messages ?? 0 }} mensajes totales</span>
                            <Link :href="route('whatsapp.instances')" class="font-semibold text-primary hover:underline">
                                Gestionar &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- KPI 2: Agenda & Citas -->
                    <div class="group relative overflow-hidden rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs transition-all duration-200 hover:border-primary/40 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Agenda & Citas</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-info/10 text-info transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 2v4"/>
                                    <path d="M16 2v4"/>
                                    <rect width="18" height="18" x="3" y="4" rx="2"/>
                                    <path d="M3 10h18"/>
                                </svg>
                            </span>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-extrabold text-base-content">
                                {{ stats.appointments?.today ?? 0 }}
                            </span>
                            <span class="text-xs text-base-content/50">citas para hoy</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-3 border-t border-base-300/60">
                            <span class="text-base-content/60">{{ stats.appointments?.total ?? 0 }} en total registradas</span>
                            <Link :href="route('whatsapp.calendar')" class="font-semibold text-primary hover:underline">
                                Ver calendario &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- KPI 3: Inventario & Stock -->
                    <div class="group relative overflow-hidden rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs transition-all duration-200 hover:border-primary/40 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Inventario</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-warning/10 text-warning transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m7.5 4.27 9 5.15"/>
                                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                    <path d="m3.3 7 8.7 5 8.7-5"/>
                                    <path d="M12 22V12"/>
                                </svg>
                            </span>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-extrabold text-base-content">
                                {{ stats.inventory?.total_products ?? 0 }}
                            </span>
                            <span class="text-xs text-base-content/50">productos</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-3 border-t border-base-300/60">
                            <span :class="stats.inventory?.low_stock_count > 0 ? 'text-error font-semibold' : 'text-base-content/60'">
                                {{ stats.inventory?.low_stock_count ?? 0 }} en stock bajo
                            </span>
                            <Link :href="route('products')" class="font-semibold text-primary hover:underline">
                                Catálogo &rarr;
                            </Link>
                        </div>
                    </div>

                    <!-- KPI 4: Equipo & Servicios -->
                    <div class="group relative overflow-hidden rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs transition-all duration-200 hover:border-primary/40 hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Servicios & Equipo</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary transition-transform duration-200 group-hover:scale-110">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </span>
                        </div>
                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-2xl font-extrabold text-base-content">
                                {{ stats.services?.total_services ?? 0 }}
                            </span>
                            <span class="text-xs text-base-content/50">servicios activos</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs pt-3 border-t border-base-300/60">
                            <span class="text-base-content/60">{{ stats.team?.total_members ?? 0 }} empleados en equipo</span>
                            <Link :href="route('services')" class="font-semibold text-primary hover:underline">
                                Ver servicios &rarr;
                            </Link>
                        </div>
                    </div>

                </div>

                <!-- ACCESOS RÁPIDOS (QUICK ACTIONS) -->
                <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-base-content/50 mb-3">Accesos Directos</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <Link
                            :href="route('whatsapp.instances')"
                            class="flex flex-col items-center justify-center rounded-xl border border-base-300/80 bg-base-200/40 p-3 text-center transition hover:bg-base-200 hover:border-primary/40 group"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-success/10 text-success mb-2 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                                    <path d="M12 18h.01"/>
                                </svg>
                            </span>
                            <span class="text-xs font-semibold text-base-content">Bots WhatsApp</span>
                        </Link>

                        <Link
                            :href="route('whatsapp.conversations')"
                            class="flex flex-col items-center justify-center rounded-xl border border-base-300/80 bg-base-200/40 p-3 text-center transition hover:bg-base-200 hover:border-primary/40 group"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary mb-2 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            </span>
                            <span class="text-xs font-semibold text-base-content">Conversaciones</span>
                        </Link>

                        <Link
                            :href="route('whatsapp.send')"
                            class="flex flex-col items-center justify-center rounded-xl border border-base-300/80 bg-base-200/40 p-3 text-center transition hover:bg-base-200 hover:border-primary/40 group"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-info/10 text-info mb-2 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m22 2-7 20-4-9-9-4Z"/>
                                    <path d="M22 2 11 13"/>
                                </svg>
                            </span>
                            <span class="text-xs font-semibold text-base-content">Enviar Mensaje</span>
                        </Link>

                        <Link
                            :href="route('whatsapp.calendar')"
                            class="flex flex-col items-center justify-center rounded-xl border border-base-300/80 bg-base-200/40 p-3 text-center transition hover:bg-base-200 hover:border-primary/40 group"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500 mb-2 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 2v4"/>
                                    <path d="M16 2v4"/>
                                    <rect width="18" height="18" x="3" y="4" rx="2"/>
                                    <path d="M3 10h18"/>
                                </svg>
                            </span>
                            <span class="text-xs font-semibold text-base-content">Agenda & Citas</span>
                        </Link>

                        <Link
                            :href="route('products')"
                            class="flex flex-col items-center justify-center rounded-xl border border-base-300/80 bg-base-200/40 p-3 text-center transition hover:bg-base-200 hover:border-primary/40 group"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-warning/10 text-warning mb-2 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m7.5 4.27 9 5.15"/>
                                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                </svg>
                            </span>
                            <span class="text-xs font-semibold text-base-content">Inventario</span>
                        </Link>

                        <Link
                            :href="route('services')"
                            class="flex flex-col items-center justify-center rounded-xl border border-base-300/80 bg-base-200/40 p-3 text-center transition hover:bg-base-200 hover:border-primary/40 group"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-500 mb-2 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="20" height="14" x="2" y="7" rx="2" ry="2"/>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                </svg>
                            </span>
                            <span class="text-xs font-semibold text-base-content">Servicios</span>
                        </Link>
                    </div>
                </div>

                <!-- DOS COLUMNAS: PRÓXIMAS CITAS & ESTADO DE INSTANCIAS / INVENTARIO -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                    <!-- PANEL 1: PRÓXIMAS CITAS PROGRAMADAS -->
                    <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs">
                        <div class="flex items-center justify-between pb-3 border-b border-base-300/60">
                            <div>
                                <h3 class="text-sm font-bold text-base-content">Próximas Citas en Agenda</h3>
                                <p class="text-xs text-base-content/50">Citas programadas a través de WhatsApp o recepcionista</p>
                            </div>
                            <Link :href="route('whatsapp.calendar')" class="text-xs font-semibold text-primary hover:underline">
                                Ver todas
                            </Link>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="appointment in stats.appointments?.upcoming"
                                :key="appointment.id"
                                class="flex items-center justify-between rounded-xl border border-base-300/60 bg-base-200/30 p-3 transition hover:bg-base-200/60"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-info/10 text-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <h4 class="text-xs font-bold text-base-content truncate">{{ appointment.summary }}</h4>
                                        <div class="text-[11px] text-base-content/60 flex items-center gap-2">
                                            <span>📱 {{ appointment.user_phone }}</span>
                                            <span>&bull;</span>
                                            <span>{{ formatDate(appointment.starts_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-success/15 px-2.5 py-0.5 text-[10px] font-semibold text-success uppercase">
                                    {{ appointment.sync_status === 'synced' ? 'Sincronizada' : 'Confirmada' }}
                                </span>
                            </div>

                            <!-- Estado vacío de citas -->
                            <div
                                v-if="!stats.appointments?.upcoming?.length"
                                class="py-8 text-center text-xs text-base-content/50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-8 w-8 text-base-content/20 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="18" height="18" x="3" y="4" rx="2"/>
                                    <path d="M16 2v4M8 2v4M3 10h18"/>
                                </svg>
                                No hay citas próximas pendientes en este momento.
                            </div>
                        </div>
                    </div>

                    <!-- PANEL 2: INSTANCIAS DE WHATSAPP & ALERTAS DE STOCK -->
                    <div class="space-y-6">
                        
                        <!-- Instancias de WhatsApp -->
                        <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs">
                            <div class="flex items-center justify-between pb-3 border-b border-base-300/60">
                                <div>
                                    <h3 class="text-sm font-bold text-base-content">Instancias de WhatsApp</h3>
                                    <p class="text-xs text-base-content/50">Estado de conexión de tus números y bots</p>
                                </div>
                                <Link :href="route('whatsapp.instances')" class="text-xs font-semibold text-primary hover:underline">
                                    Configurar
                                </Link>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                <div
                                    v-for="instance in stats.whatsapp?.instances_list"
                                    :key="instance.id"
                                    class="flex items-center justify-between rounded-xl border border-base-300/70 bg-base-200/30 p-3"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span
                                            class="flex h-2.5 w-2.5 rounded-full"
                                            :class="instance.status === 'connected' ? 'bg-success animate-pulse' : 'bg-error'"
                                        ></span>
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-base-content truncate">{{ instance.instance_name }}</div>
                                            <div class="text-[10px] text-base-content/50 truncate">{{ instance.business_name || 'Sin negocio' }}</div>
                                        </div>
                                    </div>
                                    <span
                                        class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                        :class="instance.status === 'connected' ? 'bg-success/15 text-success' : 'bg-error/15 text-error'"
                                    >
                                        {{ instance.status === 'connected' ? 'Online' : 'Offline' }}
                                    </span>
                                </div>

                                <div
                                    v-if="!stats.whatsapp?.instances_list?.length"
                                    class="col-span-full py-6 text-center text-xs text-base-content/50"
                                >
                                    No hay instancias de WhatsApp registradas aún.
                                </div>
                            </div>
                        </div>

                        <!-- Alertas de Stock Bajo -->
                        <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs">
                            <div class="flex items-center justify-between pb-3 border-b border-base-300/60">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-base-content">Alertas de Inventario</h3>
                                    <span v-if="stats.inventory?.low_stock_count > 0" class="rounded-full bg-error/15 px-2 py-0.5 text-[10px] font-bold text-error">
                                        {{ stats.inventory?.low_stock_count }} en riesgo
                                    </span>
                                </div>
                                <Link :href="route('products')" class="text-xs font-semibold text-primary hover:underline">
                                    Inventario
                                </Link>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div
                                    v-for="prod in stats.inventory?.low_stock_items"
                                    :key="prod.id"
                                    class="flex items-center justify-between rounded-xl border border-base-300/60 bg-error/5 p-2.5"
                                >
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-base-content truncate">{{ prod.name }}</div>
                                        <div class="text-[10px] text-base-content/50 font-mono">SKU: {{ prod.sku || 'N/A' }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-extrabold text-error">{{ prod.stock }} unidades</div>
                                        <div class="text-[10px] text-base-content/50">Mínimo: {{ prod.min_stock }}</div>
                                    </div>
                                </div>

                                <div
                                    v-if="!stats.inventory?.low_stock_items?.length"
                                    class="py-4 text-center text-xs text-success flex items-center justify-center gap-2"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                    <span>Todo el inventario cuenta con stock adecuado.</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- RESUMEN SAAS PARA ADMINISTRADORES DE PLATAFORMA -->
                <div
                    v-if="stats.saas?.can_manage"
                    class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-xs"
                >
                    <div class="flex items-center justify-between pb-3 border-b border-base-300/60">
                        <div>
                            <h3 class="text-sm font-bold text-base-content">Administración SaaS de la Plataforma</h3>
                            <p class="text-xs text-base-content/50">Métricas globales del sistema para administradores</p>
                        </div>
                        <Link :href="route('companies')" class="text-xs font-semibold text-primary hover:underline">
                            Panel SaaS &rarr;
                        </Link>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="rounded-xl border border-base-300/60 bg-base-200/30 p-4">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Total Empresas</span>
                            <div class="text-xl font-extrabold text-base-content mt-1">{{ stats.saas?.total_companies }}</div>
                        </div>

                        <div class="rounded-xl border border-base-300/60 bg-base-200/30 p-4">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Total Usuarios</span>
                            <div class="text-xl font-extrabold text-base-content mt-1">{{ stats.saas?.total_users }}</div>
                        </div>

                        <div class="rounded-xl border border-base-300/60 bg-base-200/30 p-4">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Empresa Actual</span>
                            <div class="text-sm font-bold text-primary truncate mt-1">{{ activeCompany?.name || 'Ninguna' }}</div>
                        </div>

                        <div class="rounded-xl border border-base-300/60 bg-base-200/30 p-4">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Personalización</span>
                            <Link :href="route('components')" class="text-xs font-semibold text-primary hover:underline block mt-1">
                                Temas & Branding &rarr;
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
