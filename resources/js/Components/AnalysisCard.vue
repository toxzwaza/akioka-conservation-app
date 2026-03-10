<script setup>
defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    subtext: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md">
        <p class="text-xs font-medium opacity-80">{{ title }}</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-slate-800">{{ value }}</p>
        <p v-if="subtext" class="mt-0.5 text-xs text-slate-500">{{ subtext }}</p>
    </div>
</template>
