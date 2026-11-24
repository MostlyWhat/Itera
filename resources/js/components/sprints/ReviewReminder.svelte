<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Close from 'carbon-icons-svelte/lib/Close.svelte';
    import Result from 'carbon-icons-svelte/lib/Result.svelte';
    import { Button, buttonVariants } from '@/components/ui/button';
    import { formatWeekRange } from '@/lib/dates';
    import { review as reviewSprint } from '@/routes/sprints';

    // A reminder to review a sprint whose week has ended (ITERA-10). It never
    // blocks the page: "Later" hides it until the next browser session.
    let {
        team,
        sprint,
    }: {
        team: string;
        sprint: { id: number; weekStart: string; weekEnd: string; waiting: number };
    } = $props();

    const key = $derived(`itera.review-later.${sprint.id}`);
    let hidden = $state(false);

    $effect(() => {
        try {
            hidden = sessionStorage.getItem(key) === '1';
        } catch {
            hidden = false;
        }
    });

    const later = () => {
        hidden = true;
        try {
            sessionStorage.setItem(key, '1');
        } catch {
            // The reminder comes back on the next page load.
        }
    };
</script>

{#if !hidden}
    <aside
        aria-label="Sprint review"
        class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border border-primary/30 bg-primary/5 py-3 pr-2 pl-4"
    >
        <Result class="size-5 shrink-0 text-primary" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-sm text-pretty">
            <span class="font-medium">Review the sprint of {formatWeekRange(sprint.weekStart, sprint.weekEnd)}.</span>
            <span class="text-muted-foreground">
                See your points, write a short retrospective, and decide on unfinished tasks.
                {#if sprint.waiting > 1}{sprint.waiting - 1} more {sprint.waiting === 2 ? 'sprint waits' : 'sprints wait'} after it.{/if}
            </span>
        </p>
        <div class="flex shrink-0 items-center gap-1">
            <Link href={reviewSprint({ current_team: team, sprint: sprint.id })} class={buttonVariants({ size: 'sm' })}>
                Review now
            </Link>
            <Button variant="ghost" size="icon" class="size-8 text-muted-foreground" aria-label="Remind me later" onclick={later}>
                <Close class="size-4" />
            </Button>
        </div>
    </aside>
{/if}
