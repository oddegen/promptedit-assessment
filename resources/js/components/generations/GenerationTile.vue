<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, Layers, LoaderCircle, Pencil } from '@lucide/vue';
import { useNow, useTimeAgo } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';
import { show } from '@/actions/App/Http/Controllers/GenerationController';
import GeneratingSurface from '@/components/generations/GeneratingSurface.vue';
import {
    aspectRatio,
    failureOf,
    hasExpired,
    isInFlight,
    qualityOf,
    shapeOf,
} from '@/lib/generations';
import { formatPrice, formatPriceRange } from '@/lib/money';
import type { Generation } from '@/types';

const props = defineProps<{
    generation: Generation;
    editable?: boolean;
    retrying?: boolean;
}>();

const emit = defineEmits<{
    edit: [generation: Generation];
    retry: [generation: Generation];
}>();

const now = useNow({ interval: 60000 });
const image = ref<HTMLImageElement | null>(null);
const loaded = ref(false);
const unavailable = ref(false);

onMounted(() => {
    if (image.value?.complete) {
        loaded.value = image.value.naturalWidth > 0;
        unavailable.value = image.value.naturalWidth === 0;
    }
});
const age = useTimeAgo(() => props.generation.created_at);

const inFlight = computed(() => isInFlight(props.generation.status));
const versions = computed(() => props.generation.version_count ?? 1);
const versionLabel = computed(() =>
    props.generation.version_number
        ? `v${props.generation.version_number} of ${versions.value}`
        : String(versions.value),
);
const versionDescription = computed(() =>
    props.generation.version_number
        ? `Version ${props.generation.version_number} of ${versions.value}`
        : `${versions.value} versions`,
);
const expired = computed(
    () =>
        props.generation.status === 'succeeded' &&
        hasExpired(props.generation, now.value),
);
const showsImage = computed(
    () =>
        props.generation.status === 'succeeded' &&
        props.generation.image_url !== null &&
        !expired.value &&
        !unavailable.value,
);
const failure = computed(() =>
    props.generation.status === 'failed'
        ? failureOf(props.generation.error_code)
        : null,
);
const canRetry = computed(
    () =>
        props.editable && (failure.value?.action === 'retry' || expired.value),
);
const opensEditor = computed(() => props.editable && !inFlight.value);

const status = computed(() => {
    if (failure.value) {
        return { label: failure.value.label, note: failure.value.message };
    }

    return expired.value
        ? { label: 'Expired', note: 'This image link has expired.' }
        : { label: 'Unavailable', note: "This image didn't load." };
});

const price = computed(() => {
    const { generation } = props;

    if (inFlight.value) {
        return formatPriceRange(generation.min_price, generation.max_price);
    }

    return generation.price === null ? null : formatPrice(generation.price);
});

const details = computed(() => {
    const { generation } = props;

    if (inFlight.value) {
        return `${shapeOf(generation.size).name}, ${qualityOf(generation.quality).name.toLowerCase()}`;
    }

    return showsImage.value ? age.value : status.value.note;
});
</script>

