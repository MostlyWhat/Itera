<script module lang="ts">
    import { index } from '@/routes/areas';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Areas',
                href: index(props.currentTeam?.slug ?? ''),
            },
        ],
    });
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import ChevronRight from 'carbon-icons-svelte/lib/ChevronRight.svelte';
    import Folders from 'carbon-icons-svelte/lib/Folders.svelte';
    import Undo from 'carbon-icons-svelte/lib/Undo.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import NameForm from '@/components/areas/NameForm.svelte';
    import RowActions from '@/components/areas/RowActions.svelte';
    import { Button } from '@/components/ui/button';
    import {
        archive as archiveArea,
        store as storeArea,
        unarchive as unarchiveArea,
        update as updateArea,
    } from '@/routes/areas';
    import {
        archive as archiveProject,
        store as storeProject,
        show as showProject,
        unarchive as unarchiveProject,
        update as updateProject,
    } from '@/routes/projects';

    type ProjectItem = {
        id: number;
        name: string;
        openTasks: number;
        overdueTasks: number;
    };
    type AreaItem = { id: number; name: string; projects: ProjectItem[] };

    let {
        areas,
        archivedAreas,
        archivedProjects,
    }: {
        areas: AreaItem[];
        archivedAreas: { id: number; name: string }[];
        archivedProjects: { id: number; name: string; areaName: string }[];
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    // One open form at a time: "new-area", "area-1", "project-3", or "add-1".
    let open = $state<string | null>(null);

    const close = () => (open = null);

    const visit = (
        definition: { url: string; method: 'post' | 'delete' },
    ): void => {
        router.visit(definition.url, {
            method: definition.method,
            preserveScroll: true,
        });
    };

    const archivedCount = $derived(
        archivedAreas.length + archivedProjects.length,
    );

    const projectCount = (count: number) =>
        count === 1 ? '1 project' : `${count} projects`;
</script>

<AppHead title="Areas" />

<div class="flex flex-col px-4 py-8 sm:py-10">
    <header
        class="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4"
    >
        <div class="space-y-1">
            <h1 class="type-title">Areas</h1>
            <p class="max-w-prose text-sm text-muted-foreground">
                Group your work by part of life, for example a class, a job,
                or home. Each Area holds Projects.
            </p>
        </div>
        {#if areas.length > 0 && open !== 'new-area'}
            <Button onclick={() => (open = 'new-area')}>
                <Add class="size-4" />
                New area
            </Button>
        {/if}
    </header>

    {#if open === 'new-area'}
        <div class="mb-6 rounded-lg border border-border bg-card p-4">
            <NameForm
                action={storeArea.form(team)}
                label="Area name"
                placeholder="For example Biology 201"
                submitLabel="Create area"
                focus
                onDone={close}
                onCancel={close}
            />
        </div>
    {/if}

    {#if areas.length === 0 && open !== 'new-area'}
        <div
            class="flex flex-col items-start gap-4 rounded-lg border border-dashed border-border px-6 py-10 sm:items-center sm:text-center"
        >
            <span
                class="flex size-10 items-center justify-center rounded-md bg-accent text-accent-foreground"
                aria-hidden="true"
            >
                <Folders class="size-5" />
            </span>
            <div class="space-y-1">
                <h2 class="type-section">Create your first area</h2>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Start with one part of your week, for example a class you
                    take this term. You add Projects to it next.
                </p>
            </div>
            <Button onclick={() => (open = 'new-area')}>
                <Add class="size-4" />
                New area
            </Button>
        </div>
    {/if}

    <div class="flex flex-col gap-4">
        {#each areas as area (area.id)}
            <section
                class="overflow-hidden rounded-lg border border-border bg-card"
                aria-labelledby={`area-${area.id}`}
            >
                <div
                    class="flex min-h-14 flex-wrap items-center justify-between gap-2 px-4 py-2"
                >
                    {#if open === `area-${area.id}`}
                        <NameForm
                            action={updateArea.form({
                                current_team: team,
                                area: area.id,
                            })}
                            value={area.name}
                            label={`New name for ${area.name}`}
                            submitLabel="Save"
                            focus
                            class="py-1"
                            onDone={close}
                            onCancel={close}
                        />
                    {:else}
                        <div class="flex min-w-0 items-baseline gap-3">
                            <h2
                                id={`area-${area.id}`}
                                class="truncate text-base font-semibold tracking-[-0.01em]"
                            >
                                {area.name}
                            </h2>
                            <span
                                class="shrink-0 text-xs text-muted-foreground tabular-nums"
                            >
                                {projectCount(area.projects.length)}
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 text-muted-foreground hover:text-foreground"
                                onclick={() => (open = `add-${area.id}`)}
                            >
                                <Add class="size-4" />
                                Add project
                            </Button>
                            <RowActions
                                label={area.name}
                                onRename={() => (open = `area-${area.id}`)}
                                onArchive={() =>
                                    visit(
                                        archiveArea({
                                            current_team: team,
                                            area: area.id,
                                        }),
                                    )}
                            />
                        </div>
                    {/if}
                </div>

                <ul aria-label={`Projects in ${area.name}`}>
                    {#each area.projects as project (project.id)}
                        <li
                            class="flex min-h-11 items-center justify-between gap-2 border-t border-border py-1 pr-4 pl-8 transition-colors hover:bg-muted/40"
                        >
                            {#if open === `project-${project.id}`}
                                <NameForm
                                    action={updateProject.form({
                                        current_team: team,
                                        project: project.id,
                                    })}
                                    value={project.name}
                                    label={`New name for ${project.name}`}
                                    submitLabel="Save"
                                    focus
                                    class="py-1.5"
                                    onDone={close}
                                    onCancel={close}
                                />
                            {:else}
                                <Link
                                    href={showProject({
                                        current_team: team,
                                        project: project.id,
                                    })}
                                    class="flex min-w-0 flex-1 items-center gap-3 self-stretch rounded-sm text-sm focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <span
                                        class="truncate font-medium underline-offset-4 hover:underline"
                                        >{project.name}</span
                                    >
                                    <span
                                        class="flex shrink-0 items-center gap-3 text-xs text-muted-foreground tabular-nums"
                                    >
                                        <span
                                            >{project.openTasks} open</span
                                        >
                                        {#if project.overdueTasks > 0}
                                            <span
                                                class="flex items-center gap-1 font-medium text-destructive"
                                            >
                                                <WarningAltFilled
                                                    class="size-3.5"
                                                />
                                                {project.overdueTasks} overdue
                                            </span>
                                        {/if}
                                    </span>
                                </Link>
                                <RowActions
                                    label={project.name}
                                    onRename={() =>
                                        (open = `project-${project.id}`)}
                                    onArchive={() =>
                                        visit(
                                            archiveProject({
                                                current_team: team,
                                                project: project.id,
                                            }),
                                        )}
                                />
                            {/if}
                        </li>
                    {/each}

                    {#if open === `add-${area.id}`}
                        <li class="border-t border-border py-2.5 pr-4 pl-8">
                            <NameForm
                                action={storeProject.form({
                                    current_team: team,
                                    area: area.id,
                                })}
                                label={`New project in ${area.name}`}
                                placeholder="Project name, for example Lab report"
                                submitLabel="Add project"
                                focus
                                onCancel={close}
                            />
                        </li>
                    {:else if area.projects.length === 0}
                        <li
                            class="border-t border-border py-3 pr-4 pl-8 text-sm text-muted-foreground"
                        >
                            No projects yet.
                            <button
                                type="button"
                                class="font-medium text-primary underline-offset-4 hover:underline"
                                onclick={() => (open = `add-${area.id}`)}
                            >
                                Add the first project
                            </button>
                        </li>
                    {/if}
                </ul>
            </section>
        {/each}
    </div>

    {#if archivedCount > 0}
        <details class="group mt-10">
            <summary
                class="flex w-fit cursor-pointer list-none items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground [&::-webkit-details-marker]:hidden"
            >
                <ChevronRight
                    class="size-4 transition-transform group-open:rotate-90"
                />
                Archived
                <span class="tabular-nums">({archivedCount})</span>
            </summary>
            <ul
                class="mt-3 overflow-hidden rounded-lg border border-border bg-card"
            >
                {#each archivedAreas as area (`area-${area.id}`)}
                    <li
                        class="flex min-h-11 items-center justify-between gap-2 border-t border-border px-4 py-1 first:border-t-0"
                    >
                        <span class="min-w-0 truncate text-sm">
                            {area.name}
                            <span class="text-muted-foreground">· Area</span>
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8"
                            onclick={() =>
                                visit(
                                    unarchiveArea({
                                        current_team: team,
                                        area: area.id,
                                    }),
                                )}
                        >
                            <Undo class="size-4" />
                            Restore
                        </Button>
                    </li>
                {/each}
                {#each archivedProjects as project (`project-${project.id}`)}
                    <li
                        class="flex min-h-11 items-center justify-between gap-2 border-t border-border px-4 py-1 first:border-t-0"
                    >
                        <span class="min-w-0 truncate text-sm">
                            {project.name}
                            <span class="text-muted-foreground"
                                >· Project in {project.areaName}</span
                            >
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8"
                            onclick={() =>
                                visit(
                                    unarchiveProject({
                                        current_team: team,
                                        project: project.id,
                                    }),
                                )}
                        >
                            <Undo class="size-4" />
                            Restore
                        </Button>
                    </li>
                {/each}
            </ul>
        </details>
    {/if}
</div>
