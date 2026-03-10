<script setup>
defineProps({
    isEmpty: { type: Boolean, default: false },
    minHeight: { type: String, default: '280px' },
});
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        :style="{ minHeight }"
    >
        <div v-if="!isEmpty" class="p-4">
            <slot />
        </div>
        <div
            v-else
            class="flex min-h-[260px] items-center justify-center text-slate-400"
        >
            データがありません
        </div>
    </div>
</template>
