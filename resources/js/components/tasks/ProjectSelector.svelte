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
        Select,
        SelectContent,
        SelectGroup,
        SelectGroupHeading,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
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

    const currentName = $derived(
        navigation
            .flatMap((area) => area.projects)
            .find((project) => project.id === currentId)?.name ?? 'Project',
    );
</script>

<div class="lg:hidden">
    <label for="project-select" class="sr-only">Project</label>
    <Select
        type="single"
        value={String(currentId)}
        onValueChange={(value: string) => router.visit(url(Number(value)))}
    >
        <SelectTrigger id="project-select" class="w-full">
            <span class="truncate">{currentName}</span>
        </SelectTrigger>
        <SelectContent>
            {#each navigation as area (area.id)}
                <SelectGroup>
                    <SelectGroupHeading>{area.name}</SelectGroupHeading>
                    {#each area.projects as project (project.id)}
                        <SelectItem value={String(project.id)} label={project.name}>
                            {#snippet children()}
                                {project.name}
                                {#if project.overdueTasks > 0}
                                    <span class="text-xs font-medium text-destructive"
                                        >{project.overdueTasks} overdue</span
                                    >
                                {/if}
                            {/snippet}
                        </SelectItem>
                    {/each}
                </SelectGroup>
            {/each}
        </SelectContent>
    </Select>
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
