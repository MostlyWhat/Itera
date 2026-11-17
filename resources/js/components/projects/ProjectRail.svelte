<script module lang="ts">
    export type RailProject = {
        id: number;
        name: string;
        openTasks: number;
        overdueTasks: number;
    };

    export type ProjectNavigation = {
        areas: { id: number; name: string; projects: RailProject[] }[];
        archivedAreas: { id: number; name: string }[];
        archivedProjects: { id: number; name: string; areaName: string }[];
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import Archive from 'carbon-icons-svelte/lib/Archive.svelte';
    import RowActions from '@/components/areas/RowActions.svelte';
    import NameDialog from '@/components/NameDialog.svelte';
    import AreaFilter from '@/components/projects/AreaFilter.svelte';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';
    import { archive as archiveIndex } from '@/routes';
    import {
        archive as archiveArea,
        store as storeArea,
        update as updateArea,
    } from '@/routes/areas';
    import { show as showProject, store as storeProject } from '@/routes/projects';

    // The project list of the Projects page, grouped by Area. The area filter
    // picks which Areas show. Areas are created, renamed, and archived here.
    // A Project is renamed and archived on its own page.
    let {
        navigation,
        team,
        currentId = null,
        onNavigate,
    }: {
        navigation: ProjectNavigation;
        team: string;
        currentId?: number | null;
        onNavigate?: () => void;
    } = $props();

    // The hidden Areas are a per-browser choice. Storage can be blocked, so
    // every read and write is guarded and the list works without it.
    const storageKey = $derived(`itera.hidden-areas.${team}`);
    const readHidden = (key: string): number[] => {
        try {
            const value = JSON.parse(localStorage.getItem(key) ?? '[]');

            return Array.isArray(value) ? value.filter((id) => typeof id === 'number') : [];
        } catch {
            return [];
        }
    };
    // svelte-ignore state_referenced_locally
    let hidden = $state<number[]>(readHidden(storageKey));
    const saveHidden = (next: number[]) => {
        hidden = next;

        try {
            localStorage.setItem(storageKey, JSON.stringify(next));
        } catch {
            // The choice lasts for this page only.
        }
    };
    const toggleArea = (id: number) =>
        saveHidden(hidden.includes(id) ? hidden.filter((value) => value !== id) : [...hidden, id]);

    // The Area of the open Project always shows, so the user never loses it.
    const visibleAreas = $derived(
        navigation.areas.filter(
            (area) =>
                !hidden.includes(area.id) ||
                area.projects.some((project) => project.id === currentId),
        ),
    );

    // One name dialog for the three actions. `area` is the Area it acts on.
    type NameAction = 'new-area' | 'rename-area' | 'new-project';
    let dialog = $state<{ action: NameAction; area?: { id: number; name: string } }>({
        action: 'new-area',
    });
    let dialogOpen = $state(false);
    const ask = (action: NameAction, area?: { id: number; name: string }) => {
        dialog = { action, area };
        dialogOpen = true;
    };

    const archivedCount = $derived(
        navigation.archivedAreas.length + navigation.archivedProjects.length,
    );

    // Area actions stay out of sight until the pointer or focus is on the
    // area. Touch screens always show them.
    const reveal =
        '[@media(hover:hover)]:opacity-0 group-hover/area:opacity-100 group-focus-within/area:opacity-100 has-data-[state=open]:opacity-100';

    // Counts sit in one fixed-width column, with or without the red fill.
    const count = 'flex h-5 min-w-6 shrink-0 items-center justify-center rounded-full px-1.5 text-xs tabular-nums';
</script>

<nav aria-label="Projects" class="flex flex-col gap-6">
    <AreaFilter
        areas={navigation.areas}
        {hidden}
        onToggle={toggleArea}
        onShowAll={() => saveHidden([])}
        onNewArea={() => ask('new-area')}
    />

    {#each visibleAreas as area (area.id)}
        <section aria-labelledby={`area-${area.id}`} class="group/area">
            <div class="flex min-h-8 items-center gap-1 pl-2">
                <h2
                    id={`area-${area.id}`}
                    class="min-w-0 flex-1 text-xs font-medium tracking-[0.06em] wrap-break-word text-muted-foreground uppercase"
                >
                    {area.name}
                </h2>
                <span class={cn('flex items-center', reveal)}>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 text-muted-foreground hover:text-foreground"
                        aria-label={`Add a project to ${area.name}`}
                        onclick={() => ask('new-project', area)}
                    >
                        <Add class="size-4" />
                    </Button>
                    <RowActions
                        label={area.name}
                        onRename={() => ask('rename-area', area)}
                        onArchive={() =>
                            router.post(
                                archiveArea({ current_team: team, area: area.id }).url,
                                {},
                                { preserveScroll: true },
                            )}
                    />
                </span>
            </div>

            <ul class="mt-1 flex flex-col gap-0.5">
                {#each area.projects as project (project.id)}
                    {@const active = project.id === currentId}
                    <li>
                        <Link
                            href={showProject({ current_team: team, project: project.id })}
                            preserveScroll
                            aria-current={active ? 'page' : undefined}
                            onclick={() => onNavigate?.()}
                            class={cn(
                                'flex min-h-8 items-center gap-2 rounded-md px-2 py-1 text-sm transition-colors focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                active
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : 'text-foreground/80 hover:bg-muted hover:text-foreground',
                            )}
                        >
                            <span class="min-w-0 flex-1 wrap-break-word">{project.name}</span>
                            {#if project.overdueTasks > 0}
                                <span class={cn(count, 'bg-destructive/10 font-medium text-destructive')}>
                                    {project.overdueTasks}
                                    <span class="sr-only">overdue</span>
                                </span>
                            {:else if project.openTasks > 0}
                                <span class={cn(count, 'text-muted-foreground')}>
                                    {project.openTasks}
                                    <span class="sr-only">open</span>
                                </span>
                            {/if}
                        </Link>
                    </li>
                {:else}
                    <li>
                        <button
                            type="button"
                            class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            onclick={() => ask('new-project', area)}
                        >
                            <Add class="size-4" />
                            Add a project
                        </button>
                    </li>
                {/each}
            </ul>
        </section>
    {:else}
        {#if navigation.areas.length > 0}
            <p class="px-2 text-sm text-muted-foreground">
                The filter hides every area. Use it to show them again.
            </p>
        {/if}
    {/each}

    <Link
        href={archiveIndex(team)}
        onclick={() => onNavigate?.()}
        class="flex min-h-8 items-center gap-2 rounded-md border-t border-border px-2 pt-4 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
    >
        <Archive class="size-4" />
        <span class="flex-1">Archive</span>
        {#if archivedCount > 0}
            <span class={cn(count, 'text-muted-foreground')}>{archivedCount}</span>
        {/if}
    </Link>
</nav>

{#key `${dialog.action}-${dialog.area?.id ?? ''}`}
    {#if dialog.action === 'new-area'}
        <NameDialog
            bind:open={dialogOpen}
            title="New area"
            description="One part of your life, for example a class, a job, or home."
            action={storeArea.form(team)}
            label="Area name"
            placeholder="For example Biology 201"
            submitLabel="Create area"
        />
    {:else if dialog.action === 'rename-area' && dialog.area}
        <NameDialog
            bind:open={dialogOpen}
            title="Rename area"
            action={updateArea.form({ current_team: team, area: dialog.area.id })}
            label="Area name"
            value={dialog.area.name}
            submitLabel="Save"
        />
    {:else if dialog.area}
        <NameDialog
            bind:open={dialogOpen}
            title="New project"
            description={`In ${dialog.area.name}. A project is one piece of work with an end, for example a lab report.`}
            action={storeProject.form({ current_team: team, area: dialog.area.id })}
            label="Project name"
            placeholder="For example Lab report"
            submitLabel="Create project"
        />
    {/if}
{/key}
