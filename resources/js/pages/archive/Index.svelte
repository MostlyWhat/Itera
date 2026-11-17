<script module lang="ts">
    import { archive } from '@/routes';
    import { index as projectsIndex } from '@/routes/projects';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projectsIndex(props.currentTeam?.slug ?? '') },
            { title: 'Archive', href: archive(props.currentTeam?.slug ?? '') },
        ],
    });
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Undo from 'carbon-icons-svelte/lib/Undo.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { unarchive as unarchiveArea } from '@/routes/areas';
    import { unarchive as unarchiveProject } from '@/routes/projects';

    // Archived work stays here until the user restores it. Nothing is lost.
    let {
        archivedAreas,
        archivedProjects,
    }: {
        archivedAreas: { id: number; name: string }[];
        archivedProjects: { id: number; name: string; areaName: string }[];
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    const restore = (definition: { url: string }) =>
        router.delete(definition.url, { preserveScroll: true });
</script>

<AppHead title="Archive" />

<div class="flex max-w-3xl flex-col gap-8 px-4 py-8 sm:py-10">
    <header class="space-y-1">
        <h1 class="type-title">Archive</h1>
        <p class="text-sm text-muted-foreground">
            Areas and projects you archived. Restore one to see it in the project list
            again.
        </p>
    </header>

    {#if archivedAreas.length === 0 && archivedProjects.length === 0}
        <p class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
            The archive is empty. When a class or a project ends, archive it from the
            <Link href={projectsIndex(team)} class="font-medium text-foreground underline underline-offset-4"
                >Projects</Link
            > page.
        </p>
    {/if}

    {#each [{ title: 'Areas', id: 'areas', items: archivedAreas.map((area) => ({ ...area, detail: 'Its projects show again when you restore it.', route: unarchiveArea({ current_team: team, area: area.id }) })) }, { title: 'Projects', id: 'projects', items: archivedProjects.map((project) => ({ ...project, detail: `In ${project.areaName}`, route: unarchiveProject({ current_team: team, project: project.id }) })) }] as group (group.id)}
        {#if group.items.length > 0}
            <section aria-labelledby={`archived-${group.id}`} class="flex flex-col gap-3">
                <h2 id={`archived-${group.id}`} class="type-section">
                    {group.title}
                    <span class="font-normal text-muted-foreground tabular-nums">({group.items.length})</span>
                </h2>
                <ul class="overflow-hidden rounded-lg border border-border bg-card">
                    {#each group.items as item (item.id)}
                        <li class="flex min-h-12 items-center gap-3 border-t border-border py-2 pr-2 pl-4 first:border-t-0">
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="text-sm wrap-break-word">{item.name}</span>
                                <span class="text-xs text-muted-foreground">{item.detail}</span>
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                class="shrink-0 gap-1.5"
                                aria-label={`Restore ${item.name}`}
                                onclick={() => restore(item.route)}
                            >
                                <Undo class="size-4" />
                                Restore
                            </Button>
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}
    {/each}
</div>
