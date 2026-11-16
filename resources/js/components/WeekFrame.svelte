<script lang="ts">
    import { cn } from '@/lib/utils';

    let { class: className = '' }: { class?: string } = $props();
</script>

<!-- Seven 1px day columns (Monday to Sunday). Place it inside a relative parent. -->
<div
    aria-hidden="true"
    class={cn(
        'pointer-events-none absolute inset-0 mx-auto grid max-w-7xl grid-cols-7 border-x border-border sm:mx-6 lg:mx-auto',
        className,
    )}
>
    {#each { length: 7 }, index (index)}
        <div class="border-r border-border last:border-r-0"></div>
    {/each}
</div>
