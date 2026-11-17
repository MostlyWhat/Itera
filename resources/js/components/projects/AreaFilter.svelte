<script lang="ts">
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import ChevronDown from 'carbon-icons-svelte/lib/ChevronDown.svelte';
    import Filter from 'carbon-icons-svelte/lib/Filter.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Command,
        CommandEmpty,
        CommandGroup,
        CommandInput,
        CommandItem,
        CommandList,
        CommandSeparator,
    } from '@/components/ui/command';
    import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

    // Choose which Areas the project list shows. The list is searchable, so it
    // stays usable with many Areas. "New area" lives here too.
    let {
        areas,
        hidden,
        onToggle,
        onShowAll,
        onNewArea,
    }: {
        areas: { id: number; name: string }[];
        hidden: number[];
        onToggle: (id: number) => void;
        onShowAll: () => void;
        onNewArea: () => void;
    } = $props();

    let open = $state(false);

    const shownCount = $derived(areas.filter((area) => !hidden.includes(area.id)).length);
    const label = $derived(
        areas.length === 0
            ? 'No areas yet'
            : shownCount === areas.length
              ? 'All areas'
              : `${shownCount} of ${areas.length} areas`,
    );
</script>

<Popover bind:open>
    <PopoverTrigger>
        {#snippet child({ props })}
            <Button
                {...props}
                variant="outline"
                size="sm"
                class="w-full justify-between gap-2 font-normal"
                aria-label={`Areas shown: ${label}`}
            >
                <span class="flex min-w-0 items-center gap-2">
                    <Filter class="size-4 shrink-0 text-muted-foreground" />
                    <span class="truncate">{label}</span>
                </span>
                <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
            </Button>
        {/snippet}
    </PopoverTrigger>
    <PopoverContent class="w-(--bits-popover-anchor-width) min-w-60 gap-0 p-0" align="start">
        <Command>
            <CommandInput placeholder="Find an area" />
            <CommandList>
                <CommandEmpty>No area has that name.</CommandEmpty>
                {#if areas.length > 0}
                    <CommandGroup heading="Show in the list">
                        {#each areas as area (area.id)}
                            {@const shown = !hidden.includes(area.id)}
                            <CommandItem
                                value={`${area.name} ${area.id}`}
                                data-checked={shown}
                                onSelect={() => onToggle(area.id)}
                            >
                                <span class="min-w-0 flex-1 wrap-break-word">{area.name}</span>
                                <span class="sr-only">{shown ? ', shown' : ', hidden'}</span>
                            </CommandItem>
                        {/each}
                    </CommandGroup>
                    <CommandSeparator />
                {/if}
                <CommandGroup>
                    {#if shownCount < areas.length}
                        <CommandItem value="Show all areas" onSelect={onShowAll}>Show all areas</CommandItem>
                    {/if}
                    <CommandItem
                        value="New area"
                        onSelect={() => {
                            open = false;
                            onNewArea();
                        }}
                    >
                        <Add class="size-4" />
                        New area
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </Command>
    </PopoverContent>
</Popover>
