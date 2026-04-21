<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
defineProps({
    dividerText: {
        type: String,
        default: '',
    },
});

const DEFAULT_AUTH_SIDE_IMAGE_URL =
    'https://drive.google.com/uc?export=view&id=1KZ_Ub_2lZ0dHbKV0fAIhxVhiQA183RCz';

const page = usePage();
function resolveBrandingSrc(value) {
    if (!value) return null;
    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:')) return value;
    if (value.startsWith('/')) return value;
    return `/storage/${value}`;
}

const authSideImageUrl = computed(
    () =>
        resolveBrandingSrc(page.props?.branding?.auth_side_image_url) ||
        DEFAULT_AUTH_SIDE_IMAGE_URL,
);

const authSideBackgroundStyle = computed(() => {
    const url = String(authSideImageUrl.value).replace(/'/g, "\\'");
    const x = Number(page.props?.branding?.auth_side_image_pos_x ?? 50);
    const y = Number(page.props?.branding?.auth_side_image_pos_y ?? 50);
    const clampedX = Number.isFinite(x) ? Math.min(100, Math.max(0, x)) : 50;
    const clampedY = Number.isFinite(y) ? Math.min(100, Math.max(0, y)) : 50;
    return {
        backgroundImage: `url('${url}')`,
        backgroundPosition: `${clampedX}% ${clampedY}%`,
    };
});
</script>

<template>
    <div
        class="min-h-screen bg-base-200 text-base-content flex justify-center"
    >

        <div
            class="max-w-screen-xl m-0 sm:m-10 bg-base-100 shadow-lg sm:rounded-lg flex justify-center flex-1 border border-base-300"
        >

            <div class="lg:w-1/2 xl:w-5/12 p-6 sm:p-12">
                <div class="flex justify-center"><ApplicationLogo class="block h-20 w-auto fill-current text-base-content" /></div>

                <div>
                    <img
                        src="https://drive.google.com/uc?export=view&id=1MFiKAExRFF0-2YNpAZzIu1Sh52J8r16v"
                        class="mx-auto block max-w-full"
                        alt=""
                    />
                </div>

                <div class="mt-12 flex flex-col items-center">
                    <div class="w-full flex-1 mt-8">
                        <slot name="banner" />

                        <div
                            v-if="dividerText"
                            class="mb-8 mt-2 border-b border-base-300 text-center"
                        >
                            <div
                                class="leading-none px-2 inline-block text-sm font-medium tracking-wide text-base-content/70 bg-base-100 transform translate-y-1/2"
                            >
                                {{ dividerText }}
                            </div>
                        </div>
                        <div class="mx-auto max-w-xs">
                            <slot />

                        </div>
                    </div>
                </div>
            </div>
            <div
                class="hidden flex-1 bg-primary/10 text-center lg:flex dark:bg-primary/20"
            >
                <div
                    class="m-12 xl:m-16 w-full min-h-[280px] bg-cover bg-no-repeat"
                    :style="authSideBackgroundStyle"
                />
            </div>
        </div>
    </div>
</template>
