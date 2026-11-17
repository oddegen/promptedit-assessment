import { useResizeObserver } from '@vueuse/core';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { computed, ref, toValue } from 'vue';
import { aspectRatio } from '@/lib/generations';
import type { Size } from '@/types';

export function useMasonry<T extends { size: Size }>(
    items: MaybeRefOrGetter<T[]>,
    container: Ref<HTMLElement | null>,
    { minColumnWidth = 210, gap = 14 } = {},
) {
    const width = ref(0);

    useResizeObserver(container, ([entry]) => {
        const observed = Math.round(entry.contentRect.width);

        if (observed !== width.value) {
            requestAnimationFrame(() => {
                width.value = observed;
            });
        }
    });

    return computed(() => {
        const count = Math.max(
            1,
            Math.floor((width.value + gap) / (minColumnWidth + gap)),
        );
        const columns: T[][] = Array.from({ length: count }, () => []);
        const heights = Array.from({ length: count }, () => 0);

        for (const item of toValue(items)) {
            const shortest = heights.indexOf(Math.min(...heights));

            columns[shortest].push(item);
            heights[shortest] += 1 / aspectRatio(item.size) + 0.25;
        }

        return columns;
    });
}
