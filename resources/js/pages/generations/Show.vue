<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Download, LoaderCircle, Pencil } from '@lucide/vue';
import { useNow, useTimeAgo } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/GenerationController';
import GenerationVersionController from '@/actions/App/Http/Controllers/GenerationVersionController';
import GenerationComposer from '@/components/generations/GenerationComposer.vue';
import GeneratingSurface from '@/components/generations/GeneratingSurface.vue';
import {
    aspectRatio,
    failureOf,
    hasExpired,
    isInFlight,
    qualityOf,
    requestFrom,
    secondsWaiting,
    shapeOf,
    waitingNote,
} from '@/lib/generations';
import { formatPrice, formatPriceRange } from '@/lib/money';
import type { Generation, Receipt } from '@/types';

const props = defineProps<{
    generation: Generation;
    receipt: Receipt | null;
    versions: Generation[];
    spent: number;
}>();

const now = useNow({ interval: 1000 });
const editing = ref(false);
const retrying = ref(false);

const ratio = computed(() => aspectRatio(props.generation.size));
const shape = computed(() => shapeOf(props.generation.size));
const age = useTimeAgo(() => props.generation.created_at);
const inFlight = computed(() => isInFlight(props.generation.status));
const expired = computed(() => hasExpired(props.generation));
const showsImage = computed(
    () => props.generation.image_url !== null && !expired.value,
);
const failure = computed(() =>
    props.generation.status === 'failed'
        ? failureOf(props.generation.error_code)
        : null,
);
const canRetry = computed(
    () => failure.value?.action === 'retry' || expired.value,
);

const { start, stop } = usePoll(
    3000,
    { only: ['generation', 'receipt', 'versions', 'spent'] },
    { autoStart: false },
);

watch(inFlight, (running) => (running ? start() : stop()), {
    immediate: true,
});

watch(
    () => props.generation.id,
    () => {
        editing.value = false;
    },
);

const settings = computed(() => {
    const references = props.generation.reference_images.length;

    return [
        `${shape.value.name}, ${shape.value.width} × ${shape.value.height}`,
        `${qualityOf(props.generation.quality).name} quality`,
        ...(references > 0
            ? [
                  `${references} reference ${references === 1 ? 'image' : 'images'}`,
              ]
            : []),
    ];
});

const status = computed(() => {
    const { generation } = props;

    if (inFlight.value) {
        const note = waitingNote(
            generation,
            secondsWaiting(generation, now.value),
        );

        return note ? `${note}.` : 'Usually ready in under 30 seconds.';
    }

    if (failure.value) {
        return failure.value.message;
    }

    if (expired.value) {
        return 'This image link has expired.';
    }

    if (generation.started_at && generation.completed_at) {
        const seconds = Math.round(
            (new Date(generation.completed_at).getTime() -
                new Date(generation.started_at).getTime()) /
                1000,
        );

        return `Ready in ${seconds} seconds, ${age.value}.`;
    }

    return `Ready ${age.value}.`;
});

const estimate = computed(() =>
    formatPriceRange(props.generation.min_price, props.generation.max_price),
);

const tokens = computed(() =>
    props.receipt === null
        ? []
        : [
              ['Prompt text', props.receipt.input_text_tokens],
              ['Reference images', props.receipt.input_image_tokens],
              ['Image output', props.receipt.output_tokens],
              ['Reasoning', props.receipt.reasoning_tokens],
          ],
);

const feePercent = computed(() =>
    props.receipt === null || props.receipt.cost === 0
        ? 0
        : Math.round((props.receipt.fee / props.receipt.cost) * 100),
);

const timeline = computed(() => {
    const { generation } = props;
    const entries: [string, string | null][] = [
        ['Sent', generation.created_at],
        ['Started', generation.started_at],
        [
            generation.status === 'failed' ? 'Failed' : 'Ready',
            generation.completed_at,
        ],
        ['Image link expires', generation.image_url_expires_at],
    ];

    return entries.filter(
        (entry): entry is [string, string] => entry[1] !== null,
    );
});

