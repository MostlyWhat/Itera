<script module lang="ts">
    export type NavigationArea = {
        id: number;
        name: string;
        projects: {
            id: number;
            name: string;
            openTasks: number;
            overdueTasks: number;
        }[];
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import {
        NativeSelect,
        NativeSelectOptGroup,
        NativeSelectOption,
    } from '@/components/ui/native-select';
    import { cn } from '@/lib/utils';
    import { show } from '@/routes/projects';

    // The list of all Projects, grouped by Area. On large screens it is a
    // side list. On small screens it is one select field.
    let {
        navigation,
        team,
        currentId,
    }: {
        navigation: NavigationArea[];
        team: string;
        currentId: number;
    } = $props();

    const url = (id: number) => show({ current_team: team, project: id }).url;
</script>

<div class="lg:hidden">
    <label for="project-select" class="sr-only">Project</label>
    <NativeSelect
        id="project-select"
        value={String(currentId)}
        class="w-full"
        onchange={(event: Event) =>
            router.visit(url(Number((event.target as HTMLSelectElement).value)))}
    >
        {#each navigation as area (area.id)}
            <NativeSelectOptGroup label={area.name}>
                {#each area.projects as project (project.id)}
                    <NativeSelectOption value={String(project.id)}>
                        {project.name}{project.overdueTasks > 0
                            ? ` (${project.overdueTasks} overdue)`
                            : ''}
                    </NativeSelectOption>
                {/each}
            </NativeSelectOptGroup>
        {/each}
    </NativeSelect>
</div>

<nav aria-label="Projects" class="hidden lg:block">
    <div class="flex flex-col gap-5">
        {#each navigation as area (area.id)}
            <div>
                <h2
                    class="mb-1.5 truncate px-2 text-[11px] font-medium tracking-[0.06em] text-muted-foreground uppercase"
                >
                    {area.name}
                </h2>
                <ul class="flex flex-col gap-0.5">
                    {#each area.projects as project (project.id)}
                        {@const active = project.id === currentId}
                        <li>
                            <Link
                                href={url(project.id)}
                                preserveScroll
                                aria-current={active ? 'page' : undefined}
                                class={cn(
                                    'flex h-8 items-center gap-2 rounded-md px-2 text-sm transition-colors focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                    active
                                        ? 'bg-accent font-medium text-accent-foreground'
                                        : 'text-foreground/80 hover:bg-muted hover:text-foreground',
                                )}
                            >
                                <span class="min-w-0 flex-1 truncate"
                                    >{project.name}</span
                                >
                                {#if project.overdueTasks > 0}
                                    <span
                                        class="flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive/10 px-1.5 text-[11px] font-medium text-destructive tabular-nums"
                                        title={`${project.overdueTasks} overdue`}
                                    >
                                        {project.overdueTasks}
                                        <span class="sr-only">overdue</span>
                                    </span>
                                {:else if project.openTasks > 0}
                                    <span
                                        class="text-[11px] text-muted-foreground tabular-nums"
                                        title={`${project.openTasks} open`}
                                    >
                                        {project.openTasks}
                                        <span class="sr-only">open</span>
                                    </span>
                                {/if}
                            </Link>
                        </li>
                    {/each}
                </ul>
            </div>
        {/each}
    </div>
</nav>
