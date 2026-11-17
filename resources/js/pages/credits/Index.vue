<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import NumberFlow from '@number-flow/vue';
import { ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/CreditController';
import { show } from '@/actions/App/Http/Controllers/GenerationController';
import { dollars, formatPrice, priceFormat } from '@/lib/money';
import type {
    CreditPack,
    CreditTransaction,
    Credits,
    HeldGeneration,
} from '@/types';

defineProps<{
    credits: Credits;
    packs: CreditPack[];
    running: HeldGeneration[];
    transactions: { data: CreditTransaction[] };
}>();

const adding = ref<number | null>(null);

function add(pack: CreditPack) {
    if (adding.value !== null) {
        return;
    }

    adding.value = pack.dollars;

    router.post(
        store.url(),
        { pack: pack.dollars },
        {
            preserveScroll: true,
            onFinish: () => {
                adding.value = null;
            },
        },
    );
}

function signed(amount: number) {
    return amount > 0
        ? `+${formatPrice(amount)}`
        : `−${formatPrice(Math.abs(amount))}`;
}

function day(value: string) {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <div class="mx-auto grid w-full max-w-5xl gap-8 px-4 pt-2 pb-16 lg:px-6">
        <Head title="Credits" />

        <section class="grid gap-6 rounded-[22px] bg-secondary p-6 sm:p-8">
            <h1 class="text-2xl font-semibold tracking-tight">Credits</h1>
            <div class="grid gap-6 sm:grid-cols-[1.4fr_1fr_1fr] sm:items-end">
                <div>
                    <p class="text-sm text-muted-foreground">
                        Available to spend
                    </p>
                    <p
                        class="mt-1 text-5xl font-semibold tracking-tight tabular-nums"
                    >
                        <NumberFlow
                            :value="dollars(credits.available)"
                            :format="priceFormat('floor')"
                            locales="en-US"
                        />
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        Set aside for images in progress
                    </p>
                    <p class="mt-1 text-xl font-medium tabular-nums">
                        {{ formatPrice(credits.held) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Balance</p>
                    <p class="mt-1 text-xl font-medium tabular-nums">
                        {{ formatPrice(credits.balance) }}
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-4" aria-labelledby="packs-title">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="packs-title" class="text-lg font-semibold">
                    Add credits
                </h2>
                <p class="text-sm text-muted-foreground">
                    This is a demo, so no card is needed.
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div
                    v-for="pack in packs"
                    :key="pack.dollars"
                    class="flex flex-col gap-4 rounded-[22px] bg-secondary p-5"
                >
                    <div>
                        <p class="text-3xl font-semibold tabular-nums">
                            ${{ pack.dollars }}
                        </p>
                        <p
                            v-if="pack.images"
                            class="mt-1 text-sm text-muted-foreground tabular-nums"
                        >
                            About {{ pack.images.toLocaleString() }}
                            high-quality images
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="adding !== null"
                        class="mt-auto inline-flex h-11 items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-60"
                        @click="add(pack)"
                    >
                        <LoaderCircle
                            v-if="adding === pack.dollars"
                            class="size-4 animate-spin"
                        />
                        Add ${{ pack.dollars }}
                    </button>
                </div>
            </div>
        </section>

        <section class="grid gap-3" aria-labelledby="activity-title">
            <h2 id="activity-title" class="text-lg font-semibold">Activity</h2>

            <ul
                v-if="running.length"
                class="grid divide-y divide-border rounded-[22px] bg-secondary/60"
            >
                <li
                    v-for="generation in running"
                    :key="generation.id"
                    class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-5 py-3 text-sm text-muted-foreground"
                >
                    <Link
                        :href="show(generation.id)"
                        class="truncate hover:text-foreground"
                        >Set aside for “{{ generation.prompt }}”</Link
                    >
                    <span class="tabular-nums"
                        >up to
                        {{ formatPrice(generation.max_price, 'ceiling') }}</span
                    >
                </li>
            </ul>

            <InfiniteScroll
                v-if="transactions.data.length"
                data="transactions"
                class="overflow-hidden rounded-[22px] bg-secondary"
            >
                <table class="w-full text-sm tabular-nums">
                    <thead>
                        <tr class="text-left text-xs text-muted-foreground">
                            <th scope="col" class="px-5 py-3 font-medium">
                                Date
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                What
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Amount
                            </th>
                            <th
                                scope="col"
                                class="hidden px-5 py-3 text-right font-medium sm:table-cell"
                            >
                                Balance
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transaction in transactions.data"
                            :key="transaction.id"
                            class="border-t border-border"
                        >
                            <td
                                class="px-5 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ day(transaction.created_at) }}
                            </td>
                            <td class="max-w-0 truncate px-5 py-3">
                                <Link
                                    v-if="transaction.generation_id"
                                    :href="show(transaction.generation_id)"
                                    class="hover:underline"
                                    >{{ transaction.description }}</Link
                                >
                                <span v-else>{{
                                    transaction.description
                                }}</span>
                            </td>
                            <td
                                class="px-5 py-3 text-right font-medium whitespace-nowrap"
                                :class="{
                                    'text-emerald-700 dark:text-emerald-400':
                                        transaction.amount > 0,
                                }"
                                :title="
                                    formatPrice(transaction.amount, 'exact')
                                "
                            >
                                {{ signed(transaction.amount) }}
                            </td>
                            <td
                                class="hidden px-5 py-3 text-right whitespace-nowrap text-muted-foreground sm:table-cell"
                            >
                                {{ formatPrice(transaction.balance_after) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </InfiniteScroll>
            <p
                v-else
                class="rounded-[22px] bg-secondary p-8 text-center text-sm text-muted-foreground"
            >
                Nothing here yet.
            </p>
        </section>
    </div>
</template>
