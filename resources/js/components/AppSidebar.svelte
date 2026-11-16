<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Book from 'carbon-icons-svelte/lib/Book.svelte';
    import LogoGithub from 'carbon-icons-svelte/lib/LogoGithub.svelte';
    import Folders from 'carbon-icons-svelte/lib/Folders.svelte';
    import Calendar from 'carbon-icons-svelte/lib/Calendar.svelte';
    import Grid from 'carbon-icons-svelte/lib/Grid.svelte';
    import ListChecked from 'carbon-icons-svelte/lib/ListChecked.svelte';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavFooter from '@/components/NavFooter.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import TeamSwitcher from '@/components/TeamSwitcher.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { dashboard } from '@/routes';
    import { index as projectsIndex } from '@/routes/projects';
    import { index as sprintsIndex } from '@/routes/sprints';
    import type { NavItem, Team } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const currentTeam = $derived(page.props.currentTeam as Team | null);
    const dashboardUrl = $derived(
        currentTeam ? dashboard(currentTeam.slug) : '/',
    );

    const mainNavItems = $derived<NavItem[]>([
        {
            title: 'This week',
            href: dashboardUrl,
            icon: Grid,
        },
        ...(currentTeam
            ? [
                  {
                      title: 'Projects',
                      href: projectsIndex(currentTeam.slug),
                      icon: ListChecked,
                  },
                  {
                      title: 'Sprints',
                      href: sprintsIndex(currentTeam.slug),
                      icon: Calendar,
                  },
              ]
            : []),
    ]);

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/MostlyWhat/Itera',
            icon: LogoGithub,
        },
        {
            title: 'Documentation',
            href: 'https://github.com/MostlyWhat/Itera#readme',
            icon: Book,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg">
                    {#snippet child({ props })}
                        <Link
                            {...props}
                            href={dashboardUrl}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
        <SidebarMenu>
            <SidebarMenuItem>
                <TeamSwitcher />
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavFooter items={footerNavItems} />
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