function time(value: string) {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function goBack() {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(index());
}

function retry() {
    if (retrying.value) {
        return;
    }

    retrying.value = true;

    router.post(
        GenerationVersionController.url(props.generation.id),
        requestFrom(props.generation),
        {
            replace: true,
            onFinish: () => {
                retrying.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="grid gap-3 px-3 pb-6 lg:h-[calc(100dvh-96px)] lg:grid-cols-[minmax(0,1fr)_400px] lg:grid-rows-[minmax(0,1fr)] lg:px-6"
    >
        <Head :title="generation.prompt" />

        <section
            class="relative flex h-[70svh] flex-col rounded-[22px] bg-secondary p-4 lg:h-auto lg:p-6"
            aria-label="Image"
        >
            <button
                type="button"
                class="absolute top-4 left-4 z-10 grid size-9 place-items-center rounded-full bg-background shadow-sm"
                aria-label="Back"
                @click="goBack"
            >
                <ArrowLeft class="size-4" />
            </button>

            <div class="[container-type:size] min-h-0 flex-1 px-8 py-10">
                <div
                    class="relative mx-auto overflow-hidden rounded-[14px] bg-background shadow-sm"
                    :style="{
                        aspectRatio: ratio,
                        width: `min(100cqw, calc(100cqh * ${ratio}))`,
                        top: '50%',
                        transform: 'translateY(-50%)',
                    }"
                >
                    <img
                        v-if="showsImage"
                        :src="generation.image_url ?? undefined"
                        :alt="generation.prompt"
                        class="size-full object-cover"
                    />
                    <GeneratingSurface
                        v-else-if="inFlight"
                        :generation="generation"
                    />
                    <div
                        v-else
                        class="grid size-full place-content-center gap-2 p-8 text-center"
                    >
                        <p
                            class="line-clamp-4 text-xl leading-snug font-medium text-muted-foreground"
                        >
                            {{ generation.prompt }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ status }}
                        </p>
                    </div>
                </div>
            </div>

            <nav
                v-if="versions.length > 1"
                class="flex items-end justify-center gap-2.5"
                aria-label="Versions"
            >
                <Link
                    v-for="(version, position) in versions"
                    :key="version.id"
                    :href="show(version.id)"
                    replace
                    preserve-scroll
                    class="grid justify-items-center gap-1 text-[11px] font-medium tabular-nums"
                    :aria-current="
                        version.id === generation.id ? 'page' : undefined
                    "
                >
                    <span
                        class="relative grid size-12 place-items-center overflow-hidden rounded-[10px] bg-background text-muted-foreground transition"
                        :class="
                            version.id === generation.id
                                ? 'ring-2 ring-primary ring-offset-2 ring-offset-secondary'
                                : 'opacity-70 hover:opacity-100'
                        "
                    >
                        <img
                            v-if="version.image_url && !hasExpired(version)"
                            :src="version.image_url"
                            alt=""
                            class="size-full object-cover"
                        />
                        <span
                            v-else-if="isInFlight(version.status)"
                            class="size-1.5 animate-pulse rounded-full bg-primary"
                        />
                        <span v-else class="text-[10px]">{{
                            version.status === 'failed' ? 'Failed' : 'Expired'
                        }}</span>
                    </span>
                    <span
                        :class="
                            version.id === generation.id
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                        >v{{ position + 1 }}</span
                    >
                </Link>
            </nav>
        </section>

        <GenerationComposer
            v-if="editing"
            embedded
            :draft="generation"
            class="lg:min-h-0"
            @cancel="editing = false"
        />

        <aside
            v-else
            class="flex flex-col overflow-hidden rounded-[22px] bg-secondary lg:min-h-0"
        >
            <div
                class="grid min-h-0 flex-1 content-start gap-5 p-6 lg:overflow-y-auto lg:overscroll-contain"
            >
                <div class="grid gap-3">
                    <h1 class="line-clamp-4 text-lg leading-snug font-medium">
                        {{ generation.prompt }}
                    </h1>
                    <ul class="flex flex-wrap gap-1.5">
                        <li
                            v-for="setting in settings"
                            :key="setting"
                            class="rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground tabular-nums"
                        >
                            {{ setting }}
                        </li>
                    </ul>
                    <p class="text-sm text-muted-foreground">{{ status }}</p>
                </div>

                <section
                    v-if="receipt && generation.price !== null"
                    class="grid gap-4 rounded-2xl bg-background p-4"
                    aria-label="Receipt"
                >
                    <div class="flex items-end justify-between gap-3">
                        <p
                            class="text-4xl font-semibold tracking-tight tabular-nums"
                        >
                            {{ formatPrice(generation.price) }}
                        </p>
                        <p
                            class="text-right text-xs text-muted-foreground tabular-nums"
                        >
                            estimate {{ estimate }}
                        </p>
                    </div>
                    <table class="w-full text-[13px] tabular-nums">
                        <caption class="sr-only">
                            How the price was worked out
                        </caption>
                        <tbody>
                            <tr
                                v-for="[label, count] in tokens"
                                :key="label"
                                class="border-t border-border"
                            >
                                <th
                                    scope="row"
                                    class="py-1.5 text-left font-normal text-muted-foreground"
                                >
                                    {{ label }}
                                </th>
                                <td class="py-1.5 text-right">
                                    {{ count.toLocaleString() }} tokens
                                </td>
                            </tr>
                            <tr class="border-t border-border">
                                <th
                                    scope="row"
                                    class="py-1.5 text-left font-normal text-muted-foreground"
                                >
                                    Model cost
                                </th>
                                <td class="py-1.5 text-right">
                                    {{ formatPrice(receipt.cost, 'exact') }}
                                </td>
                            </tr>
                            <tr class="border-t border-border">
                                <th
                                    scope="row"
                                    class="py-1.5 text-left font-normal text-muted-foreground"
                                >
                                    Service fee, {{ feePercent }}%
                                </th>
                                <td class="py-1.5 text-right">
                                    {{ formatPrice(receipt.fee, 'exact') }}
                                </td>
                            </tr>
                            <tr
                                class="border-t border-dashed border-foreground/40"
                            >
                                <th
                                    scope="row"
                                    class="py-1.5 text-left font-medium"
                                >
                                    You paid
                                </th>
                                <td class="py-1.5 text-right font-medium">
                                    {{ formatPrice(generation.price, 'exact') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div
                    v-else-if="inFlight"
                    class="flex items-baseline justify-between gap-3 rounded-2xl bg-background p-4 text-sm tabular-nums"
                >
                    <span class="text-muted-foreground">Estimate</span>
                    <span class="font-medium">{{ estimate }}</span>
                </div>

                <dl
                    class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-[13px] tabular-nums"
                >
                    <template v-for="[label, value] in timeline" :key="label">
                        <dt class="text-muted-foreground">{{ label }}</dt>
                        <dd class="text-right">{{ time(value) }}</dd>
                    </template>
                    <template v-if="versions.length > 1">
                        <dt class="text-muted-foreground">All versions</dt>
                        <dd class="text-right">{{ formatPrice(spent) }}</dd>
                    </template>
                </dl>
            </div>

            <div
                v-if="!inFlight"
                class="flex flex-wrap gap-2 border-t border-border p-5"
            >
                <a
                    v-if="showsImage"
                    :href="generation.image_url ?? undefined"
                    target="_blank"
                    rel="noopener"
                    download
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <Download class="size-4" />
                    Download
                </a>
                <button
                    v-if="canRetry"
                    type="button"
                    :disabled="retrying"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-70"
                    @click="retry"
                >
                    <LoaderCircle v-if="retrying" class="size-4 animate-spin" />
                    {{
                        retrying
                            ? 'Starting…'
                            : expired
                              ? 'Make again'
                              : 'Try again'
                    }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-full bg-background px-5 text-sm font-medium ring-1 ring-border hover:bg-accent"
                    @click="editing = true"
                >
                    <Pencil class="size-4" />
                    Edit
                </button>
            </div>
        </aside>
    </div>
</template>
