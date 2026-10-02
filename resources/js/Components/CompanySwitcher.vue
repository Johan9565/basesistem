<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const isOpen = ref(false);

const activeCompany = computed(() => page.props?.auth?.active_company ?? null);
const userCompanies = computed(() => page.props?.auth?.user_companies ?? []);

function toggleDropdown() {
    if (userCompanies.value.length > 1) {
        isOpen.value = !isOpen.value;
    }
}

function closeDropdown() {
    isOpen.value = false;
}

function switchCompany(companyId) {
    if (!companyId || companyId === activeCompany.value?.id) {
        isOpen.value = false;
        return;
    }

    isOpen.value = false;
    router.post(route('companies.switch', companyId), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div v-if="activeCompany" class="relative inline-block text-left" v-click-outside="closeDropdown">
        <button
            type="button"
            @click="toggleDropdown"
            class="group inline-flex items-center gap-2 rounded-lg border border-base-300 bg-base-100/80 px-3 py-1.5 text-xs font-semibold text-base-content/80 shadow-xs backdrop-blur-md transition-all duration-200 hover:border-primary/40 hover:bg-base-200 hover:text-base-content focus:outline-hidden"
            :class="{ 'cursor-default': userCompanies.length <= 1 }"
            :title="userCompanies.length > 1 ? 'Cambiar de empresa' : 'Empresa activa'"
        >
            <!-- Icono Empresa -->
            <span class="flex h-5 w-5 items-center justify-center rounded-md bg-primary/10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
                    <path d="M9 22v-4h6v4"/>
                    <path d="M8 6h.01"/>
                    <path d="M16 6h.01"/>
                    <path d="M12 6h.01"/>
                    <path d="M12 10h.01"/>
                    <path d="M12 14h.01"/>
                    <path d="M16 10h.01"/>
                    <path d="M16 14h.01"/>
                    <path d="M8 10h.01"/>
                    <path d="M8 14h.01"/>
                </svg>
            </span>

            <span class="max-w-[140px] truncate font-medium sm:max-w-[180px]">
                {{ activeCompany.name }}
            </span>

            <!-- Flecha desplegable si hay más de 1 empresa -->
            <svg
                v-if="userCompanies.length > 1"
                xmlns="http://www.w3.org/2000/svg"
                class="h-3.5 w-3.5 text-base-content/50 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <!-- Dropdown Menu -->
        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen && userCompanies.length > 1"
                class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-xl border border-base-300 bg-base-100 p-1.5 shadow-2xl backdrop-blur-xl focus:outline-hidden"
            >
                <div class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-base-content/40">
                    Tus Empresas Disponibles
                </div>

                <div class="space-y-1">
                    <button
                        v-for="company in userCompanies"
                        :key="company.id"
                        type="button"
                        @click="switchCompany(company.id)"
                        class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs transition-colors duration-150"
                        :class="company.is_active
                            ? 'bg-primary/15 font-semibold text-primary'
                            : 'text-base-content/80 hover:bg-base-200 hover:text-base-content'"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <span
                                class="flex h-2 w-2 rounded-full"
                                :class="company.is_active ? 'bg-primary animate-pulse' : 'bg-base-content/20'"
                            ></span>
                            <span class="truncate">{{ company.name }}</span>
                        </div>

                        <span
                            v-if="company.is_active"
                            class="text-[10px] font-bold uppercase tracking-wider text-primary"
                        >
                            Activa
                        </span>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
