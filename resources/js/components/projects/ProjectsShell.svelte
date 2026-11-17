<script lang="ts">
    import ListBulleted from 'carbon-icons-svelte/lib/ListBulleted.svelte';
    import type { Snippet } from 'svelte';
    import ProjectRail, {
        type ProjectNavigation,
    } from '@/components/projects/ProjectRail.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Sheet,
        SheetContent,
        SheetHeader,
        SheetTitle,
        SheetTrigger,
    } from '@/components/ui/sheet';

    // The frame of the Projects pages: the project list on the left on wide
    // screens, and in a sheet behind one button on narrow screens.
    let {
        navigation,
        team,
        currentId = null,
        children,
    }: {
        navigation: ProjectNavigation;
        team: string;
        currentId?: number | null;
        children: Snippet;
    } = $props();

    let sheetOpen = $state(false);
</script>

<div class="grid gap-6 px-4 py-8 sm:py-10 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-12">
    <aside class="hidden lg:sticky lg:top-6 lg:block lg:self-start">
        <ProjectRail {navigation} {team} {currentId} />
    </aside>

    <div class="lg:hidden">
        <Sheet bind:open={sheetOpen}>
            <SheetTrigger>
                {#snippet child({ props })}
                    <Button {...props} variant="outline" size="sm" class="gap-2">
                        <ListBulleted class="size-4" />
                        All projects
                    </Button>
                {/snippet}
            </SheetTrigger>
            <SheetContent side="left" class="w-80 overflow-y-auto overscroll-contain p-4">
                <SheetHeader class="p-0 pb-4">
                    <SheetTitle>Projects</SheetTitle>
                </SheetHeader>
                <ProjectRail
                    {navigation}
                    {team}
                    {currentId}
                    onNavigate={() => (sheetOpen = false)}
                />
            </SheetContent>
        </Sheet>
    </div>

    <div class="min-w-0">
        {@render children()}
    </div>
</div>
