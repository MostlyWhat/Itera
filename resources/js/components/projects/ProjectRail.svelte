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
    import ChevronRight from 'carbon-icons-svelte/lib/ChevronRight.svelte';
    import Undo from 'carbon-icons-svelte/lib/Undo.svelte';
    import NameForm from '@/components/areas/NameForm.svelte';
    import RowActions from '@/components/areas/RowActions.svelte';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';
    import {
        archive as archiveArea,
        store as storeArea,
        unarchive as unarchiveArea,
        update as updateArea,
    } from '@/routes/areas';
    import {
        show as showProject,
        store as storeProject,
        unarchive as unarchiveProject,
    } from '@/routes/projects';

    // The project list of the Projects page, grouped by Area. Areas are
    // created, renamed, and archived here. A Project is renamed and archived
    // on its own page, so a project row is only a link.
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

    // One open form at a time: "new-area", "rename-1", or "add-1".
    let open = $state<string | null>(null);
    const close = () => (open = null);

    const visit = (definition: { url: string; method: 'post' | 'delete' }) =>
        router.visit(definition.url, {
            method: definition.method,
            preserveScroll: true,
        });

    const archivedCount = $derived(
        navigation.archivedAreas.length + navigation.archivedProjects.length,
    );

    // Area actions stay out of sight until the pointer or focus is on the
    // area. Touch screens always show them.
    const reveal =
        '[@media(hover:hover)]:opacity-0 group-hover/area:opacity-100 group-focus-within/area:opacity-100 has-data-[state=open]:opacity-100';
</script>

<nav aria-label="Projects" class="flex flex-col gap-6">
    {#each navigation.areas as area (area.id)}
        <section aria-labelledby={`area-${area.id}`} class="group/area">
            {#if open === `rename-${area.id}`}
                <NameForm
                    action={updateArea.form({ current_team: team, area: area.id })}
                    value={area.name}
                    label={`New name for ${area.name}`}
                    submitLabel="Save"
                    focus
                    onDone={close}
                    onCancel={close}
                />
            {:else}
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
                            onclick={() => (open = `add-${area.id}`)}
                        >
                            <Add class="size-4" />
                        </Button>
                        <RowActions
                            label={area.name}
                            onRename={() => (open = `rename-${area.id}`)}
                            onArchive={() =>
                                visit(archiveArea({ current_team: team, area: area.id }))}
                        />
                    </span>
                </div>
            {/if}

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
                                <span
                                    class="flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive/10 px-1.5 text-xs font-medium text-destructive tabular-nums"
                                >
                                    {project.overdueTasks}
                                    <span class="sr-only">overdue</span>
                                </span>
                            {:else if project.openTasks > 0}
                                <span class="text-xs text-muted-foreground tabular-nums">
                                    {project.openTasks}
                                    <span class="sr-only">open</span>
                                </span>
                            {/if}
                        </Link>
                    </li>
                {/each}

                {#if open === `add-${area.id}`}
                    <li class="px-1 pt-1">
                        <NameForm
                            action={storeProject.form({ current_team: team, area: area.id })}
                            label={`New project in ${area.name}`}
                            placeholder="Project name"
                            submitLabel="Add"
                            focus
                            onDone={close}
                            onCancel={close}
                        />
                    </li>
                {:else if area.projects.length === 0}
                    <li>
                        <button
                            type="button"
                            class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            onclick={() => (open = `add-${area.id}`)}
                        >
                            <Add class="size-4" />
                            Add a project
                        </button>
                    </li>
                {/if}
            </ul>
        </section>
    {/each}

    <div class="border-t border-border pt-4">
        {#if open === 'new-area'}
            <NameForm
                action={storeArea.form(team)}
                label="Area name"
                placeholder="For example Biology 201"
                submitLabel="Create"
                focus
                onDone={close}
                onCancel={close}
            />
        {:else}
            <Button
                variant="ghost"
                size="sm"
                class="w-full justify-start gap-2 px-2 text-muted-foreground hover:text-foreground"
                onclick={() => (open = 'new-area')}
            >
                <Add class="size-4" />
                New area
            </Button>
        {/if}

        {#if archivedCount > 0}
            <details class="group/archived mt-2">
                <summary
                    class="flex h-8 cursor-pointer list-none items-center gap-1.5 rounded-md px-2 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    <ChevronRight
                        class="size-4 group-open/archived:rotate-90 motion-safe:transition-transform"
                    />
                    Archived
                    <span class="tabular-nums">({archivedCount})</span>
                </summary>
                <ul class="mt-1 flex flex-col gap-0.5">
                    {#each navigation.archivedAreas as area (`area-${area.id}`)}
                        <li class="flex min-h-8 items-center gap-2 pl-2">
                            <span class="min-w-0 flex-1 text-sm wrap-break-word text-muted-foreground"
                                >{area.name}</span
                            >
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7 text-muted-foreground hover:text-foreground"
                                aria-label={`Restore ${area.name}`}
                                onclick={() =>
                                    visit(unarchiveArea({ current_team: team, area: area.id }))}
                            >
                                <Undo class="size-4" />
                            </Button>
                        </li>
                    {/each}
                    {#each navigation.archivedProjects as project (`project-${project.id}`)}
                        <li class="flex min-h-8 items-center gap-2 pl-2">
                            <span class="min-w-0 flex-1 text-sm wrap-break-word text-muted-foreground">
                                {project.name}
                                <span class="block text-xs">{project.areaName}</span>
                            </span>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7 text-muted-foreground hover:text-foreground"
                                aria-label={`Restore ${project.name}`}
                                onclick={() =>
                                    visit(
                                        unarchiveProject({ current_team: team, project: project.id }),
                                    )}
                            >
                                <Undo class="size-4" />
                            </Button>
                        </li>
                    {/each}
                </ul>
            </details>
        {/if}
    </div>
</nav>
