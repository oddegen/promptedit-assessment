<script setup lang="ts">
import { ref } from 'vue';
import GenerationTile from '@/components/generations/GenerationTile.vue';
import { useMasonry } from '@/composables/useMasonry';
import type { Generation } from '@/types';

const props = defineProps<{
    generations: Generation[];
    editable?: boolean;
    retrying?: string | null;
}>();

const emit = defineEmits<{
    edit: [generation: Generation];
    retry: [generation: Generation];
}>();

const wall = ref<HTMLElement | null>(null);
const columns = useMasonry(() => props.generations, wall);
</script>

<template>
    <div ref="wall" class="flex items-start gap-3.5">
        <div
            v-for="(column, index) in columns"
            :key="index"
            class="flex min-w-0 flex-1 flex-col gap-5"
        >
            <GenerationTile
                v-for="generation in column"
                :key="generation.id"
                :generation="generation"
                :editable="editable"
                :retrying="retrying === generation.id"
                @edit="emit('edit', $event)"
                @retry="emit('retry', $event)"
            />
        </div>
    </div>
</template>
