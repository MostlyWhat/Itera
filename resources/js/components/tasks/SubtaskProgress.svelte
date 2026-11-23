<script module lang="ts">
    export type Progress = { done: number; total: number; percent: number };
</script>

<script lang="ts">
    import { Progress as Bar } from '@/components/ui/progress';
    import { cn } from '@/lib/utils';

    // How much of a Task is finished, from its Subtasks: a short bar and the
    // count. A screen reader hears the count in words.
    let { progress, class: className }: { progress: Progress; class?: string } = $props();
</script>

<span
    class={cn('flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground tabular-nums', className)}
    title={`${progress.percent}% of the subtasks done`}
>
    <Bar value={progress.percent} class="h-1 w-8" aria-hidden="true" />
    <span aria-hidden="true">{progress.done}/{progress.total}</span>
    <span class="sr-only">{progress.done} of {progress.total} subtasks done</span>
</span>
