<script setup>
defineProps({
    isEmpty: { type: Boolean, default: false },
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div v-if="!isEmpty" class="overflow-x-auto">
            <slot />
        </div>
        <div
            v-else
            class="flex min-h-[200px] items-center justify-center text-slate-400"
        >
            データがありません
        </div>
    </div>
</template>
