<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const flashMessage = computed(() => {
    const flash = page.props.flash;
    if (!flash) return null;
    if (flash.success) return { type: 'success', text: flash.success };
    if (flash.error) return { type: 'error', text: flash.error };
    if (flash.status) return { type: 'status', text: flash.status };
    return null;
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <slot />
    </div>
</template>
