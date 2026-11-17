<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ReportController from '@/actions/App/Http/Controllers/Admin/ReportController';
import { formatPrice } from '@/lib/money';
import type {
    ProviderUsage,
    ReportDay,
    ReportRange,
    ReportSummary,
    ReportUser,
} from '@/types';

const props = defineProps<{
    range: ReportRange;
    from: string;
    to: string;
    summary: ReportSummary;
    days: ReportDay[];
    users: ReportUser[];
    provider?: ProviderUsage;
}>();

const ranges: { value: ReportRange; label: string }[] = [
    { value: '7', label: '7 days' },
    { value: '30', label: '30 days' },
    { value: 'month', label: 'This month' },
];

const hovered = ref<string | null>(null);

const chronological = computed(() => [...props.days].reverse());

const peak = computed(() =>
    Math.max(1, ...props.days.map((day) => day.charged)),
);

const activeDays = computed(() =>
    props.days.filter(
        (day) =>
            day.generations > 0 ||
            (props.provider?.days[day.date]?.generations ?? 0) > 0,
    ),
);

const matches = computed(() => {
    if (!props.provider?.available) {
        return null;
    }

    const settled = activeDays.value
        .map((day) => props.provider?.days[day.date])
        .filter((day) => day && day.status !== 'in_progress');

    return {
        matched: settled.filter((day) => day?.status === 'matched').length,
        total: settled.length,
    };
});

const tooltipDay = computed(() =>
    props.days.find((day) => day.date === hovered.value),
);

