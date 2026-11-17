<script setup lang="ts">
import { useNow } from '@vueuse/core';
import { computed } from 'vue';
import { secondsWaiting, waitingNote } from '@/lib/generations';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();

const now = useNow({ interval: 1000 });

const seconds = computed(() => secondsWaiting(props.generation, now.value));

const label = computed(() => {
    const clock = `${Math.floor(seconds.value / 60)}:${String(seconds.value % 60).padStart(2, '0')}`;

    return {
        pending: `Starting ${clock}`,
        queued: 'Queued',
        processing: `Creating ${clock}`,
    }[props.generation.status as 'pending' | 'queued' | 'processing'];
});

const note = computed(() => waitingNote(props.generation, seconds.value));
</script>

<template>
    <div
        class="pointer-events-none absolute inset-0 overflow-hidden bg-secondary"
        aria-live="polite"
    >
        <div
            class="absolute inset-y-0 left-0 w-1/2 animate-sweep bg-linear-to-r from-transparent via-background/70 to-transparent motion-reduce:hidden"
        />
        <div class="relative flex h-full flex-col gap-2.5 p-3.5">
            <span
                class="inline-flex h-6 items-center gap-1.5 self-start rounded-full bg-background px-2.5 text-xs font-medium tabular-nums"
            >
                <span
                    class="size-1.5 rounded-full"
                    :class="
                        generation.status === 'processing'
                            ? 'animate-pulse bg-primary'
                            : 'bg-muted-foreground'
                    "
                />
                {{ label }}
            </span>
            <p
                class="min-h-0 flex-1 overflow-hidden [mask-image:linear-gradient(to_bottom,black_calc(100%-2rem),transparent)] text-[19px] leading-[1.22] font-medium text-foreground/45"
            >
                {{ generation.prompt }}
            </p>
            <p v-if="note" class="text-[13px] text-muted-foreground">
                {{ note }}
            </p>
        </div>
    </div>
</template>
