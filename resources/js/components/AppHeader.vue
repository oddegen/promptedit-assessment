<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import report from '@/actions/App/Http/Controllers/Admin/ReportController';
import { index as credits } from '@/actions/App/Http/Controllers/CreditController';
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
import { formatPrice } from '@/lib/money';
import { studio } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const balance = computed(() => page.props.credits);
const { isCurrentOrParentUrl } = useCurrentUrl();

const items = computed<NavItem[]>(() =>
    user.value.is_admin
        ? [{ title: 'Report', href: report() }]
        : [
              { title: 'Studio', href: studio() },
              { title: 'History', href: history() },
              { title: 'Credits', href: credits() },
          ],
);
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

            <div class="flex items-center gap-2 justify-self-end">
                <Link
                    v-if="balance"
                    :href="credits()"
                    class="hidden h-9 items-center rounded-full bg-secondary px-3.5 text-sm font-medium tabular-nums transition hover:bg-accent sm:inline-flex"
                    :title="
                        balance.held > 0
                            ? `${formatPrice(balance.held)} set aside for images in progress`
                            : 'Available credit'
                    "
                    >{{ formatPrice(balance.available, 'floor') }}</Link
                >
                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <button
                            type="button"
                            class="rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
        </div>
    </header>
</template>
