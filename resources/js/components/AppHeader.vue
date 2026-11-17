<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as history } from '@/actions/App/Http/Controllers/GenerationController';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { studio } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentOrParentUrl } = useCurrentUrl();

const items: NavItem[] = [
    { title: 'Studio', href: studio() },
    { title: 'History', href: history() },
];
</script>

<template>
    <header class="sticky top-0 z-30 bg-background/90 backdrop-blur">
        <div
            class="mx-auto grid h-[72px] max-w-[1440px] grid-cols-[1fr_auto_1fr] items-center gap-3 px-4 lg:px-6"
        >
            <Link
                :href="studio()"
                class="flex items-center gap-2.5 justify-self-start"
            >
                <AppLogo />
            </Link>

            <nav
                class="flex gap-0.5 rounded-full bg-secondary p-1"
                aria-label="Main"
            >
                <Link
                    v-for="item in items"
                    :key="item.title"
                    :href="item.href"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                    :class="
                        isCurrentOrParentUrl(item.href)
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    >{{ item.title }}</Link
                >
            </nav>

            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <button
                        type="button"
                        class="justify-self-end rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Account"
                    >
                        <Avatar class="size-9">
                            <AvatarImage
                                v-if="user.avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-secondary text-sm font-medium text-foreground"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