<template>
    <article class="group">
        <div
            class="relative overflow-hidden rounded-[14px] bg-secondary"
            :style="{ aspectRatio: aspectRatio(generation.size) }"
        >
            <template v-if="showsImage">
                <Link :href="show(generation.id)" class="absolute inset-0">
                    <img
                        :src="generation.image_url ?? undefined"
                        :alt="generation.prompt"
                        ref="image"
                        loading="lazy"
                        class="size-full object-cover transition duration-500 group-hover:scale-[1.02]"
                        :class="
                            loaded
                                ? 'opacity-100 blur-none'
                                : 'opacity-0 blur-md'
                        "
                        @load="loaded = true"
                        @error="unavailable = true"
                    />
                </Link>
                <span
                    v-if="versions > 1"
                    class="absolute top-2.5 left-2.5 inline-flex h-6 items-center gap-1 rounded-full bg-background/95 px-2 text-xs font-medium tabular-nums shadow-sm"
                    :aria-label="versionDescription"
                    ><Layers class="size-3.5" />{{ versionLabel }}</span
                >
                <div
                    class="absolute top-2.5 right-2.5 flex gap-1.5 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
                >
                    <a
                        :href="generation.image_url ?? undefined"
                        target="_blank"
                        rel="noopener"
                        download
                        class="grid size-8 place-items-center rounded-full bg-background/95 text-foreground shadow-sm"
                        aria-label="Download"
                    >
                        <Download class="size-4" />
                    </a>
                    <button
                        v-if="editable"
                        type="button"
                        class="grid size-8 place-items-center rounded-full bg-background/95 text-foreground shadow-sm"
                        aria-label="Edit"
                        @click="emit('edit', generation)"
                    >
                        <Pencil class="size-4" />
                    </button>
                </div>
            </template>

            <template v-else>
                <button
                    v-if="opensEditor"
                    type="button"
                    class="peer absolute inset-0 rounded-[14px] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Edit ${generation.prompt}`"
                    @click="emit('edit', generation)"
                />
                <Link
                    v-else
                    :href="show(generation.id)"
                    class="peer absolute inset-0 rounded-[14px] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="generation.prompt"
                />

                <GeneratingSurface v-if="inFlight" :generation="generation" />

                <div
                    v-else
                    class="pointer-events-none absolute inset-0 flex flex-col gap-2.5 p-3.5 transition-colors group-hover:bg-accent/70"
                >
                    <div class="flex gap-1.5">
                        <span
                            class="inline-flex h-6 items-center rounded-full bg-background px-2.5 text-xs font-medium"
                        >
                            {{ status.label }}
                        </span>
                        <span
                            v-if="versions > 1"
                            class="inline-flex h-6 items-center gap-1 rounded-full bg-background px-2 text-xs font-medium tabular-nums"
                            :aria-label="versionDescription"
                            ><Layers class="size-3.5" />{{ versionLabel }}</span
                        >
                    </div>
                    <p
                        class="min-h-0 flex-1 overflow-hidden [mask-image:linear-gradient(to_bottom,black_calc(100%-2rem),transparent)] text-[19px] leading-[1.22] font-medium text-muted-foreground"
                    >
                        {{ generation.prompt }}
                    </p>
                    <button
                        v-if="canRetry"
                        type="button"
                        :disabled="retrying"
                        class="pointer-events-auto inline-flex h-8 items-center gap-1.5 self-start rounded-full bg-primary px-3.5 text-xs font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-70"
                        @click="emit('retry', generation)"
                    >
                        <LoaderCircle
                            v-if="retrying"
                            class="size-3.5 animate-spin"
                        />
                        {{
                            retrying
                                ? 'Starting…'
                                : expired
                                  ? 'Make again'
                                  : 'Try again'
                        }}
                    </button>
                </div>

                <span
                    v-if="opensEditor"
                    class="pointer-events-none absolute top-2.5 right-2.5 grid size-8 place-items-center rounded-full bg-background text-foreground opacity-0 shadow-sm transition-opacity group-hover:opacity-100 peer-focus-visible:opacity-100"
                    aria-hidden="true"
                >
                    <Pencil class="size-4" />
                </span>
            </template>
        </div>

        <div
            class="flex items-baseline justify-between gap-3 px-0.5 pt-2 text-[13px]"
        >
            <Link
                :href="show(generation.id)"
                class="min-w-0 truncate hover:underline"
                >{{ generation.prompt }}</Link
            >
            <span v-if="price" class="shrink-0 font-medium tabular-nums">{{
                price
            }}</span>
        </div>
        <p
            class="truncate px-0.5 text-xs text-muted-foreground"
            :title="details"
        >
            {{ details }}
        </p>
    </article>
</template>
