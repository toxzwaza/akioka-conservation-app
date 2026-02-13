<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps(['href', 'active', 'title']);

const classes = computed(() =>
    props.active
        ? 'flex items-center gap-3 py-2.5 pl-3 pr-3 rounded-lg text-sm font-medium bg-slate-700 text-emerald-400 border-l-2 border-emerald-500'
        : 'flex items-center gap-3 py-2.5 pl-3 pr-3 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-700 hover:text-slate-100 transition-colors border-l-2 border-transparent'
);
</script>

<template>
    <Link :href="href" :class="classes" :title="title">
        <slot />
    </Link>
</template>
