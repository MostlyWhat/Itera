<script lang="ts">
	import { cn, type WithElementRef } from "@/lib/utils.js";
	import type { HTMLOptgroupAttributes } from "svelte/elements";

	let {
		ref = $bindable(null),
		class: className,
		label,
		children,
		...restProps
	}: WithElementRef<HTMLOptgroupAttributes> = $props();
</script>

<optgroup
	bind:this={ref}
	data-slot="native-select-optgroup"
	{label}
	class={cn("bg-[Canvas] text-[CanvasText]", className)}
	{...restProps}
>
	{@render children?.()}
</optgroup>
