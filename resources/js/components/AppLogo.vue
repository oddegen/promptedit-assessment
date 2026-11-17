<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <AppLogoIcon class="size-[18px] text-foreground" />
    <span class="text-base font-semibold tracking-tight max-sm:sr-only">{{
        name
    }}</span>
</template>
