<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ArrowDown from 'carbon-icons-svelte/lib/ArrowDown.svelte';
    import ArrowUp from 'carbon-icons-svelte/lib/ArrowUp.svelte';
    import ChevronRight from 'carbon-icons-svelte/lib/ChevronRight.svelte';
    import DownToBottom from 'carbon-icons-svelte/lib/DownToBottom.svelte';
    import Draggable from 'carbon-icons-svelte/lib/Draggable.svelte';
    import OverflowMenuVertical from 'carbon-icons-svelte/lib/OverflowMenuVertical.svelte';
    import UpToTop from 'carbon-icons-svelte/lib/UpToTop.svelte';
    import { tick } from 'svelte';
    import { flip } from 'svelte/animate';
    import { quintOut } from 'svelte/easing';
    import { prefersReducedMotion } from 'svelte/motion';
    import { SvelteSet } from 'svelte/reactivity';
    import { slide } from 'svelte/transition';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { cn } from '@/lib/utils';
    import { order as orderAreas } from '@/routes/areas';
    import { order as orderProjects } from '@/routes/projects';

    type Row = { id: number; name: string };
    type AreaRow = Row & { projects: Row[] };

    // The priority stack (ITERA-07): Areas and Projects, most important
    // first. Each Area is one row that opens to show its Projects, so a long
    // stack stays short (Carbon accordion: progressive disclosure, with a
    // summary on the closed row). Drag a row, use its arrows, or use its
    // menu to move it to the top or the bottom. Each change saves at once.
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

    // The open Areas. The browser keeps them, so a reload or a save does not
    // close the Area the user works in. Storage can be blocked, so every
    // read and write is guarded.
    const storageKey = $derived(`itera.priority-open.${team}`);
    const readOpen = (key: string): number[] => {
        try {
            const value = JSON.parse(localStorage.getItem(key) ?? '[]');

            return Array.isArray(value) ? value.filter((id) => typeof id === 'number') : [];
        } catch {
            return [];
        }
    };
    // svelte-ignore state_referenced_locally
    const open = new SvelteSet<number>(readOpen(storageKey));
    const toggle = (id: number) => {
        if (open.has(id)) {
            open.delete(id);
        } else {
            open.add(id);
        }

        try {
            localStorage.setItem(storageKey, JSON.stringify([...open]));
        } catch {
            // The choice lasts for this page only.
        }
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
    const reveal = () => ({ duration: prefersReducedMotion.current ? 0 : 180, easing: quintOut });

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

    const control = 'size-7 shrink-0 text-muted-foreground hover:text-foreground disabled:opacity-30';
</script>

<!--
    The controls of one row: two arrows, and a menu that moves the row to
    the top or the bottom in one step. They show on hover and focus, and
    touch screens always show them.
-->
{#snippet controls(name: string, index: number, count: number, move: (from: number, to: number) => void)}
    <span
        class="flex shrink-0 items-center transition-opacity duration-150 group-focus-within/row:opacity-100 group-hover/row:opacity-100 has-data-[state=open]:opacity-100 [@media(hover:hover)]:opacity-0"
    >
        <Button
            variant="ghost"
            size="icon"
            class={control}
            aria-label={`Move ${name} up`}
            disabled={index === 0}
            onclick={(event: MouseEvent) => press(event, () => move(index, index - 1))}
        >
            <ArrowUp class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class={control}
            aria-label={`Move ${name} down`}
            disabled={index === count - 1}
            onclick={(event: MouseEvent) => press(event, () => move(index, index + 1))}
        >
            <ArrowDown class="size-4" />
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger>
                {#snippet child({ props })}
                    <Button
                        {...props}
                        variant="ghost"
                        size="icon"
                        class={cn(control, 'data-[state=open]:bg-accent data-[state=open]:text-accent-foreground')}
                        aria-label={`More ways to move ${name}`}
                        disabled={count < 2}
                    >
                        <OverflowMenuVertical class="size-4" />
                    </Button>
                {/snippet}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-44">
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
    </span>
{/snippet}

<div class="flex flex-col gap-3">
    <p class="sr-only" role="status">{announcement}</p>

    <ol class="flex flex-col gap-1.5" aria-label="Areas, most important first">
        {#each areas as area, areaIndex (area.id)}
            {@const expanded = open.has(area.id)}
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
                        'group/row flex min-h-10 cursor-grab items-center gap-1 rounded-md pl-1 transition-colors duration-500 hover:bg-muted active:cursor-grabbing',
                        highlighted === `area-${area.id}` && 'bg-accent hover:bg-accent',
                    )}
                >
                    <Draggable class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span class="w-5 shrink-0 text-center text-xs text-muted-foreground tabular-nums">{areaIndex + 1}</span>
                    <button
                        type="button"
                        aria-expanded={expanded}
                        aria-controls={`stack-area-${area.id}`}
                        disabled={area.projects.length === 0}
                        onclick={() => toggle(area.id)}
                        class="flex min-w-0 flex-1 cursor-pointer items-start gap-1.5 rounded-sm py-1.5 text-start focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-default"
                    >
                        <ChevronRight
                            class={cn(
                                'mt-0.5 size-4 shrink-0 text-muted-foreground motion-safe:transition-transform motion-safe:duration-150',
                                expanded && 'rotate-90',
                                area.projects.length === 0 && 'invisible',
                            )}
                            aria-hidden="true"
                        />
                        <span class="flex min-w-0 flex-col">
                            <span class="text-sm font-medium wrap-break-word">{area.name}</span>
                            <!-- The closed row says what it holds, so nothing important is hidden. -->
                            <span class="text-xs text-muted-foreground wrap-break-word">
                                {#if area.projects.length === 0}
                                    No projects
                                {:else}
                                    {area.projects.length}
                                    {area.projects.length === 1 ? 'project' : 'projects'}{#if !expanded}, first: {area.projects[0].name}{/if}
                                {/if}
                            </span>
                        </span>
                    </button>
                    {@render controls(area.name, areaIndex, areas.length, moveArea)}
                </div>

                {#if expanded && area.projects.length > 0}
                    <ol
                        id={`stack-area-${area.id}`}
                        transition:slide={reveal()}
                        class="mt-1 flex flex-col gap-0.5 border-t border-border pt-1"
                        aria-label={`Projects in ${area.name}, most important first`}
                    >
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
                                    'group/row flex min-h-8 cursor-grab items-center gap-1 rounded-md pl-1 transition-[opacity,background-color] duration-500 hover:bg-muted active:cursor-grabbing',
                                    highlighted === `project-${project.id}` && 'bg-accent hover:bg-accent',
                                    dragging?.kind === 'project' && dragging.id === project.id && 'opacity-50',
                                )}
                            >
                                <Draggable class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <span class="w-5 shrink-0 text-center text-xs text-muted-foreground tabular-nums">{index + 1}</span>
                                <span class="min-w-0 flex-1 py-1 text-sm wrap-break-word">{project.name}</span>
                                {@render controls(project.name, index, area.projects.length, (from, to) =>
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
