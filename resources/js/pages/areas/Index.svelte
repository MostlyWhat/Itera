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
    import { Link, page } from '@inertiajs/svelte';
    import Archive from 'carbon-icons-svelte/lib/Archive.svelte';
    import Edit from 'carbon-icons-svelte/lib/Edit.svelte';
    import Undo from 'carbon-icons-svelte/lib/Undo.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import NameForm from '@/components/areas/NameForm.svelte';
    import { buttonVariants } from '@/components/ui/button';
    import { cn } from '@/lib/utils';
    import {
        archive as archiveArea,
        store as storeArea,
        unarchive as unarchiveArea,
        update as updateArea,
    } from '@/routes/areas';
    import {
        archive as archiveProject,
        store as storeProject,
        unarchive as unarchiveProject,
        update as updateProject,
    } from '@/routes/projects';

    type ProjectItem = { id: number; name: string };
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

    // The key of the Area or Project that shows its rename form, if any.
    let editing = $state<string | null>(null);

    const iconButton = cn(
        buttonVariants({ variant: 'ghost', size: 'sm' }),
        'h-8 gap-1.5 px-2 text-muted-foreground hover:text-foreground',
    );

    const archivedCount = $derived(
        archivedAreas.length + archivedProjects.length,
    );
</script>

<AppHead title="Areas" />

<div class="flex flex-col px-4 py-8 sm:py-10">
    <header class="mb-6 space-y-1">
        <h1 class="type-title">Areas</h1>
        <p class="max-w-prose text-sm text-muted-foreground">
            Group your work by part of life, for example a class, a job, or
            home. Each Area holds Projects.
        </p>
    </header>

    <div class="mb-8">
        <NameForm
            action={storeArea.form(team)}
            label="New area name"
            placeholder="New area, for example Biology 201"
            submitLabel="Add area"
        />
    </div>

    {#if areas.length === 0}
        <div class="border-y border-border py-10">
            <h2 class="type-section">No areas yet</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Add your first Area above. Then add Projects to it.
            </p>
        </div>
    {/if}

    {#each areas as area (area.id)}
        <section
            class="border-t border-border py-5 last-of-type:border-b"
            aria-labelledby={`area-${area.id}`}
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                {#if editing === `area-${area.id}`}
                    <NameForm
                        action={updateArea.form({
                            current_team: team,
                            area: area.id,
                        })}
                        value={area.name}
                        label={`Rename ${area.name}`}
                        submitLabel="Save"
                        autofocus
                        onDone={() => (editing = null)}
                        onCancel={() => (editing = null)}
                    />
                {:else}
                    <h2 id={`area-${area.id}`} class="type-section">
                        {area.name}
                    </h2>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class={iconButton}
                            onclick={() => (editing = `area-${area.id}`)}
                        >
                            <Edit class="size-4" />
                            Rename
                        </button>
                        <Link
                            href={archiveArea({
                                current_team: team,
                                area: area.id,
                            })}
                            as="button"
                            preserveScroll
                            class={iconButton}
                        >
                            <Archive class="size-4" />
                            Archive
                        </Link>
                    </div>
                {/if}
            </div>

            <ul class="mt-3" aria-label={`Projects in ${area.name}`}>
                {#each area.projects as project (project.id)}
                    <li
                        class="flex min-h-11 flex-wrap items-center justify-between gap-2 border-t border-border py-1.5 pl-4"
                    >
                        {#if editing === `project-${project.id}`}
                            <NameForm
                                action={updateProject.form({
                                    current_team: team,
                                    project: project.id,
                                })}
                                value={project.name}
                                label={`Rename ${project.name}`}
                                submitLabel="Save"
                                autofocus
                                onDone={() => (editing = null)}
                                onCancel={() => (editing = null)}
                            />
                        {:else}
                            <span class="text-sm">{project.name}</span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class={iconButton}
                                    onclick={() =>
                                        (editing = `project-${project.id}`)}
                                >
                                    <Edit class="size-4" />
                                    <span class="sr-only sm:not-sr-only"
                                        >Rename</span
                                    >
                                </button>
                                <Link
                                    href={archiveProject({
                                        current_team: team,
                                        project: project.id,
                                    })}
                                    as="button"
                                    preserveScroll
                                    class={iconButton}
                                >
                                    <Archive class="size-4" />
                                    <span class="sr-only sm:not-sr-only"
                                        >Archive</span
                                    >
                                </Link>
                            </div>
                        {/if}
                    </li>
                {/each}
                <li class="border-t border-border pt-3 pl-4">
                    <NameForm
                        action={storeProject.form({
                            current_team: team,
                            area: area.id,
                        })}
                        label={`New project in ${area.name}`}
                        placeholder="New project"
                        submitLabel="Add project"
                    />
                </li>
            </ul>
        </section>
    {/each}

    {#if archivedCount > 0}
        <details class="group mt-10">
            <summary
                class="cursor-pointer text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                Archived ({archivedCount})
            </summary>
            <ul class="mt-3">
                {#each archivedAreas as area (`area-${area.id}`)}
                    <li
                        class="flex min-h-11 items-center justify-between gap-2 border-t border-border py-1.5"
                    >
                        <span class="text-sm">
                            {area.name}
                            <span class="text-muted-foreground">· Area</span>
                        </span>
                        <Link
                            href={unarchiveArea({
                                current_team: team,
                                area: area.id,
                            })}
                            as="button"
                            preserveScroll
                            class={iconButton}
                        >
                            <Undo class="size-4" />
                            Restore
                        </Link>
                    </li>
                {/each}
                {#each archivedProjects as project (`project-${project.id}`)}
                    <li
                        class="flex min-h-11 items-center justify-between gap-2 border-t border-border py-1.5"
                    >
                        <span class="text-sm">
                            {project.name}
                            <span class="text-muted-foreground"
                                >· Project in {project.areaName}</span
                            >
                        </span>
                        <Link
                            href={unarchiveProject({
                                current_team: team,
                                project: project.id,
                            })}
                            as="button"
                            preserveScroll
                            class={iconButton}
                        >
                            <Undo class="size-4" />
                            Restore
                        </Link>
                    </li>
                {/each}
            </ul>
        </details>
    {/if}
</div>
