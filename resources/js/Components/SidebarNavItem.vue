<script setup>
import { ref, computed } from 'vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: { type: String, default: null },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
    children: { type: Array, default: () => [] },
    collapsed: { type: Boolean, default: false },
});

const showSub = ref(false);
const hasChildren = computed(() => props.children && props.children.length > 0);
</script>

<template>
    <div
        class="relative"
        @mouseenter="hasChildren && (showSub = true)"
        @mouseleave="showSub = false"
    >
        <SidebarNavLink
            v-if="href"
            :href="href"
            :active="active"
            :title="label"
        >
            <!-- 折りたたみ時は先頭1文字を表示 -->
            <span
                v-if="collapsed"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-700 text-xs font-medium text-slate-200 lg:flex"
            >
                {{ label.charAt(0) }}
            </span>
            <span
                class="truncate transition-all duration-200 lg:flex-1"
                :class="collapsed ? 'hidden' : ''"
            >
                {{ label }}
            </span>
            <svg
                v-if="hasChildren"
                class="h-4 w-4 shrink-0 transition-transform"
                :class="{ 'rotate-90': showSub, 'lg:hidden': collapsed }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </SidebarNavLink>

        <!-- サブナビ（ホバーで表示・折りたたみ時は右に表示） -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="hasChildren && showSub"
                class="absolute z-50 mt-1 rounded-lg border border-slate-600 bg-slate-800 py-1 shadow-xl lg:mt-0"
                :class="collapsed ? 'left-full ml-1 min-w-[11rem] top-0' : 'left-0 right-0 top-full'"
            >
                <Link
                    v-for="child in children"
                    :key="child.href"
                    :href="child.href"
                    :class="[
                        'block px-4 py-2 text-sm transition-colors',
                        child.active
                            ? 'bg-slate-700 text-emerald-400'
                            : 'text-slate-300 hover:bg-slate-700 hover:text-white',
                    ]"
                >
                    {{ child.label }}
                </Link>
            </div>
        </Transition>
    </div>
</template>
