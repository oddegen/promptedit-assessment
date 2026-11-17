<script setup lang="ts">
import { Link, useForm, useHttp, usePage } from '@inertiajs/vue3';
import { ImageOff, LoaderCircle, Plus, X } from '@lucide/vue';
import NumberFlow from '@number-flow/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { index as creditsIndex } from '@/actions/App/Http/Controllers/CreditController';
import { store } from '@/actions/App/Http/Controllers/GenerationController';
import GenerationEstimateController from '@/actions/App/Http/Controllers/GenerationEstimateController';
import GenerationVersionController from '@/actions/App/Http/Controllers/GenerationVersionController';
import InputError from '@/components/InputError.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { qualities, requestFrom, shapeOf, shapes } from '@/lib/generations';
import { dollars, formatPrice, priceFormat } from '@/lib/money';
import type { Estimate, Generation, GenerationRequest } from '@/types';

const MAX_PROMPT_LENGTH = 4000;
const MAX_REFERENCES = 4;

const props = defineProps<{
    draft?: Generation | null;
    embedded?: boolean;
    prefill?: Pick<GenerationRequest, 'prompt' | 'size' | 'quality'> | null;
}>();

const emit = defineEmits<{ cancel: [] }>();

const form = useForm<GenerationRequest>({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [],
    parent_id: null,
});
const versionOf = ref<Generation | null>(null);

if (props.draft) {
    Object.assign(form, requestFrom(props.draft));
    versionOf.value = props.draft;
}

if (props.prefill) {
    Object.assign(form, props.prefill);
}

const quote = useHttp<GenerationRequest, Estimate>(
    GenerationEstimateController(),
    { ...form.data() },
);
const estimate = ref<Estimate | null>(null);
const pricingFailed = ref(false);
const referenceUrl = ref('');
const checkingReference = ref(false);
const referenceProblem = ref<string | null>(null);
const brokenReferences = ref<string[]>([]);
const promptField = ref<HTMLTextAreaElement | null>(null);
let latestQuote = 0;

watchDebounced(
    () => form.data(),
    async (request) => {
        const current = ++latestQuote;

        Object.assign(quote, request);

        try {
            const response = await quote.submit();

            if (current === latestQuote) {
                estimate.value = response;
                pricingFailed.value = false;
            }
        } catch {
            if (current === latestQuote) {
                pricingFailed.value = true;
            }
        }
    },
    { debounce: 300, deep: true, immediate: true },
);

const hasPrompt = computed(() => form.prompt.trim() !== '');
const page = usePage();

const shortfall = computed(() => {
    const credits = page.props.credits;

    if (!hasPrompt.value || !estimate.value || !credits) {
        return null;
    }

    const needed = estimate.value.maximum;

    if (needed <= credits.available) {
        return null;
    }

    if (credits.held > 0 && needed <= credits.balance) {
        return `${formatPrice(credits.held)} is set aside for ${credits.running} ${credits.running === 1 ? 'image' : 'images'} in progress, so you have ${formatPrice(Math.max(0, credits.available), 'floor')} free. This one needs up to ${formatPrice(needed, 'ceiling')}.`;
    }

    return `This needs up to ${formatPrice(needed, 'ceiling')}, but your balance is ${formatPrice(credits.balance, 'floor')}.`;
});

const blockedMessage = computed(
    () =>
        (form.errors as Partial<Record<string, string>>).credits ??
        shortfall.value,
);
const shape = computed(() => shapeOf(form.size));
const canAddReference = computed(
    () => form.reference_images.length < MAX_REFERENCES,
);

const referenceError = computed(
    () =>
        referenceProblem.value ??
        Object.entries(form.errors).find(([field]) =>
            field.startsWith('reference_images'),
        )?.[1],
);

watch(referenceUrl, () => {
    referenceProblem.value = null;
});

