<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 16 16"
        fill="currentColor"
        :class="className"
        v-bind="$attrs"
    >
        <rect width="7" height="7" rx="2" />
        <rect x="9" width="7" height="7" rx="2" />
        <rect y="9" width="7" height="7" rx="2" />
        <rect x="9" y="9" width="7" height="7" rx="2" opacity="0.35" />
    </svg>
</template>
