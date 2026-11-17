<script setup lang="ts">
import type { Quality } from '@/types';

defineProps<{
    quality: Quality;
}>();

const levels: Quality[] = ['low', 'medium', 'high'];
</script>

<template>
    <span class="inline-flex h-3.5 items-end gap-[2px]" aria-hidden="true">
        <span
            v-for="(level, index) in levels"
            :key="level"
            class="w-[3px] rounded-[1px] bg-current"
            :class="{ 'opacity-25': index > levels.indexOf(quality) }"
            :style="{ height: `${(index + 1) * 33.4}%` }"
        />
    </span>
</template>
