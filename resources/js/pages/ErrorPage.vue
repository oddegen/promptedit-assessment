<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const props = defineProps<{ status: number }>();

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'No access',
        description: "You don't have access to this page.",
    },
    404: {
        title: 'Page not found',
        description: "This page doesn't exist or has moved.",
    },
    503: {
        title: 'Back soon',
        description:
            "We're doing some maintenance. Try again in a few minutes.",
    },
};

const message = computed(
    () =>
        messages[props.status] ?? {
            title: 'Something went wrong',
            description: 'Try again in a moment.',
        },
);
</script>

<template>
    <main
        class="grid min-h-svh place-content-center justify-items-center gap-4 bg-background px-6 text-center"
    >
        <Head :title="message.title" />

        <AppLogoIcon class="size-8 text-foreground" />
        <h1 class="text-2xl font-semibold tracking-tight">
            {{ message.title }}
        </h1>
        <p class="max-w-sm text-muted-foreground">{{ message.description }}</p>
        <Link
            :href="home()"
            class="mt-2 inline-flex h-11 items-center rounded-full bg-primary px-5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >Back to home</Link
        >
    </main>
</template>