const caption = computed(() => {
    if (!hasPrompt.value) {
        return 'Describe an image to see its price.';
    }

    if (pricingFailed.value || estimate.value === null) {
        return 'Pricing is unavailable right now.';
    }

    return 'Your final price lands in this range.';
});

const priceRange = computed(() => {
    if (estimate.value === null) {
        return null;
    }

    const { minimum, maximum } = estimate.value;

    return formatPrice(minimum) === formatPrice(maximum, 'ceiling')
        ? [maximum]
        : [minimum, maximum];
});

function opensAnImage(url: string): Promise<boolean> {
    return new Promise((resolve) => {
        const image = new Image();
        const timeout = window.setTimeout(() => resolve(false), 10000);

        image.referrerPolicy = 'no-referrer';
        image.onload = () => {
            window.clearTimeout(timeout);
            resolve(true);
        };
        image.onerror = () => {
            window.clearTimeout(timeout);
            resolve(false);
        };
        image.src = url;
    });
}

async function addReference() {
    const url = referenceUrl.value.trim();

    if (url === '' || !canAddReference.value || checkingReference.value) {
        return;
    }

    checkingReference.value = true;
    const isImage = await opensAnImage(url);
    checkingReference.value = false;

    if (!isImage) {
        referenceProblem.value =
            "That link doesn't open an image. Paste a direct image link.";

        return;
    }

    form.reference_images = [...form.reference_images, url];
    referenceUrl.value = '';
}

function removeReference(position: number) {
    form.reference_images = form.reference_images.filter(
        (_, index) => index !== position,
    );
}

function generate() {
    if (form.processing || !hasPrompt.value) {
        return;
    }

    if (props.embedded && props.draft) {
        form.post(GenerationVersionController.url(props.draft.id), {
            replace: true,
        });

        return;
    }

    form.post(store.url(), { preserveScroll: true });
}

function fill(generation: Generation) {
    Object.assign(form, requestFrom(generation));
    versionOf.value = generation;
    form.clearErrors();
    promptField.value?.focus();
}

function makeSeparate() {
    form.parent_id = null;
    versionOf.value = null;
}

defineExpose({ fill });
</script>

