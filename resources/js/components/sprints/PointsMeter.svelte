<script lang="ts">
    import { cn } from '@/lib/utils';

    // Committed story points against the target. Over the target, the bar and
    // the count turn amber, so the student sees that the week is too full.
    let {
        committed,
        target,
        class: className = '',
    }: {
        committed: number;
        target: number | null;
        class?: string;
    } = $props();

    const over = $derived(target !== null && committed > target);
    const ratio = $derived(
        target === null || target === 0 ? 0 : Math.min(committed / target, 1),
    );
</script>

<div class={cn('flex flex-col gap-1.5', className)}>
    <div class="flex items-baseline justify-between gap-3 text-sm tabular-nums">
        <span>
            <span
                class={cn(
                    'text-base font-semibold',
                    over ? 'text-warning' : 'text-foreground',
                )}>{committed}</span
            >
            <span class="text-muted-foreground">
                {target === null ? 'points planned' : `of ${target} points`}
            </span>
        </span>
        {#if over && target !== null}
            <span class="text-xs font-medium text-warning"
                >{committed - target} over target</span
            >
        {/if}
    </div>
    {#if target !== null}
        <div
            class="h-1.5 overflow-hidden rounded-full bg-muted"
            role="meter"
            aria-label="Planned story points"
            aria-valuemin={0}
            aria-valuemax={target}
            aria-valuenow={committed}
        >
            <div
                class={cn(
                    'h-full rounded-full transition-[width] duration-300 ease-out',
                    over ? 'bg-warning' : 'bg-primary',
                )}
                style:width={`${ratio * 100}%`}
            ></div>
        </div>
    {/if}
</div>
