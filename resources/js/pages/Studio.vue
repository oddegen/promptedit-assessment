<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { index } from '@/actions/App/Http/Controllers/GenerationController';
import GenerationComposer from '@/components/generations/GenerationComposer.vue';
import GenerationWall from '@/components/generations/GenerationWall.vue';
import { useRetry } from '@/composables/useRetry';
import { isInFlight } from '@/lib/generations';
import { formatPrice } from '@/lib/money';
import type { Generation, GenerationRequest, GenerationSummary } from '@/types';

const props = defineProps<{
    generations: Generation[];
    summary: GenerationSummary;
    prefill: Pick<GenerationRequest, 'prompt' | 'size' | 'quality'> | null;
}>();

const composer = ref<InstanceType<typeof GenerationComposer> | null>(null);

const running = computed(
    () =>
        props.generations.filter((generation) => isInFlight(generation.status))
            .length,
);

const overview = computed(() => {
    const images = `${props.summary.count} ${props.summary.count === 1 ? 'image' : 'images'}`;
    const parts = [images, `${formatPrice(props.summary.spent)} spent`];

    if (running.value > 0) {
        parts.push(`${running.value} running`);
    }

    return parts.join(', ');
});

const { start, stop } = usePoll(
    3000,
    { only: ['generations', 'summary'] },
    { autoStart: false },
);

watch(running, (count) => (count > 0 ? start() : stop()), { immediate: true });

function edit(generation: Generation) {
    composer.value?.fill(generation);
}

const { retrying, retry } = useRetry();
</script>

<template>
    <div
        class="grid gap-6 px-4 pt-2 pb-12 lg:grid-cols-[minmax(0,1fr)_380px] lg:px-6"
    >
        <Head title="Studio" />

        <section class="order-2 min-w-0 lg:order-1" aria-labelledby="images">
            <header class="flex flex-wrap items-baseline gap-x-4 gap-y-1 pb-5">
                <h1 id="images" class="text-2xl font-semibold tracking-tight">
                    Your images
                </h1>
                <p class="text-sm text-muted-foreground tabular-nums">
                    {{ overview }}
                </p>
                <Link
                    :href="index()"
                    class="ml-auto text-sm font-medium text-muted-foreground underline decoration-border underline-offset-4 hover:text-foreground"
                    >Open history</Link
                >
            </header>

            <GenerationWall
                v-if="generations.length"
                :generations="generations"
                editable
                :retrying="retrying"
                @edit="edit"
                @retry="retry($event)"
            />
            <div
                v-else
                class="grid min-h-80 place-content-center justify-items-center gap-2 rounded-[22px] bg-secondary p-10 text-center"
            >
                <p class="text-lg font-medium">Nothing here yet</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Describe an image and it shows up here, with exactly what it
                    cost.
                </p>
            </div>
        </section>

        <GenerationComposer
            ref="composer"
            :prefill="prefill"
            class="order-1 lg:sticky lg:top-[88px] lg:order-2 lg:max-h-[calc(100dvh-104px)] lg:self-start"
        />
    </div>
</template>
