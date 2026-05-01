<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    checked: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['change']);

const isChecked = ref(props.checked);

watch(
    () => props.checked,
    (newVal) => {
        isChecked.value = newVal;
    },
);

function handleChange(event) {
    isChecked.value = event.target.checked;
    emit('change', event);
}
</script>

<template>
    <input
        type="checkbox"
        class="toggle toggle-primary toggle-sm"
        :checked="isChecked"
        @change="handleChange"
    />
</template>
