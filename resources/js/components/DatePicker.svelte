<script lang="ts">
    import { parseDate, type DateValue } from '@internationalized/date';
    import CalendarIcon from 'carbon-icons-svelte/lib/Calendar.svelte';
    import { Button } from '@/components/ui/button';
    import { Calendar } from '@/components/ui/calendar';
    import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
    import { formatShortDate } from '@/lib/dates';
    import { cn } from '@/lib/utils';

    // A date field for forms: a button that opens the shadcn calendar. A
    // hidden input sends the date as YYYY-MM-DD, or an empty value for "no date".
    let {
        id,
        name,
        value = null,
        placeholder = 'Pick a date',
        clearLabel = 'Clear',
        invalid = false,
        class: className = '',
    }: {
        id?: string;
        name: string;
        value?: string | null;
        placeholder?: string;
        clearLabel?: string;
        invalid?: boolean;
        class?: string;
    } = $props();

    // svelte-ignore state_referenced_locally
    let selected = $state<DateValue | undefined>(value ? parseDate(value) : undefined);
    let open = $state(false);

    const text = $derived(selected ? selected.toString() : '');
</script>

<input type="hidden" {name} value={text} />

<Popover bind:open>
    <PopoverTrigger>
        {#snippet child({ props })}
            <Button
                {...props}
                {id}
                variant="outline"
                aria-invalid={invalid ? 'true' : undefined}
                class={cn(
                    'h-9 justify-start gap-2 px-2.5 font-normal tabular-nums',
                    !selected && 'text-muted-foreground',
                    className,
                )}
            >
                <CalendarIcon class="size-4 shrink-0 text-muted-foreground" />
                {selected ? formatShortDate(text) : placeholder}
            </Button>
        {/snippet}
    </PopoverTrigger>
    <PopoverContent class="w-auto gap-0 overflow-hidden rounded-xl p-0" align="start">
        <Calendar
            type="single"
            bind:value={selected}
            weekStartsOn={1}
            onValueChange={() => (open = false)}
        />
        {#if selected}
            <div class="border-t border-border p-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-full"
                    onclick={() => {
                        selected = undefined;
                        open = false;
                    }}
                >
                    {clearLabel}
                </Button>
            </div>
        {/if}
    </PopoverContent>
</Popover>
