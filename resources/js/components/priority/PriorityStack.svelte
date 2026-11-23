<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ArrowDown from 'carbon-icons-svelte/lib/ArrowDown.svelte';
    import ArrowUp from 'carbon-icons-svelte/lib/ArrowUp.svelte';
    import Draggable from 'carbon-icons-svelte/lib/Draggable.svelte';
    import { tick } from 'svelte';
    import { flip } from 'svelte/animate';
    import { quintOut } from 'svelte/easing';
    import { prefersReducedMotion } from 'svelte/motion';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';
    import { order as orderAreas } from '@/routes/areas';
    import { order as orderProjects } from '@/routes/projects';

    type Row = { id: number; name: string };
    type AreaRow = Row & { projects: Row[] };

    // The priority stack (ITERA-07): Areas and Projects, most important
    // first. Drag a row, or use its arrow buttons. Each change saves at once.
    // Only the order changes: deadlines, statuses, and story points stay.
    let { stack, team }: { stack: AreaRow[]; team: string } = $props();

    // The order on screen. It follows the saved order, and a move changes it
    // at once, before the server answers.
    let areas = $derived<AreaRow[]>(
        stack.map((area) => ({
            id: area.id,
            name: area.name,
            projects: area.projects.map((project) => ({ id: project.id, name: project.name })),
        })),
    );

    // A short message for screen readers after each move.
    let announcement = $state('');

    // The row that just moved lights up for a moment, so the user sees what
    // changed. The key is "area-1" or "project-4".
    let highlighted = $state<string | null>(null);
    let highlightTimer: ReturnType<typeof setTimeout> | undefined;
    const highlight = (key: string) => {
        highlighted = key;
        clearTimeout(highlightTimer);
        highlightTimer = setTimeout(() => (highlighted = null), 900);
    };

    const options = { preserveScroll: true, preserveState: true } as const;

    const moved = <T,>(list: T[], from: number, to: number): T[] => {
        const next = [...list];
        const [item] = next.splice(from, 1);
        next.splice(to, 0, item);

        return next;
    };

    // A copy of the list with new Projects for one Area.
    const withProjects = (areaIndex: number, projects: Row[]) =>
        areas.map((area, index) => (index === areaIndex ? { ...area, projects } : area));

    const saveAreas = () =>
        router.put(orderAreas(team).url, { area_ids: areas.map((area) => area.id) }, options);

    const saveProjects = (area: AreaRow) =>
        router.put(
            orderProjects({ current_team: team, area: area.id }).url,
            { project_ids: area.projects.map((project) => project.id) },
            options,
        );

    const moveArea = (from: number, to: number) => {
        if (to < 0 || to >= areas.length || from === to) {
            return;
        }

        areas = moved(areas, from, to);
        announcement = `${areas[to].name} moved to position ${to + 1} of ${areas.length}.`;
        highlight(`area-${areas[to].id}`);
        saveAreas();
    };

    const moveProject = (areaIndex: number, from: number, to: number) => {
        const area = areas[areaIndex];

        if (to < 0 || to >= area.projects.length || from === to) {
            return;
        }

        const projects = moved(area.projects, from, to);
        areas = withProjects(areaIndex, projects);
        announcement = `${projects[to].name} moved to position ${to + 1} of ${projects.length} in ${area.name}.`;
        highlight(`project-${projects[to].id}`);
        saveProjects(areas[areaIndex]);
    };

    // Drag and drop: the row moves while the pointer passes over other rows
    // of the same list. The order saves when the drag ends.
    let dragging = $state<{ kind: 'area'; id: number } | { kind: 'project'; id: number; areaId: number } | null>(null);
    let dragStart = '';

    const orderKey = () => JSON.stringify(areas.map((area) => [area.id, area.projects.map((project) => project.id)]));

    const startDrag = (event: DragEvent, value: NonNullable<typeof dragging>) => {
        event.stopPropagation();
        dragging = value;
        dragStart = orderKey();
        event.dataTransfer?.setData('text/plain', String(value.id));

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
        }
    };

    const overArea = (event: DragEvent, index: number) => {
        if (dragging?.kind !== 'area') {
            return;
        }

        event.preventDefault();
        const from = areas.findIndex((area) => area.id === dragging?.id);

        if (from !== index) {
            areas = moved(areas, from, index);
        }
    };

    const overProject = (event: DragEvent, areaIndex: number, index: number) => {
        const area = areas[areaIndex];

        if (dragging?.kind !== 'project' || dragging.areaId !== area.id) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const from = area.projects.findIndex((project) => project.id === dragging?.id);

        if (from !== index) {
            areas = withProjects(areaIndex, moved(area.projects, from, index));
        }
    };

    const endDrag = () => {
        const value = dragging;
        dragging = null;

        if (value === null || orderKey() === dragStart) {
            return;
        }

        if (value.kind === 'area') {
            saveAreas();
        } else {
            const area = areas.find((item) => item.id === value.areaId);

            if (area) {
                saveProjects(area);
            }
        }
    };

    // Critically damped settle: fast start, no overshoot.
    const motion = () => ({ duration: prefersReducedMotion.current ? 0 : 220, easing: quintOut });

    // A row at the top or the bottom disables one arrow. Keep the keyboard
    // focus on the other arrow, so it does not fall back to the page.
    const press = async (event: MouseEvent, run: () => void) => {
        const button = event.currentTarget as HTMLButtonElement;
        run();
        await tick();

        if (button.disabled) {
            button.parentElement?.querySelector<HTMLButtonElement>('button:not(:disabled)')?.focus();
        }
    };

    const arrow =
        'size-7 shrink-0 text-muted-foreground hover:text-foreground disabled:opacity-30';
