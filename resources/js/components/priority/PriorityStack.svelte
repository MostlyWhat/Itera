<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ArrowDown from 'carbon-icons-svelte/lib/ArrowDown.svelte';
    import ArrowUp from 'carbon-icons-svelte/lib/ArrowUp.svelte';
    import ListNumbered from 'carbon-icons-svelte/lib/ListNumbered.svelte';
    import DownToBottom from 'carbon-icons-svelte/lib/DownToBottom.svelte';
    import Draggable from 'carbon-icons-svelte/lib/Draggable.svelte';
    import OverflowMenuVertical from 'carbon-icons-svelte/lib/OverflowMenuVertical.svelte';
    import UpToTop from 'carbon-icons-svelte/lib/UpToTop.svelte';
    import { flip } from 'svelte/animate';
    import { quintOut } from 'svelte/easing';
    import { prefersReducedMotion } from 'svelte/motion';
    import FormDialog from '@/components/FormDialog.svelte';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuShortcut,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { cn } from '@/lib/utils';
    import { order as orderAreas } from '@/routes/areas';
    import { order as orderProjects } from '@/routes/projects';

    type Row = { id: number; name: string };
    type AreaRow = Row & { projects: Row[] };

    // The priority stack (ITERA-07): Areas and Projects, most important
    // first. Each Area is one short row, so a long stack stays easy to scan.
    // The Projects of an Area are ordered in a dialog, one Area at a time.
    // Drag a row, use its menu, or press Alt+Up or Alt+Down. Each change
    // saves at once. Only the order changes:
    // deadlines, statuses, and story points stay.
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

    // The Area whose Projects the dialog orders. It comes from the list by
    // id, so the dialog shows each move at once.
    let dialogAreaId = $state<number | null>(null);
    let dialogOpen = $state(false);
    const dialogIndex = $derived(areas.findIndex((area) => area.id === dialogAreaId));
    const dialogArea = $derived(dialogIndex >= 0 ? areas[dialogIndex] : null);
    const openProjects = (id: number) => {
        dialogAreaId = id;
        dialogOpen = true;
    };

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

    // Alt+Up and Alt+Down move the focused row: the keyboard path next to
    // dragging and the menu.
    const keyMove = (event: KeyboardEvent, index: number, move: (from: number, to: number) => void) => {
        if (!event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) {
            return;
        }

        event.preventDefault();
        move(index, event.key === 'ArrowUp' ? index - 1 : index + 1);
    };
</script>

<!--
    The menu of one row: every way to move it, in one place. The trigger shows
    on hover and focus, and touch screens always show it.
