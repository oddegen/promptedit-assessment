<script setup lang="ts">
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/GenerationController';
import GenerationWall from '@/components/generations/GenerationWall.vue';
import type { Generation } from '@/types';

type Filter = 'all' | 'running' | 'failed';

defineProps<{
    generations: { data: Generation[] };
    filter: Filter;
}>();

const filters: { value: Filter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'running', label: 'In progress' },
    { value: 'failed', label: "Didn't work" },
];
</script>

<template>
    <div class="px-4 pt-2 pb-12 lg:px-6">
        <Head title="History" />

        <header class="flex flex-wrap items-center gap-4 pb-5">
            <h1 class="text-2xl font-semibold tracking-tight">History</h1>
            <nav class="flex gap-1 rounded-full bg-secondary p-1">
                <Link
                    v-for="option in filters"
                    :key="option.value"
                    :href="
                        index({
                            query:
                                option.value === 'all'
                                    ? {}
                                    : { filter: option.value },
                        })
                    "
                    class="rounded-full px-3.5 py-1.5 text-[13px] font-medium transition"
                    :class="
                        filter === option.value
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    >{{ option.label }}</Link
                >
            </nav>
        </header>

        <InfiniteScroll v-if="generations.data.length" data="generations">
            <GenerationWall :generations="generations.data" />
        </InfiniteScroll>
        <div
            v-else
            class="grid min-h-80 place-content-center justify-items-center gap-2 rounded-[22px] bg-secondary p-10 text-center"
        >
            <p class="text-lg font-medium">Nothing to show</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                {{
                    filter === 'all'
                        ? 'Images you make in the studio show up here.'
                        : 'No images match this filter right now.'
                }}
            </p>
        </div>
    </div>
</template>
