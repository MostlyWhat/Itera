<script lang="ts">
	import { currentAppearance } from "@/lib/theme.svelte";
	import { Toaster, type ToasterProps as SonnerProps } from "svelte-sonner";
	import Loader2Icon from 'carbon-icons-svelte/lib/CircleDash.svelte';
	import CircleCheckIcon from 'carbon-icons-svelte/lib/CheckmarkOutline.svelte';
	import OctagonXIcon from 'carbon-icons-svelte/lib/Misuse.svelte';
	import InfoIcon from 'carbon-icons-svelte/lib/Information.svelte';
	import TriangleAlertIcon from 'carbon-icons-svelte/lib/WarningAlt.svelte';

	let { ...restProps }: SonnerProps = $props();
</script>

<Toaster
	theme={currentAppearance()}
	class="toaster group"
	toastOptions={{
		unstyled: true,
		classes: {
			toast: "cn-toast group/toast flex w-(--width) items-start gap-3 rounded-lg border border-border bg-popover px-4 py-3 text-sm text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0_/_0.28)]",
			icon: "mt-0.5 flex size-4 shrink-0 items-center justify-center",
			content: "flex min-w-0 flex-1 flex-col gap-0.5",
			title: "font-medium leading-5",
			description: "leading-5 text-muted-foreground",
			actionButton: "ml-auto h-7 shrink-0 rounded-md bg-primary px-2.5 text-xs font-medium text-primary-foreground",
			cancelButton: "ml-auto h-7 shrink-0 rounded-md px-2.5 text-xs font-medium text-muted-foreground hover:text-foreground",
			success: "[&_[data-icon]]:text-primary",
			info: "[&_[data-icon]]:text-primary",
			warning: "[&_[data-icon]]:text-chart-4",
			error: "[&_[data-icon]]:text-destructive",
		},
	}}
	{...restProps}
>
	{#snippet loadingIcon()}
		<Loader2Icon class="size-4 animate-spin" />
	{/snippet}
	{#snippet successIcon()}
		<CircleCheckIcon class="size-4" />
	{/snippet}
	{#snippet errorIcon()}
		<OctagonXIcon class="size-4" />
	{/snippet}
	{#snippet infoIcon()}
		<InfoIcon class="size-4" />
	{/snippet}
	{#snippet warningIcon()}
		<TriangleAlertIcon class="size-4" />
	{/snippet}
</Toaster>
