<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import ChevronSort from 'carbon-icons-svelte/lib/ChevronSort.svelte';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import {
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
        useSidebar,
    } from '@/components/ui/sidebar';
    import UserInfo from '@/components/UserInfo.svelte';
    import UserMenuContent from '@/components/UserMenuContent.svelte';
    import type { Team } from '@/types';

    const user = $derived(page.props.auth.user);
    const currentTeam = $derived(page.props.currentTeam as Team | null);
    const sidebar = useSidebar();
</script>

<SidebarMenu>
    <SidebarMenuItem>
        <DropdownMenu>
            <DropdownMenuTrigger>
                {#snippet child({ props })}
                    <SidebarMenuButton
                        {...props}
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo {user} team={currentTeam} />
                        <ChevronSort class="ml-auto size-4" />
                    </SidebarMenuButton>
                {/snippet}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="w-full min-w-0 rounded-lg"
                side={sidebar.state === 'collapsed' && !sidebar.isMobile
                    ? 'left'
                    : 'top'}
                align="end"
                sideOffset={4}
            >
                <UserMenuContent {user} />
            </DropdownMenuContent>
        </DropdownMenu>
    </SidebarMenuItem>
</SidebarMenu>
