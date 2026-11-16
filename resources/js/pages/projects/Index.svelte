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
    import NameForm from '@/components/areas/NameForm.svelte';
    import type { ProjectNavigation } from '@/components/projects/ProjectRail.svelte';
    import ProjectsShell from '@/components/projects/ProjectsShell.svelte';
    import { store as storeArea } from '@/routes/areas';

    // The Projects page before the first Project exists. It says what to do
    // first: make an Area for one part of life, then a Project in it.
    let { navigation }: { navigation: ProjectNavigation } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);
    const hasArea = $derived(navigation.areas.length > 0);
</script>

<AppHead title="Projects" />

<ProjectsShell {navigation} {team}>
    <div class="flex max-w-lg flex-col gap-6 pt-2">
        <div class="space-y-2">
            <h1 class="type-title">
                {hasArea ? 'Add your first project' : 'Start with an area'}
            </h1>
            <p class="text-sm text-muted-foreground">
                {#if hasArea}
                    A project is one piece of work with an end, for example a lab
                    report or a midterm. Use <span class="font-medium text-foreground"
                        >Add a project</span
                    > under an area in the list.
                {:else}
                    An area is one part of your life, for example a class, a job, or
                    home. Your projects and tasks go inside it.
                {/if}
            </p>
        </div>

        {#if !hasArea}
            <NameForm
                action={storeArea.form(team)}
                label="Area name"
                placeholder="For example Biology 201"
                submitLabel="Create area"
                focus
            />
        {/if}
    </div>
</ProjectsShell>
