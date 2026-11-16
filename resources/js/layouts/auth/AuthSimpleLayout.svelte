<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import AppWordmark from '@/components/AppWordmark.svelte';
    import WeekFrame from '@/components/WeekFrame.svelte';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="relative flex min-h-svh flex-col overflow-x-clip bg-background text-foreground"
>
    <WeekFrame />

    <header class="relative border-b border-border bg-background">
        <div
            class="mx-auto flex h-16 max-w-7xl items-center px-4 sm:px-10 lg:px-4"
        >
            <Link href={home()} class="flex items-center gap-2.5">
                <span
                    class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                <AppWordmark class="h-3.5" />
            </Link>
        </div>
    </header>

    <main
        class="relative mx-auto grid w-full max-w-7xl flex-1 grid-cols-7 sm:px-6 lg:px-0"
    >
        <!-- The form sits on Tuesday to Friday on large screens. -->
        <div
            class="col-span-7 bg-background px-4 py-16 sm:col-span-5 sm:col-start-2 sm:px-8 sm:py-24 lg:col-span-3 lg:col-start-3"
        >
            <div class="mx-auto w-full max-w-sm">
                <div class="mb-8 space-y-2">
                    <h1
                        class="text-3xl leading-tight font-semibold tracking-[-0.02em] text-balance"
                    >
                        {title}
                    </h1>
                    {#if description}
                        <p class="text-sm text-muted-foreground">
                            {description}
                        </p>
                    {/if}
                </div>
                {@render children?.()}
            </div>
        </div>
    </main>
</div>