function shortDate(value: string) {
    return new Date(`${value}T00:00:00Z`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
}

function difference(amount: number) {
    return `${amount > 0 ? '+' : '−'}${formatPrice(Math.abs(amount), 'exact')}`;
}
</script>

<template>
    <div class="mx-auto grid w-full max-w-6xl gap-6 px-4 pt-2 pb-16 lg:px-6">
        <Head title="Report" />

        <header class="flex flex-wrap items-center gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">Report</h1>
            <nav class="flex gap-1 rounded-full bg-secondary p-1">
                <Link
                    v-for="option in ranges"
                    :key="option.value"
                    :href="ReportController({ query: { range: option.value } })"
                    class="rounded-full px-3.5 py-1.5 text-[13px] font-medium transition"
                    :class="
                        range === option.value
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    >{{ option.label }}</Link
                >
            </nav>
            <p class="text-sm text-muted-foreground tabular-nums">
                {{ shortDate(from) }} to {{ shortDate(to) }}, UTC
            </p>
        </header>

        <section
            class="grid grid-cols-2 gap-px overflow-hidden rounded-[22px] bg-border sm:grid-cols-5"
            aria-label="Summary"
        >
            <div class="bg-secondary p-5">
                <p class="text-[13px] text-muted-foreground">Charged</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatPrice(summary.charged) }}
                </p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    {{ summary.generations }} images
                </p>
            </div>
            <div class="bg-secondary p-5">
                <p class="text-[13px] text-muted-foreground">Model cost</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatPrice(summary.cost) }}
                </p>
            </div>
            <div class="bg-secondary p-5">
                <p class="text-[13px] text-muted-foreground">Profit</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ formatPrice(summary.profit) }}
                </p>
            </div>
            <div class="bg-secondary p-5">
                <p class="text-[13px] text-muted-foreground">Margin</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ summary.margin === null ? '—' : `${summary.margin}%` }}
                </p>
            </div>
            <div class="col-span-2 bg-secondary p-5 sm:col-span-1">
                <p class="text-[13px] text-muted-foreground">
                    Matches Pixelforge
                </p>
                <Deferred data="provider">
                    <template #fallback>
                        <div
                            class="mt-2 h-7 w-24 animate-pulse rounded-lg bg-accent"
                        />
                    </template>
                    <p
                        v-if="matches"
                        class="mt-1 text-2xl font-semibold tabular-nums"
                    >
                        {{
                            matches.total === 0
                                ? 'No full days yet'
                                : `${matches.matched} of ${matches.total} days`
                        }}
                    </p>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Couldn't reach Pixelforge right now.
                    </p>
                </Deferred>
            </div>
        </section>

        <section
            class="grid gap-3 rounded-[22px] bg-secondary p-5"
            aria-labelledby="chart-title"
        >
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="chart-title" class="text-[15px] font-semibold">
                    Charged per day
                </h2>
                <p class="text-xs text-muted-foreground tabular-nums">
                    Peak {{ formatPrice(peak) }}
                </p>
            </div>
            <div class="relative">
                <div
                    class="flex h-44 items-end gap-0.5 border-b border-border"
                    @mouseleave="hovered = null"
                >
                    <button
                        v-for="day in chronological"
                        :key="day.date"
                        type="button"
                        class="group flex h-full min-w-0 flex-1 items-end justify-center focus-visible:outline-none"
                        :aria-label="`${shortDate(day.date)}: ${formatPrice(day.charged)} charged`"
                        @mouseenter="hovered = day.date"
                        @focus="hovered = day.date"
                        @blur="hovered = null"
                    >
                        <span
                            class="block w-full max-w-6 rounded-t-[4px] bg-primary transition-opacity"
                            :class="{
                                'opacity-60':
                                    hovered !== null && hovered !== day.date,
                            }"
                            :style="{
                                height: `${(day.charged / peak) * 100}%`,
                            }"
                        />
                    </button>
                </div>
                <div
                    v-if="tooltipDay"
                    class="pointer-events-none absolute top-0 right-0 grid gap-0.5 rounded-xl bg-background px-3 py-2 text-xs tabular-nums shadow-sm ring-1 ring-border"
                >
                    <p class="font-medium">{{ shortDate(tooltipDay.date) }}</p>
                    <p>{{ formatPrice(tooltipDay.charged) }} charged</p>
                    <p class="text-muted-foreground">
                        {{ formatPrice(tooltipDay.cost) }} model cost,
                        {{ formatPrice(tooltipDay.charged - tooltipDay.cost) }}
                        profit
                    </p>
                </div>
            </div>
            <div
                class="flex justify-between text-xs text-muted-foreground tabular-nums"
            >
                <span>{{ shortDate(from) }}</span>
                <span>{{ shortDate(to) }}</span>
            </div>
        </section>

        <section class="grid gap-3" aria-labelledby="days-title">
            <h2 id="days-title" class="text-lg font-semibold">By day</h2>
            <div class="overflow-x-auto rounded-[22px] bg-secondary">
                <table class="w-full min-w-[640px] text-sm tabular-nums">
                    <thead>
                        <tr class="text-left text-xs text-muted-foreground">
                            <th scope="col" class="px-5 py-3 font-medium">
                                Day
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Images
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Charged
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Model cost
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Profit
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Pixelforge
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="day in activeDays"
                            :key="day.date"
                            class="border-t border-border"
                        >
                            <td class="px-5 py-3 whitespace-nowrap">
                                {{ shortDate(day.date) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                {{ day.generations }}
                            </td>
                            <td
                                class="px-5 py-3 text-right"
                                :title="formatPrice(day.charged, 'exact')"
                            >
                                {{ formatPrice(day.charged) }}
                            </td>
                            <td
                                class="px-5 py-3 text-right"
                                :title="formatPrice(day.cost, 'exact')"
                            >
                                {{ formatPrice(day.cost) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                {{ formatPrice(day.charged - day.cost) }}
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <Deferred data="provider">
                                    <template #fallback>
                                        <span
                                            class="inline-block h-4 w-20 animate-pulse rounded bg-accent align-middle"
                                        />
                                    </template>
                                    <template
                                        v-if="
                                            provider?.available &&
                                            provider.days[day.date]
                                        "
                                    >
                                        <span
                                            v-if="
                                                provider.days[day.date]
                                                    .status === 'in_progress'
                                            "
                                            class="text-muted-foreground"
                                            >Day in progress</span
                                        >
                                        <span
                                            v-else-if="
                                                provider.days[day.date]
                                                    .status === 'matched'
                                            "
                                            >✓ Matches</span
                                        >
                                        <span
                                            v-else
                                            class="font-medium text-amber-700 dark:text-amber-400"
                                            :title="`Pixelforge billed ${formatPrice(provider.days[day.date].cost, 'exact')} for ${provider.days[day.date].generations} images`"
                                            >Off by
                                            {{
                                                difference(
                                                    provider.days[day.date]
                                                        .difference,
                                                )
                                            }}</span
                                        >
                                    </template>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </Deferred>
                            </td>
                        </tr>
                        <tr v-if="activeDays.length === 0">
                            <td
                                colspan="6"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                No images finished in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-3" aria-labelledby="users-title">
            <h2 id="users-title" class="text-lg font-semibold">By customer</h2>
            <div class="overflow-x-auto rounded-[22px] bg-secondary">
                <table class="w-full min-w-[560px] text-sm tabular-nums">
                    <thead>
                        <tr class="text-left text-xs text-muted-foreground">
                            <th scope="col" class="px-5 py-3 font-medium">
                                Customer
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Images
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Charged
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="customer in users"
                            :key="customer.id"
                            class="border-t border-border"
                        >
                            <td class="px-5 py-3">
                                <span class="font-medium">{{
                                    customer.name
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ customer.email }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                {{ customer.generations }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                {{ formatPrice(customer.charged) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                {{
                                    formatPrice(
                                        customer.charged - customer.cost,
                                    )
                                }}
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td
                                colspan="4"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                No customers yet in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
