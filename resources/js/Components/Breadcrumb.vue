<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        default: () => [],
        // [{ label: string, href?: string }]
    },
});
</script>

<template>
    <nav aria-label="パンくずリスト" class="flex items-center gap-1.5 text-sm">
        <template v-for="(item, idx) in items" :key="idx">
            <span v-if="idx > 0" class="text-slate-300 select-none" aria-hidden="true">/</span>
            <Link
                v-if="item.href"
                :href="item.href"
                class="text-slate-600 hover:text-slate-900 transition-colors truncate max-w-[200px] sm:max-w-[300px]"
            >
                {{ item.label }}
            </Link>
            <span
                v-else
                class="font-medium text-slate-800 truncate max-w-[200px] sm:max-w-[300px]"
            >
                {{ item.label }}
            </span>
        </template>
    </nav>
</template>
