<script lang="ts">
    import { addDays, isSameDay, parseDate } from '@/lib/dates';
    import { cn } from '@/lib/utils';

    // The Monday-to-Sunday week of a Sprint, drawn as seven hairline day
    // columns. Today has a cobalt top rule.
    let {
        weekStart,
        class: className = '',
    }: {
        weekStart: string;
        class?: string;
    } = $props();

    const days = $derived(
        Array.from({ length: 7 }, (_, index) => {
            const date = addDays(parseDate(weekStart), index);

            return {
                key: index,
                label: date.toLocaleDateString(undefined, { weekday: 'short' }),
                day: date.getDate(),
                isToday: isSameDay(date, new Date()),
            };
        }),
    );
</script>

<ol
    class={cn(
        'grid grid-cols-7 overflow-hidden rounded-lg border border-border bg-card',
        className,
    )}
    aria-label="Sprint week"
>
    {#each days as day (day.key)}
        <li
            class="relative border-l border-border px-2 py-2 first:border-l-0 sm:px-3"
            aria-current={day.isToday ? 'date' : undefined}
        >
            {#if day.isToday}
                <span
                    aria-hidden="true"
                    class="absolute inset-x-0 top-0 h-0.5 bg-primary"
                ></span>
            {/if}
            <span
                class={cn(
                    'block text-[11px] font-medium tracking-[0.06em] uppercase',
                    day.isToday ? 'text-primary' : 'text-muted-foreground',
                )}
            >
                {day.label}
            </span>
            <span
                class={cn(
                    'block text-sm tabular-nums',
                    day.isToday ? 'font-semibold text-foreground' : 'text-foreground/80',
                )}
            >
                {day.day}
            </span>
        </li>
    {/each}
</ol>
