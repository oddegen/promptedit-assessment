<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    Disc3,
    LoaderCircle,
    Mountain,
    Package,
    Sofa,
    Ticket,
    UserRound,
    UtensilsCrossed,
    X,
} from '@lucide/vue';
import { useResizeObserver } from '@vueuse/core';
import { computed, nextTick, ref } from 'vue';
import type { Component } from 'vue';
import DraftController from '@/actions/App/Http/Controllers/DraftController';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import QualityBars from '@/components/generations/QualityBars.vue';
import InputError from '@/components/InputError.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    aspectRatio,
    qualities,
    qualityOf,
    shapeOf,
    shapes,
} from '@/lib/generations';
import { formatPrice } from '@/lib/money';
import { home, login, register, studio } from '@/routes';
import { store as registerStore } from '@/routes/register';
import type { Quality, Size } from '@/types';

type PriceTable = Record<Quality, Record<Size, { min: number; max: number }>>;

type Tile = {
    seed: string;
    size: Size;
    quality: Quality;
    x: number;
    y: number;
    width: number;
    depth: number;
    rotate: number;
    duration: number;
    tagged: boolean;
};

const props = defineProps<{
    prices: PriceTable | null;
    bonus: number;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const appName = computed(() => page.props.name);
const year = new Date().getFullYear();

const prompt = ref('');
const size = ref<Size>('1536x1024');
const quality = ref<Quality>('medium');
const promptField = ref<HTMLTextAreaElement | null>(null);
const saving = ref(false);
const wallOpen = ref(false);

const account = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const upTo = computed(() =>
    props.prices
        ? formatPrice(props.prices[quality.value][size.value].max, 'ceiling')
        : null,
);

const welcomeCredit = computed(() => formatPrice(props.bonus));

const draftSummary = computed(
    () =>
        `${shapeOf(size.value).name}, ${qualityOf(quality.value).name.toLowerCase()} quality${upTo.value ? `, up to ${upTo.value}` : ''}`,
);

const seeds = [
    'vase-2',
    'terrace-9',
    'plum-4',
    'harbour-3',
    'forest-1',
    'court-5',
    'tailor-8',
    'chair-6',
    'dunes-2',
    'stair-3',
    'glass-1',
    'neon-7',
    'atlas-4',
    'linen-2',
    'orbit-9',
    'reef-3',
    'fold-8',
    'salt-6',
    'moss-2',
    'tide-5',
    'kite-1',
    'ember-4',
    'loom-7',
    'pier-3',
];

const ideas: {
    label: string;
    icon: Component;
    prompt: string;
    size: Size;
}[] = [
    {
        label: 'Product shot',
        icon: Package,
        prompt: 'A ceramic mug on a linen tablecloth, soft morning light, studio photo',
        size: '1024x1024',
    },
    {
        label: 'Poster',
        icon: Ticket,
        prompt: 'Retro travel poster of a seaside town, bold flat colours',
        size: '1024x1536',
    },
    {
        label: 'Album cover',
        icon: Disc3,
        prompt: 'Album cover, a single plum on crumpled linen, minimal',
        size: '1024x1024',
    },
    {
        label: 'Interior',
        icon: Sofa,
        prompt: 'Sunlit Scandinavian living room with oak floors, wide angle',
        size: '1536x1024',
    },
    {
        label: 'Landscape',
        icon: Mountain,
        prompt: 'Misty pine valley at dawn, cinematic',
        size: '1536x1024',
    },
    {
        label: 'Portrait',
        icon: UserRound,
        prompt: 'Portrait of an old fisherman, Rembrandt lighting',
        size: '1024x1536',
    },
    {
        label: 'Food',
        icon: UtensilsCrossed,
        prompt: 'Overhead shot of a rustic breakfast table, natural light',
        size: '1536x1024',
    },
];

const hero = ref<HTMLElement | null>(null);
const heroContent = ref<HTMLElement | null>(null);
const tiles = ref<Tile[]>([]);

function noise(n: number) {
    const x = Math.sin(n * 9301 + 49297) * 233280;

    return x - Math.floor(x);
}

function overlaps(
    a: { x: number; y: number; w: number; h: number },
    b: { x: number; y: number; w: number; h: number },
    gap: number,
) {
    return (
        a.x < b.x + b.w + gap &&
        a.x + a.w + gap > b.x &&
        a.y < b.y + b.h + gap &&
        a.y + a.h + gap > b.y
    );
}

function arrange() {
    if (!hero.value || !heroContent.value) {
        return;
    }

    const area = hero.value.getBoundingClientRect();
    const block = heroContent.value.getBoundingClientRect();
    const keepOut = {
        x: block.left - area.left - 16,
        y: block.top - area.top - 16,
        w: block.width + 32,
        h: block.height + 32,
    };
    const scale = Math.min(1.1, Math.max(0.55, area.width / 1440));
    const placed: (Tile & { h: number })[] = [];

    seeds.forEach((seed, index) => {
        const depth = noise(index * 11);
        const size = shapes[index % 3].size;
        const ratio = aspectRatio(size);
        const longest = (100 + depth * 150) * scale;
        const width = ratio >= 1 ? longest : longest * ratio;
        const height = width / ratio;

        for (let attempt = 0; attempt < 80; attempt++) {
            const candidate = {
                x:
                    noise(index * 7 + attempt * 13) *
                        (area.width + width * 0.5) -
                    width * 0.25,
                y:
                    28 +
                    noise(index * 17 + attempt * 29) *
                        (area.height - height - 44),
                w: width,
                h: height,
            };

            if (
                overlaps(candidate, keepOut, 0) ||
                placed.some((tile) =>
                    overlaps(
                        candidate,
                        { x: tile.x, y: tile.y, w: tile.width, h: tile.h },
                        14,
                    ),
                )
            ) {
                continue;
            }

            placed.push({
                seed,
                size,
                quality: qualities[index % 3].quality,
                x: candidate.x,
                y: candidate.y,
                width,
                h: height,
                depth,
                rotate: (noise(index * 5) - 0.5) * 8,
                duration: 16 + noise(index * 3) * 12,
                tagged: depth > 0.55 && index % 3 === 0,
            });

            break;
        }
    });

    tiles.value = placed;
}

useResizeObserver(hero, () => requestAnimationFrame(arrange));
useResizeObserver(heroContent, () => requestAnimationFrame(arrange));

const steps = [
    {
        title: 'Know the price upfront',
        body: 'Every idea comes with a price range before you hit generate. No guesswork, no surprise bills.',
    },
    {
        title: 'Watch it come to life',
        body: 'We reserve the top of the range while your image renders, so you never go over.',
    },
    {
        title: 'Pay for what you keep',
        body: "You're charged the real cost once it's done. If an image doesn't make it, it's on us.",
    },
];

function imageUrl(tile: Tile) {
    const { width, height } = shapeOf(tile.size);

    return `https://picsum.photos/seed/${tile.seed}/${width / 4}/${height / 4}`;
}

function tilePrice(tile: Tile) {
    return props.prices
        ? formatPrice(props.prices[tile.quality][tile.size].min)
        : null;
}

function generate() {
    if (prompt.value.trim() === '') {
        promptField.value?.focus();

        return;
    }

    if (saving.value) {
        return;
    }

    saving.value = true;

    router.post(
        DraftController.url(),
        { prompt: prompt.value, size: size.value, quality: quality.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (!user.value) {
                    wallOpen.value = true;
                }
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function useIdea(idea: (typeof ideas)[number]) {
    prompt.value = idea.prompt;
    size.value = idea.size;
    promptField.value?.focus();
}

function createAccount() {
    account.post(registerStore.url(), {
        onFinish: () => account.reset('password', 'password_confirmation'),
    });
}

async function closeWall() {
    wallOpen.value = false;
    await nextTick();
    promptField.value?.focus();
}
</script>

<template>
    <div
        class="relative min-h-svh overflow-x-clip bg-[radial-gradient(120%_90%_at_50%_30%,#ffffff_0%,#f4f6fa_55%,#e9eef5_100%)] text-foreground dark:bg-background dark:bg-none"
    >
        <Head title="Images from a sentence" />

        <header
            class="relative z-20 mx-auto flex h-20 max-w-[1440px] items-center gap-8 px-5 lg:px-10"
        >
            <Link :href="home()" class="flex items-center gap-2.5">
                <AppLogoIcon class="size-5 text-foreground" />
                <span class="text-[17px] font-semibold tracking-tight">{{
                    appName
                }}</span>
            </Link>
            <nav
                class="hidden gap-6 text-sm font-medium text-muted-foreground md:flex"
                aria-label="Sections"
            >
                <a href="#pricing" class="hover:text-foreground">Pricing</a>
                <a href="#how-it-works" class="hover:text-foreground"
                    >How it works</a
                >
            </nav>
            <div class="ml-auto flex items-center gap-5 text-sm font-medium">
                <Link
                    v-if="user"
                    :href="studio()"
                    class="inline-flex h-11 items-center rounded-full bg-primary px-5 text-primary-foreground hover:bg-primary/90"
                    >Open the studio</Link
                >
                <template v-else>
                    <Link :href="login()" class="hover:underline">Log in</Link>
                    <Link
                        :href="register()"
                        class="inline-flex h-11 items-center rounded-full bg-primary px-5 text-primary-foreground hover:bg-primary/90"
                        >Sign up</Link
                    >
                </template>
            </div>
        </header>

        <main class="relative z-10">
            <section
                ref="hero"
                class="relative grid min-h-[calc(100svh-5rem)] grid-cols-1 place-items-center px-5 pt-6 pb-20"
            >
                <div
                    class="pointer-events-none absolute inset-0 -z-10"
                    aria-hidden="true"
                >
                    <figure
                        v-for="tile in tiles"
                        :key="tile.seed"
                        class="absolute animate-drift motion-reduce:animate-none"
                        :style="{
                            left: `${tile.x}px`,
                            top: `${tile.y}px`,
                            width: `${tile.width}px`,
                            zIndex: Math.round(tile.depth * 10),
                            opacity: 0.6 + tile.depth * 0.4,
                            '--drift-rotate': `${tile.rotate}deg`,
                            '--drift-duration': `${tile.duration}s`,
                        }"
                    >
                        <img
                            :src="imageUrl(tile)"
                            alt=""
                            class="w-full rounded-[10px] object-cover shadow-[0_1px_1px_rgb(0_0_0/0.06),0_14px_30px_-12px_rgb(0_0_0/0.28)]"
                            :style="{ aspectRatio: aspectRatio(tile.size) }"
                        />
                        <figcaption
                            v-if="tile.tagged && tilePrice(tile)"
                            class="absolute bottom-1.5 left-1.5 rounded-full bg-white/90 px-2 py-0.5 text-[10.5px] font-semibold text-[#0b1b33] tabular-nums"
                        >
                            {{ tilePrice(tile) }}
                        </figcaption>
                    </figure>
                </div>

                <div
                    ref="heroContent"
                    class="grid w-full max-w-[760px] grid-cols-1 justify-items-center text-center"
                >
                    <h1
                        class="max-w-[20ch] text-[clamp(44px,6.6vw,88px)] leading-[0.96] font-semibold tracking-[-0.05em] text-balance"
                    >
                        Images from a sentence, priced to the cent.
                    </h1>
                    <p
                        class="mt-6 max-w-[44ch] text-lg leading-relaxed text-muted-foreground"
                    >
                        Describe anything. See the price before you create, and
                        only pay for the images you keep.
                    </p>

                    <div
                        class="mt-10 w-full rounded-[34px] bg-white/50 p-2.5 text-left shadow-[0_32px_80px_-36px_rgb(11_27_51/0.45)] ring-1 ring-[#0b1b33]/[0.07] backdrop-blur-xl dark:bg-white/5"
                    >
                        <form
                            class="rounded-[26px] bg-background p-4 shadow-[0_1px_2px_rgb(11_27_51/0.06)] ring-1 ring-border"
                            @submit.prevent="generate"
                        >
                            <textarea
                                ref="promptField"
                                v-model="prompt"
                                rows="2"
                                aria-label="Prompt"
                                placeholder="A lighthouse on a cliff at dawn, oil painting"
                                class="w-full resize-none bg-transparent px-1.5 pt-0.5 text-[17px] leading-relaxed outline-none placeholder:text-muted-foreground/70"
                                @keydown.enter.exact.prevent="generate"
                            />
                            <div
                                class="flex flex-wrap items-center gap-2.5 pt-4"
                            >
                                <div
                                    class="flex rounded-full bg-secondary p-1"
                                    role="radiogroup"
                                    aria-label="Shape"
                                >
                                    <button
                                        v-for="shape in shapes"
                                        :key="shape.size"
                                        type="button"
                                        role="radio"
                                        :aria-checked="size === shape.size"
                                        :aria-label="shape.name"
                                        class="inline-flex h-8 items-center gap-2 rounded-full px-3 text-sm font-medium transition"
                                        :class="
                                            size === shape.size
                                                ? 'bg-background text-foreground shadow-[0_1px_3px_rgb(11_27_51/0.12)]'
                                                : 'text-muted-foreground hover:text-foreground'
                                        "
                                        @click="size = shape.size"
                                    >
                                        <span
                                            class="block rounded-[2px] border-[1.5px] border-current"
                                            :style="{
                                                width: `${shape.width / 112}px`,
                                                height: `${shape.height / 112}px`,
                                            }"
                                        />
                                        <span class="hidden sm:inline">{{
                                            shape.name
                                        }}</span>
                                    </button>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 items-center gap-2 rounded-full bg-secondary pr-3 pl-3.5 text-sm font-medium outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                                            aria-label="Quality"
                                        >
                                            <QualityBars :quality="quality" />
                                            {{ qualityOf(quality).name }}
                                            <ChevronDown
                                                class="size-3.5 text-muted-foreground"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="start"
                                        class="w-64 rounded-2xl p-1.5"
                                    >
                                        <DropdownMenuRadioGroup
                                            v-model="quality"
                                        >
                                            <DropdownMenuRadioItem
                                                v-for="tier in qualities"
                                                :key="tier.quality"
                                                :value="tier.quality"
                                                class="rounded-xl py-2 pr-3"
                                            >
                                                <span class="grid gap-0.5">
                                                    <span
                                                        class="inline-flex items-center gap-2 font-medium"
                                                    >
                                                        <QualityBars
                                                            :quality="
                                                                tier.quality
                                                            "
                                                        />
                                                        {{ tier.name }}
                                                    </span>
                                                    <span
                                                        class="text-xs text-muted-foreground"
                                                        >{{ tier.hint }}</span
                                                    >
                                                </span>
                                                <span
                                                    v-if="prices"
                                                    class="ml-auto text-xs text-muted-foreground tabular-nums"
                                                    >up to
                                                    {{
                                                        formatPrice(
                                                            prices[
                                                                tier.quality
                                                            ][size].max,
                                                            'ceiling',
                                                        )
                                                    }}</span
                                                >
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <div class="ml-auto flex items-center gap-4">
                                    <span
                                        v-if="upTo"
                                        class="text-sm text-muted-foreground tabular-nums"
                                    >
                                        Up to
                                        <span
                                            class="font-semibold text-foreground"
                                            >{{ upTo }}</span
                                        >
                                    </span>
                                    <button
                                        type="submit"
                                        :disabled="saving"
                                        class="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-6 text-[15px] font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-70"
                                    >
                                        <LoaderCircle
                                            v-if="saving"
                                            class="size-4 animate-spin"
                                        />
                                        Generate
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div
                            class="flex [scrollbar-width:none] gap-2 overflow-x-auto [mask-image:linear-gradient(to_right,black_calc(100%-64px),transparent)] px-2 pt-2.5 pb-0.5"
                            aria-label="Ideas to start from"
                        >
                            <button
                                v-for="idea in ideas"
                                :key="idea.label"
                                type="button"
                                class="inline-flex h-9 shrink-0 items-center gap-2 rounded-full bg-white/80 px-3.5 text-sm font-medium text-foreground/80 ring-1 ring-[#0b1b33]/[0.06] transition hover:bg-white hover:text-foreground dark:bg-white/10"
                                @click="useIdea(idea)"
                            >
                                <component
                                    :is="idea.icon"
                                    class="size-4 text-muted-foreground"
                                />
                                {{ idea.label }}
                            </button>
                        </div>
                    </div>
                    <p class="mt-5 text-sm text-muted-foreground">
                        Your first
                        <span class="font-medium text-foreground">{{
                            welcomeCredit
                        }}</span>
                        is on us.
                    </p>
                </div>
            </section>

            <section
                id="pricing"
                class="mx-auto grid max-w-5xl scroll-mt-8 gap-6 px-5 py-20"
                aria-labelledby="pricing-title"
            >
                <div class="grid gap-2">
                    <h2
                        id="pricing-title"
                        class="text-3xl font-semibold tracking-tight"
                    >
                        Simple pricing, per image
                    </h2>
                    <p class="max-w-[60ch] text-muted-foreground">
                        No subscriptions and no surprises. Pick a quality and a
                        shape, and you'll know the price before you start.
                    </p>
                </div>
                <div
                    v-if="prices"
                    class="overflow-x-auto rounded-[22px] bg-background ring-1 ring-border"
                >
                    <table class="w-full min-w-[520px] text-sm tabular-nums">
                        <thead>
                            <tr class="bg-secondary/60 text-left">
                                <th scope="col" class="px-5 py-3 font-medium">
                                    Quality
                                </th>
                                <th
                                    v-for="shape in shapes"
                                    :key="shape.size"
                                    scope="col"
                                    class="px-5 py-3 text-right font-medium"
                                >
                                    <span
                                        class="inline-flex items-center gap-2"
                                    >
                                        <span
                                            class="inline-block rounded-[2px] border-[1.5px] border-current text-muted-foreground"
                                            :style="{
                                                width: `${shape.width / 96}px`,
                                                height: `${shape.height / 96}px`,
                                            }"
                                        />
                                        {{ shape.name }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="tier in qualities"
                                :key="tier.quality"
                                class="border-t border-border"
                            >
                                <th
                                    scope="row"
                                    class="px-5 py-4 text-left font-medium"
                                >
                                    {{ tier.name }}
                                    <span
                                        class="block text-xs font-normal text-muted-foreground"
                                        >{{ tier.hint }}</span
                                    >
                                </th>
                                <td
                                    v-for="shape in shapes"
                                    :key="shape.size"
                                    class="px-5 py-4 text-right"
                                >
                                    <span class="text-xs text-muted-foreground"
                                        >from </span
                                    ><span class="font-medium">{{
                                        formatPrice(
                                            prices[tier.quality][shape.size]
                                                .min,
                                        )
                                    }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="rounded-[22px] bg-secondary p-6 text-sm text-muted-foreground"
                >
                    Prices are taking a moment to load. Check back shortly.
                </p>
            </section>

            <section
                id="how-it-works"
                class="mx-auto grid max-w-5xl scroll-mt-8 gap-8 px-5 py-20"
                aria-labelledby="how-title"
            >
                <h2
                    id="how-title"
                    class="text-3xl font-semibold tracking-tight"
                >
                    How it works
                </h2>
                <ol class="grid gap-4 sm:grid-cols-3">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="grid content-start gap-2 rounded-[22px] bg-background/70 p-6 ring-1 ring-border"
                    >
                        <span
                            class="grid size-8 place-items-center rounded-full bg-secondary text-sm font-semibold tabular-nums"
                            >{{ index + 1 }}</span
                        >
                        <h3 class="mt-2 text-lg font-semibold">
                            {{ step.title }}
                        </h3>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ step.body }}
                        </p>
                    </li>
                </ol>
                <div>
                    <Link
                        :href="user ? studio() : register()"
                        class="inline-flex h-12 items-center rounded-full bg-primary px-6 text-[15px] font-medium text-primary-foreground hover:bg-primary/90"
                        >{{
                            user
                                ? 'Open the studio'
                                : `Start creating, ${welcomeCredit} on us`
                        }}</Link
                    >
                </div>
            </section>
        </main>

        <footer class="relative z-10 mx-auto max-w-5xl px-5">
            <div
                class="flex flex-wrap items-center gap-x-8 gap-y-4 border-t border-border py-10 text-sm text-muted-foreground"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-semibold text-foreground"
                >
                    <AppLogoIcon class="size-4" />
                    {{ appName }}
                </Link>
                <nav class="flex gap-6" aria-label="Footer">
                    <a href="#pricing" class="hover:text-foreground">Pricing</a>
                    <a href="#how-it-works" class="hover:text-foreground"
                        >How it works</a
                    >
                    <Link
                        :href="user ? studio() : login()"
                        class="hover:text-foreground"
                        >{{ user ? 'Studio' : 'Log in' }}</Link
                    >
                </nav>
                <p class="sm:ml-auto">© {{ year }} {{ appName }}</p>
            </div>
        </footer>

        <div
            v-if="wallOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-[#141414]/30 p-5 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="wall-title"
            @keydown.esc="closeWall"
        >
            <div
                class="relative w-full max-w-[440px] rounded-[22px] bg-background p-7 text-left shadow-2xl"
            >
                <button
                    type="button"
                    class="absolute top-4 right-4 grid size-9 place-items-center rounded-full text-muted-foreground hover:bg-secondary"
                    aria-label="Close"
                    @click="closeWall"
                >
                    <X class="size-4" />
                </button>
                <div
                    class="grid grid-cols-[48px_1fr] items-center gap-3 rounded-2xl bg-secondary p-2.5 pr-10"
                >
                    <span
                        class="grid h-8 w-12 place-items-center rounded bg-accent"
                    >
                        <span
                            class="block rounded-[2px] border-[1.5px] border-muted-foreground"
                            :style="{
                                width: `${shapeOf(size).width / 96}px`,
                                height: `${shapeOf(size).height / 96}px`,
                            }"
                        />
                    </span>
                    <p class="text-sm leading-snug">
                        <span class="line-clamp-2">{{ prompt }}</span>
                        <span class="block text-xs text-muted-foreground">{{
                            draftSummary
                        }}</span>
                    </p>
                </div>
                <h2
                    id="wall-title"
                    class="mt-6 text-[22px] font-semibold tracking-tight"
                >
                    Your idea is saved
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Create an account to bring it to life. Your first
                    {{ welcomeCredit }} is on us.
                </p>
                <form class="mt-5 grid gap-2.5" @submit.prevent="createAccount">
                    <input
                        v-model="account.name"
                        autocomplete="name"
                        placeholder="Name"
                        aria-label="Name"
                        class="h-12 rounded-xl bg-secondary px-4 outline-none focus:bg-background focus:ring-[1.5px] focus:ring-primary"
                    />
                    <InputError :message="account.errors.name" />
                    <input
                        v-model="account.email"
                        type="email"
                        autocomplete="email"
                        placeholder="Email"
                        aria-label="Email"
                        class="h-12 rounded-xl bg-secondary px-4 outline-none focus:bg-background focus:ring-[1.5px] focus:ring-primary"
                    />
                    <InputError :message="account.errors.email" />
                    <input
                        v-model="account.password"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Password"
                        aria-label="Password"
                        class="h-12 rounded-xl bg-secondary px-4 outline-none focus:bg-background focus:ring-[1.5px] focus:ring-primary"
                    />
                    <input
                        v-model="account.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        aria-label="Confirm password"
                        class="h-12 rounded-xl bg-secondary px-4 outline-none focus:bg-background focus:ring-[1.5px] focus:ring-primary"
                    />
                    <InputError :message="account.errors.password" />
                    <button
                        type="submit"
                        :disabled="account.processing"
                        class="mt-2 inline-flex h-12 items-center justify-center gap-2 rounded-full bg-primary text-[15px] font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-70"
                    >
                        <LoaderCircle
                            v-if="account.processing"
                            class="size-4 animate-spin"
                        />
                        Create my account
                    </button>
                </form>
                <p class="mt-4 text-center text-sm text-muted-foreground">
                    Already have an account?
                    <Link
                        :href="login()"
                        class="font-medium text-foreground underline underline-offset-4"
                        >Log in</Link
                    >
                </p>
            </div>
        </div>
    </div>
</template>
