<script setup>
defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <section>
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">
            {{ title }}
        </h2>
        <slot />
    </section>
</template>