<template>
    <aside
        class="flex flex-col overflow-hidden rounded-[22px] bg-secondary"
        aria-labelledby="composer-title"
    >
        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="generate">
            <div class="flex items-center justify-between gap-3 px-5 pt-5">
                <h2 id="composer-title" class="text-[15px] font-semibold">
                    {{ versionOf ? 'New version' : 'New image' }}
                </h2>
                <button
                    v-if="embedded"
                    type="button"
                    class="text-[13px] font-medium text-muted-foreground underline decoration-border underline-offset-4 hover:text-foreground"
                    @click="emit('cancel')"
                >
                    Cancel
                </button>
                <button
                    v-else-if="versionOf"
                    type="button"
                    class="text-[13px] font-medium text-muted-foreground underline decoration-border underline-offset-4 hover:text-foreground"
                    @click="makeSeparate"
                >
                    Make it a separate image
                </button>
            </div>
            <div
                class="grid min-h-0 gap-5 px-5 pt-3 pb-8 lg:overflow-y-auto lg:overscroll-contain lg:[mask-image:linear-gradient(to_bottom,transparent,black_0.75rem,black_calc(100%-2.5rem),transparent)]"
            >
                <div class="grid gap-2">
                    <div
                        class="flex justify-between text-[13px] font-medium text-muted-foreground"
                    >
                        <label for="prompt">Prompt</label>
                        <span
                            class="font-normal tabular-nums"
                            :class="{
                                'text-destructive':
                                    form.prompt.length > MAX_PROMPT_LENGTH,
                            }"
                            >{{ form.prompt.length.toLocaleString() }} of
                            4,000</span
                        >
                    </div>
                    <textarea
                        id="prompt"
                        ref="promptField"
                        v-model="form.prompt"
                        rows="4"
                        placeholder="A lighthouse on a cliff at dawn, oil painting"
                        class="min-h-28 w-full resize-y rounded-2xl bg-background px-3.5 py-3 text-[15px] leading-relaxed outline-none placeholder:text-muted-foreground/70 focus:ring-2 focus:ring-ring"
                        @keydown.meta.enter.prevent="generate"
                        @keydown.ctrl.enter.prevent="generate"
                    />
                    <InputError :message="form.errors.prompt" />
                </div>

                <div class="grid gap-2">
                    <div
                        class="flex justify-between text-[13px] font-medium text-muted-foreground"
                    >
                        <span id="shape-label">Shape</span>
                        <span class="font-normal tabular-nums"
                            >{{ shape.width }} × {{ shape.height }}</span
                        >
                    </div>
                    <div
                        class="grid grid-cols-3 gap-2"
                        role="radiogroup"
                        aria-labelledby="shape-label"
                    >
                        <button
                            v-for="option in shapes"
                            :key="option.size"
                            type="button"
                            role="radio"
                            :aria-checked="form.size === option.size"
                            class="grid h-20 content-end justify-items-center gap-2 rounded-2xl pb-2.5 text-[13px] font-medium transition"
                            :class="
                                form.size === option.size
                                    ? 'bg-background text-foreground ring-[1.5px] ring-primary'
                                    : 'bg-accent/60 text-muted-foreground hover:bg-accent'
                            "
                            @click="form.size = option.size"
                        >
                            <span
                                class="block rounded-[3px] transition-colors"
                                :class="
                                    form.size === option.size
                                        ? 'bg-primary'
                                        : 'bg-muted-foreground/30'
                                "
                                :style="{
                                    width: `${option.width / 48}px`,
                                    height: `${option.height / 48}px`,
                                }"
                            />
                            {{ option.name }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <div
                        class="flex justify-between text-[13px] font-medium text-muted-foreground"
                    >
                        <span id="quality-label">Quality</span>
                        <span class="font-normal">price for this shape</span>
                    </div>
                    <div
                        class="grid gap-1.5 rounded-2xl bg-accent/60 p-1"
                        role="radiogroup"
                        aria-labelledby="quality-label"
                    >
                        <button
                            v-for="tier in qualities"
                            :key="tier.quality"
                            type="button"
                            role="radio"
                            :aria-checked="form.quality === tier.quality"
                            class="grid grid-cols-[1fr_auto] items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
                            :class="
                                form.quality === tier.quality
                                    ? 'bg-background ring-[1.5px] ring-primary'
                                    : 'hover:bg-background/60'
                            "
                            @click="form.quality = tier.quality"
                        >
                            <span>
                                <span class="block text-sm font-medium">{{
                                    tier.name
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ tier.hint }}</span
                                >
                            </span>
                            <span class="text-sm tabular-nums">
                                <span class="text-xs text-muted-foreground"
                                    >up to
                                </span>
                                <span class="font-medium">{{
                                    estimate
                                        ? formatPrice(
                                              estimate.tiers[tier.quality],
                                              'ceiling',
                                          )
                                        : '—'
                                }}</span>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <div
                        class="flex justify-between text-[13px] font-medium text-muted-foreground"
                    >
                        <label for="reference-url">Reference images</label>
                        <span class="font-normal tabular-nums"
                            >{{ form.reference_images.length }} of 4</span
                        >
                    </div>
                    <ul
                        v-if="form.reference_images.length"
                        class="grid gap-1.5"
                    >
                        <li
                            v-for="(url, index) in form.reference_images"
                            :key="`${index}-${url}`"
                            class="grid grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-2.5 rounded-2xl bg-background p-1.5"
                        >
                            <span
                                v-if="brokenReferences.includes(url)"
                                class="grid size-10 place-items-center rounded-[10px] bg-secondary text-muted-foreground"
                            >
                                <ImageOff class="size-4" />
                            </span>
                            <img
                                v-else
                                :src="url"
                                alt=""
                                referrerpolicy="no-referrer"
                                class="size-10 rounded-[10px] bg-secondary object-cover"
                                @error="brokenReferences.push(url)"
                            />
                            <span class="truncate text-[13px]">{{ url }}</span>
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-full text-muted-foreground hover:bg-secondary"
                                aria-label="Remove reference image"
                                @click="removeReference(index)"
                            >
                                <X class="size-4" />
                            </button>
                        </li>
                    </ul>
                    <div v-if="canAddReference" class="flex gap-2">
                        <input
                            id="reference-url"
                            v-model="referenceUrl"
                            type="url"
                            inputmode="url"
                            placeholder="Paste an image link"
                            class="h-10 min-w-0 flex-1 rounded-2xl bg-background px-3.5 text-sm outline-none placeholder:text-muted-foreground/70 focus:ring-2 focus:ring-ring"
                            @keydown.enter.prevent="addReference"
                        />
                        <button
                            type="button"
                            :disabled="checkingReference"
                            class="grid size-10 place-items-center rounded-full bg-background ring-1 ring-border hover:bg-accent disabled:opacity-70"
                            :aria-label="
                                checkingReference
                                    ? 'Checking the link'
                                    : 'Add reference image'
                            "
                            @click="addReference"
                        >
                            <LoaderCircle
                                v-if="checkingReference"
                                class="size-4 animate-spin"
                            />
                            <Plus v-else class="size-4" />
                        </button>
                    </div>
                    <InputError :message="referenceError" />
                </div>
            </div>

            <div
                class="mt-auto grid shrink-0 gap-3.5 border-t border-border p-5"
            >
                <div aria-live="polite">
                    <p
                        class="text-[40px] leading-none font-semibold tracking-tight whitespace-nowrap tabular-nums transition-opacity"
                        :class="{ 'opacity-50': quote.processing }"
                    >
                        <template v-if="hasPrompt && priceRange">
                            <NumberFlow
                                v-if="priceRange.length === 2"
                                :value="dollars(priceRange[0])"
                                :format="priceFormat()"
                                locales="en-US"
                            /><span v-if="priceRange.length === 2">–</span
                            ><NumberFlow
                                :value="
                                    dollars(priceRange[priceRange.length - 1])
                                "
                                :format="priceFormat('ceiling')"
                                locales="en-US"
                            />
                        </template>
                        <span v-else class="text-muted-foreground/50">$—</span>
                    </p>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-[13px] text-muted-foreground">
                        {{ caption }}
                    </p>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="shrink-0 text-[13px] font-medium text-muted-foreground underline decoration-border underline-offset-4 hover:text-foreground"
                                >
                                    How it's priced
                                </button>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-64 text-pretty">
                                Prices follow what the model uses for each
                                image, plus a
                                {{ estimate?.fee_percent ?? 25 }}% service fee.
                                You only pay once your image is ready.
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
                <p
                    v-if="blockedMessage"
                    class="rounded-2xl bg-background px-4 py-3 text-[13px] leading-relaxed"
                    role="status"
                >
                    {{ blockedMessage }}
                    <Link
                        :href="creditsIndex()"
                        class="font-medium underline underline-offset-4"
                        >Add credits</Link
                    >
                </p>
                <button
                    type="submit"
                    :disabled="
                        form.processing || !hasPrompt || shortfall !== null
                    "
                    class="flex h-12 items-center justify-between rounded-full bg-primary pr-2 pl-5 text-[15px] font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                >
                    <span class="inline-flex items-center gap-2">
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                        />
                        {{ form.processing ? 'Starting…' : 'Generate' }}
                    </span>
                    <kbd
                        class="rounded-full bg-primary-foreground/15 px-2 py-1 font-sans text-[11px]"
                        >⌘ ↵</kbd
                    >
                </button>
                <p class="text-xs text-muted-foreground">
                    You only pay for images that come out.
                </p>
            </div>
        </form>
    </aside>
</template>