</script>

{#snippet arrows(name: string, index: number, count: number, move: (from: number, to: number) => void)}
    <!-- The arrows show on hover and focus. Touch screens always show them. -->
    <span class="flex shrink-0 items-center transition-opacity duration-150 [@media(hover:hover)]:opacity-0 group-hover/row:opacity-100 group-focus-within/row:opacity-100">
    <Button
        variant="ghost"
        size="icon"
        class={arrow}
        aria-label={`Move ${name} up`}
        disabled={index === 0}
        onclick={(event: MouseEvent) => press(event, () => move(index, index - 1))}
    >
        <ArrowUp class="size-4" />
    </Button>
    <Button
        variant="ghost"
        size="icon"
        class={arrow}
        aria-label={`Move ${name} down`}
        disabled={index === count - 1}
        onclick={(event: MouseEvent) => press(event, () => move(index, index + 1))}
    >
        <ArrowDown class="size-4" />
    </Button>
    </span>
{/snippet}

<div class="flex flex-col gap-3">
    <p class="sr-only" role="status">{announcement}</p>

    <ol class="flex flex-col gap-2" aria-label="Areas, most important first">
        {#each areas as area, areaIndex (area.id)}
            <li
                animate:flip={motion()}
                draggable="true"
                ondragstart={(event: DragEvent) => startDrag(event, { kind: 'area', id: area.id })}
                ondragover={(event: DragEvent) => overArea(event, areaIndex)}
                ondragend={endDrag}
                ondrop={(event: DragEvent) => event.preventDefault()}
                class={cn(
                    'rounded-lg border border-border bg-card p-1 transition-opacity',
                    dragging?.kind === 'area' && dragging.id === area.id && 'opacity-50',
                )}
            >
                <div
                    class={cn(
                        'group/row flex min-h-9 cursor-grab items-center gap-1 rounded-md pl-1 transition-colors duration-500 hover:bg-muted active:cursor-grabbing',
                        highlighted === `area-${area.id}` && 'bg-accent hover:bg-accent',
                    )}
                >
                    <Draggable class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span class="w-4 shrink-0 text-xs text-muted-foreground tabular-nums">{areaIndex + 1}</span>
                    <span
                        class="min-w-0 flex-1 text-xs font-medium tracking-[0.06em] wrap-break-word text-muted-foreground uppercase"
                    >
                        {area.name}
                    </span>
                    {@render arrows(area.name, areaIndex, areas.length, moveArea)}
                </div>

                {#if area.projects.length > 0}
                    <ol class="mt-1 flex flex-col gap-0.5" aria-label={`Projects in ${area.name}, most important first`}>
                        {#each area.projects as project, index (project.id)}
                            <li
                                animate:flip={motion()}
                                draggable="true"
                                ondragstart={(event: DragEvent) =>
                                    startDrag(event, { kind: 'project', id: project.id, areaId: area.id })}
                                ondragover={(event: DragEvent) => overProject(event, areaIndex, index)}
                                ondragend={(event: DragEvent) => {
                                    event.stopPropagation();
                                    endDrag();
                                }}
                                ondrop={(event: DragEvent) => event.preventDefault()}
                                class={cn(
                                    'group/row flex min-h-8 cursor-grab items-center gap-1 rounded-md pl-3 transition-[opacity,background-color] duration-500 hover:bg-muted active:cursor-grabbing',
                                    highlighted === `project-${project.id}` && 'bg-accent hover:bg-accent',
                                    dragging?.kind === 'project' && dragging.id === project.id && 'opacity-50',
                                )}
                            >
                                <Draggable class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <span class="min-w-0 flex-1 text-sm wrap-break-word">{project.name}</span>
                                {@render arrows(project.name, index, area.projects.length, (from, to) =>
                                    moveProject(areaIndex, from, to),
                                )}
                            </li>
                        {/each}
                    </ol>
                {/if}
            </li>
        {/each}
    </ol>
</div>
