<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Book from 'carbon-icons-svelte/lib/Book.svelte';
    import LogoGithub from 'carbon-icons-svelte/lib/LogoGithub.svelte';
    import Folders from 'carbon-icons-svelte/lib/Folders.svelte';
    import Calendar from 'carbon-icons-svelte/lib/Calendar.svelte';
    import Help from 'carbon-icons-svelte/lib/Help.svelte';
    import Grid from 'carbon-icons-svelte/lib/Grid.svelte';
    import ListChecked from 'carbon-icons-svelte/lib/ListChecked.svelte';
    import Menu from 'carbon-icons-svelte/lib/Menu.svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import Breadcrumbs from '@/components/Breadcrumbs.svelte';
    import TeamSwitcher from '@/components/TeamSwitcher.svelte';
    import {
        Avatar,
        AvatarFallback,
        AvatarImage,
    } from '@/components/ui/avatar';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import {
        NavigationMenu,
        NavigationMenuItem,
        NavigationMenuList,
        navigationMenuTriggerStyle,
    } from '@/components/ui/navigation-menu';
    import {
        Sheet,
        SheetContent,
        SheetHeader,
        SheetTitle,
        SheetTrigger,
    } from '@/components/ui/sheet';
    import {
        Tooltip,
        TooltipContent,
        TooltipProvider,
        TooltipTrigger,
    } from '@/components/ui/tooltip';
    import UserMenuContent from '@/components/UserMenuContent.svelte';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { getInitials } from '@/lib/initials';
    import { toUrl } from '@/lib/utils';
    import { dashboard, guide } from '@/routes';
    import { index as projectsIndex } from '@/routes/projects';
    import { index as sprintsIndex } from '@/routes/sprints';
    import type { BreadcrumbItem, NavItem, Team } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs?: BreadcrumbItem[];
    } = $props();

    const auth = $derived(page.props.auth);
    const currentTeam = $derived(page.props.currentTeam as Team | null);
    const dashboardUrl = $derived(
        currentTeam ? dashboard(currentTeam.slug) : '/',
    );

    const url = currentUrlState();

    const activeItemStyles =
        'text-foreground';

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
                  {
                      title: 'Guide',
                      href: guide(currentTeam.slug),
                      icon: Help,
                  },
              ]
            : []),
    ]);

    const rightNavItems: NavItem[] = [
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

<div>
    <div class="border-b border-sidebar-border/80">
        <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
            <div class="lg:hidden">
                <Sheet>
                    <SheetTrigger>
                        {#snippet child({ props })}
                            <Button
                                {...props}
                                variant="ghost"
                                size="icon"
                                class="mr-2 h-9 w-9"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        {/snippet}
                    </SheetTrigger>
                    <SheetContent side="left" class="w-[300px] p-6">
                        <SheetTitle class="sr-only">Navigation menu</SheetTitle>
                        <SheetHeader class="flex justify-start text-left">
                            <AppLogoIcon
                                class="size-6 text-primary"
                            />
                        </SheetHeader>
                        <div
                            class="flex h-full flex-1 flex-col justify-between space-y-4 pt-6 pb-10"
                        >
                            <nav class="-mx-3 space-y-1">
                                {#each mainNavItems as item (toUrl(item.href))}
                                    <Link
                                        href={toUrl(item.href)}
                                        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent {url.whenCurrentUrl(
                                            item.href,
                                            url.currentUrl,
                                            activeItemStyles,
                                            '',
                                        ) ?? ''}"
                                    >
                                        {#if item.icon}
                                            {@const Icon = item.icon}
                                            <Icon class="h-5 w-5" />
                                        {/if}
                                        {item.title}
                                    </Link>
                                {/each}
                            </nav>
                            <div class="flex flex-col space-y-4">
                                {#each rightNavItems as item (toUrl(item.href))}
                                    <a
                                        href={toUrl(item.href)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 text-sm font-medium"
                                    >
                                        {#if item.icon}
                                            {@const Icon = item.icon}
                                            <Icon class="h-5 w-5" />
                                        {/if}
                                        <span>{item.title}</span>
                                    </a>
                                {/each}
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>

            <Link href={dashboardUrl} class="flex items-center gap-x-2">
                <AppLogo />
            </Link>

            <div class="hidden h-full lg:flex lg:flex-1">
                <NavigationMenu class="ml-10 flex h-full items-stretch">
                    <NavigationMenuList
                        class="flex h-full items-stretch space-x-2"
                    >
                        {#each mainNavItems as item (toUrl(item.href))}
                            <NavigationMenuItem
                                class="relative flex h-full items-center"
                            >
                                <Link
                                    class="{navigationMenuTriggerStyle()} {url.whenCurrentUrl(
                                        item.href,
                                        url.currentUrl,
                                        activeItemStyles,
                                        '',
                                    ) ?? ''} h-9 cursor-pointer px-4"
                                    href={toUrl(item.href)}
                                >
                                    {#if item.icon}
                                        {@const Icon = item.icon}
                                        <Icon class="mr-2 h-4 w-4" />
                                    {/if}
                                    {item.title}
                                </Link>
                                {#if url.isCurrentUrl(item.href, url.currentUrl)}
                                    <div
                                        class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-primary"
                                    ></div>
                                {/if}
                            </NavigationMenuItem>
                        {/each}
                    </NavigationMenuList>
                </NavigationMenu>
            </div>

            <div class="ml-auto flex items-center space-x-2">
                <div class="relative flex items-center space-x-1">
                    <div class="hidden space-x-1 lg:flex">
                        {#each rightNavItems as item (toUrl(item.href))}
                            {@const Icon = item.icon}
                            <TooltipProvider delayDuration={0}>
                                <Tooltip>
                                    <TooltipTrigger>
                                        {#snippet child({ props })}
                                            <a
                                                href={toUrl(item.href)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                {...props}
                                                class="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground h-9 w-9 group cursor-pointer"
                                            >
                                                <span class="sr-only"
                                                    >{item.title}</span
                                                >
                                                <Icon
                                                    class="size-5 opacity-80 group-hover:opacity-100"
                                                />
                                            </a>
                                        {/snippet}
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{item.title}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        {/each}
                    </div>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger>
                        {#snippet child({ props })}
                            <Button
                                {...props}
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    {#if auth.user.avatar}
                                        <AvatarImage
                                            src={auth.user.avatar}
                                            alt={auth.user.name}
                                        />
                                    {/if}
                                    <AvatarFallback
                                        class="rounded-lg bg-muted font-semibold text-foreground"
                                    >
                                        {getInitials(auth.user?.name)}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        {/snippet}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>

                <TeamSwitcher inHeader={true} />
            </div>
        </div>
    </div>

    {#if breadcrumbs.length > 1}
        <div class="flex w-full border-b border-sidebar-border/70">
            <div
                class="mx-auto flex h-12 w-full items-center justify-start px-4 text-muted-foreground md:max-w-7xl"
            >
                <Breadcrumbs {breadcrumbs} />
            </div>
        </div>
    {/if}
</div>
