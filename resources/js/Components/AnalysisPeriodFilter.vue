<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    preset: { type: String, default: 'month' },
    baseRoute: { type: String, required: true },
});

const presets = [
    { value: 'month', label: '今月' },
    { value: 'last_month', label: '先月' },
    { value: 'quarter', label: '今四半期' },
    { value: 'year', label: '年間' },
];

function applyPreset(preset) {
    router.get(route(props.baseRoute), { preset }, { preserveState: true });
}

function applyDateRange() {
    const fromEl = document.getElementById('analysis-from');
    const toEl = document.getElementById('analysis-to');
    if (fromEl?.value && toEl?.value) {
        router.get(route(props.baseRoute), {
            from: fromEl.value,
            to: toEl.value,
        }, { preserveState: true });
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-slate-600">期間</span>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="p in presets"
                :key="p.value"
                type="button"
                :class="[
                    'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                    preset === p.value
                        ? 'bg-indigo-100 text-indigo-800'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                ]"
                @click="applyPreset(p.value)"
            >
                {{ p.label }}
            </button>
        </div>
        <div class="flex items-center gap-2">
            <input
                id="analysis-from"
                type="date"
                :value="from"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <span class="text-slate-500">〜</span>
            <input
                id="analysis-to"
                type="date"
                :value="to"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <button
                type="button"
                class="rounded-lg bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-300"
                @click="applyDateRange"
            >
                適用
            </button>
        </div>
    </div>
</template>
