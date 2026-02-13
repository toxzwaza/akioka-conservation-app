<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-100">
        <div>
            <Link href="/" class="inline-block">
                <ApplicationLogo class="w-16 h-16 fill-current text-slate-600" />
            </Link>
        </div>

        <div
            class="w-full sm:max-w-md mt-8 px-8 py-8 bg-white border border-slate-200 shadow-sm overflow-hidden sm:rounded-xl"
        >
            <slot />
        </div>
    </div>
</template>