-->
{#snippet rowMenu(name: string, index: number, count: number, move: (from: number, to: number) => void, onOrder?: () => void)}
    <DropdownMenu>
        <DropdownMenuTrigger>
            {#snippet child({ props })}
                <Button
                    {...props}
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0 text-muted-foreground transition-opacity duration-150 group-focus-within/row:opacity-100 group-hover/row:opacity-100 hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground data-[state=open]:opacity-100 [@media(hover:hover)]:opacity-0"
                    aria-label={`Actions for ${name}`}
                >
                    <OverflowMenuVertical class="size-4" />
                </Button>
            {/snippet}
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-48">
            {#if onOrder}
                <DropdownMenuItem onSelect={onOrder}>
                    <ListNumbered class="size-4" />
                    Order projects
                </DropdownMenuItem>
                <DropdownMenuSeparator />
            {/if}
            <DropdownMenuItem disabled={index === 0} onSelect={() => move(index, index - 1)}>
                <ArrowUp class="size-4" />
                Move up
                <DropdownMenuShortcut>Alt ↑</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem disabled={index === count - 1} onSelect={() => move(index, index + 1)}>
                <ArrowDown class="size-4" />
                Move down
                <DropdownMenuShortcut>Alt ↓</DropdownMenuShortcut>
            </DropdownMenuItem>
            <DropdownMenuItem disabled={index === 0} onSelect={() => move(index, 0)}>
                <UpToTop class="size-4" />
                Move to top
            </DropdownMenuItem>
            <DropdownMenuItem disabled={index === count - 1} onSelect={() => move(index, count - 1)}>
                <DownToBottom class="size-4" />
                Move to bottom
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
{/snippet}

<!-- The rank. On hover it turns into the drag handle, so the row shows one mark, not two. -->
{#snippet rank(value: number)}
    <span class="relative flex size-6 shrink-0 items-center justify-center" aria-hidden="true">
        <span class="text-xs font-medium text-muted-foreground tabular-nums transition-opacity duration-150 [@media(hover:hover)]:group-hover/row:opacity-0">
            {value}
        </span>
        <Draggable
            class="absolute size-4 text-muted-foreground opacity-0 transition-opacity duration-150 [@media(hover:hover)]:group-hover/row:opacity-100"
        />
    </span>
{/snippet}

{#snippet projectList(area: AreaRow, areaIndex: number)}
    <ol class="flex flex-col overflow-hidden rounded-lg border border-border" aria-label={`Projects in ${area.name}, most important first`}>
        {#each area.projects as project, index (project.id)}
            <!-- Alt+Up and Alt+Down reach the row from its focused button or menu. -->
            <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
            <li
                animate:flip={motion()}
                draggable="true"
                ondragstart={(event: DragEvent) => startDrag(event, { kind: 'project', id: project.id, areaId: area.id })}
                ondragover={(event: DragEvent) => overProject(event, areaIndex, index)}
                ondragend={(event: DragEvent) => {
                    event.stopPropagation();
                    endDrag();
                }}
                ondrop={(event: DragEvent) => event.preventDefault()}
                onkeydown={(event: KeyboardEvent) => keyMove(event, index, (from, to) => moveProject(areaIndex, from, to))}
                class={cn(
                    'group/row flex h-11 cursor-grab items-center gap-2 border-t border-border bg-card pr-1 pl-2 transition-[opacity,background-color] duration-500 first:border-t-0 hover:bg-muted/60 active:cursor-grabbing',
                    highlighted === `project-${project.id}` && 'bg-accent hover:bg-accent',
                    dragging?.kind === 'project' && dragging.id === project.id && 'opacity-50',
                )}
            >
                {@render rank(index + 1)}
                <span class="min-w-0 flex-1 truncate text-sm" title={project.name}>{project.name}</span>
                {@render rowMenu(project.name, index, area.projects.length, (from, to) => moveProject(areaIndex, from, to))}
            </li>
        {/each}
    </ol>
{/snippet}

<div class="flex flex-col gap-3">
    <p class="sr-only" role="status">{announcement}</p>

    <ol class="flex flex-col overflow-hidden rounded-lg border border-border bg-card" aria-label="Areas, most important first">
        {#each areas as area, areaIndex (area.id)}
            {@const canOrder = area.projects.length > 1}
            <!-- Alt+Up and Alt+Down reach the row from its focused button or menu. -->
            <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
            <li
                animate:flip={motion()}
                draggable="true"
                ondragstart={(event: DragEvent) => startDrag(event, { kind: 'area', id: area.id })}
                ondragover={(event: DragEvent) => overArea(event, areaIndex)}
                ondragend={endDrag}
                ondrop={(event: DragEvent) => event.preventDefault()}
                onkeydown={(event: KeyboardEvent) => keyMove(event, areaIndex, moveArea)}
                class={cn(
                    'group/row flex h-11 cursor-grab items-center gap-2 border-t border-border bg-card pr-1 pl-2 transition-[opacity,background-color] duration-500 first:border-t-0 hover:bg-muted/60 active:cursor-grabbing',
                    highlighted === `area-${area.id}` && 'bg-accent hover:bg-accent',
                    dragging?.kind === 'area' && dragging.id === area.id && 'opacity-50',
                )}
            >
                {@render rank(areaIndex + 1)}
                <button
                    type="button"
                    onclick={() => canOrder && openProjects(area.id)}
                    aria-haspopup={canOrder ? 'dialog' : undefined}
                    class={cn(
                        'flex h-full min-w-0 flex-1 items-center gap-2 rounded-sm text-start focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                        canOrder ? 'cursor-pointer' : 'cursor-grab',
                    )}
                >
                    <span class="min-w-0 flex-1 truncate text-sm font-medium" title={area.name}>{area.name}</span>
                    <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
                        {area.projects.length} {area.projects.length === 1 ? 'project' : 'projects'}
                        {#if canOrder}<span class="sr-only">, order its projects</span>{/if}
                    </span>
                </button>
                {@render rowMenu(area.name, areaIndex, areas.length, moveArea, canOrder ? () => openProjects(area.id) : undefined)}
            </li>
        {/each}
    </ol>
</div>

<FormDialog
    bind:open={dialogOpen}
    title={dialogArea ? `Order projects in ${dialogArea.name}` : 'Order projects'}
    description="Most important at the top. Drag a row, or use its menu. Changes save at once."
>
    {#if dialogArea}
        {@render projectList(dialogArea, dialogIndex)}
        <div class="flex justify-end">
            <Button onclick={() => (dialogOpen = false)}>Done</Button>
        </div>
    {/if}
</FormDialog>
