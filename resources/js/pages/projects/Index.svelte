<script module lang="ts">
    import { index as projectsIndex } from '@/routes/projects';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Projects',
                href: projectsIndex(props.currentTeam?.slug ?? ''),
            },
        ],
    });
</script>

<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import NameDialog from '@/components/NameDialog.svelte';
    import type { ProjectNavigation } from '@/components/projects/ProjectRail.svelte';
    import ProjectsShell from '@/components/projects/ProjectsShell.svelte';
    import { Button } from '@/components/ui/button';
    import { store as storeArea } from '@/routes/areas';
    import { store as storeProject } from '@/routes/projects';

    // The Projects page before the first Project exists. It says what to do
    // first: make an Area for one part of life, then a Project in it.
    let { navigation }: { navigation: ProjectNavigation } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);
    const firstArea = $derived(navigation.areas[0] ?? null);
    let dialogOpen = $state(false);
</script>

<AppHead title="Projects" />

<ProjectsShell {navigation} {team}>
    <div class="flex max-w-lg flex-col gap-6 pt-2">
        <div class="space-y-2">
            <h1 class="type-title">
                {firstArea ? 'Add your first project' : 'Start with an area'}
            </h1>
            <p class="text-sm text-muted-foreground">
                {#if firstArea}
                    A project is one piece of work with an end, for example a lab
                    report or a midterm.
                {:else}
                    An area is one part of your life, for example a class, a job, or
                    home. Your projects and tasks go inside it.
                {/if}
            </p>
        </div>

        <Button class="w-fit" onclick={() => (dialogOpen = true)}>
            {firstArea ? `Add a project to ${firstArea.name}` : 'Create an area'}
        </Button>
    </div>
</ProjectsShell>

{#if firstArea}
    <NameDialog
        bind:open={dialogOpen}
        title="New project"
        description={`In ${firstArea.name}. A project is one piece of work with an end, for example a lab report.`}
        action={storeProject.form({ current_team: team, area: firstArea.id })}
        label="Project name"
        placeholder="For example Lab report"
        submitLabel="Create project"
    />
{:else}
    <NameDialog
        bind:open={dialogOpen}
        title="New area"
        description="One part of your life, for example a class, a job, or home."
        action={storeArea.form(team)}
        label="Area name"
        placeholder="For example Biology 201"
        submitLabel="Create area"
    />
{/if}
