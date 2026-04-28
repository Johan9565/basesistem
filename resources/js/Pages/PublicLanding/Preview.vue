<script setup>
const props = defineProps({
    portfolio: { type: Object, required: true },
});
</script>

<template>
    <div class="min-h-[200px] p-6 text-sm opacity-70">
        Preview legacy deshabilitado. La página pública se renderiza con `portfolio.builder_state`.
    </div>
</template>

