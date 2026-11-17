import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/GenerationController';
import { requestFrom } from '@/lib/generations';
import type { Generation } from '@/types';

export function useRetry() {
    const retrying = ref<string | null>(null);

    function retry(generation: Generation) {
        if (retrying.value !== null) {
            return;
        }

        retrying.value = generation.id;

        router.post(store.url(), requestFrom(generation), {
            onFinish: () => {
                retrying.value = null;
            },
        });
    }

    return { retrying, retry };
}
