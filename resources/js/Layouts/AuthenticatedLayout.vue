<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'sidebar-collapsed';
const FLASH_DURATION_MS = 5000;

const page = usePage();
const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const flashVisible = ref(false);

onMounted(() => {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored !== null) sidebarCollapsed.value = stored === 'true';
});

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem(STORAGE_KEY, String(sidebarCollapsed.value));
}

const flashMessage = computed(() => {
    const flash = page.props.flash;
    if (!flash) return null;
    if (flash.success) return { type: 'success', text: flash.success };
    if (flash.error) return { type: 'error', text: flash.error };
    if (flash.status) return { type: 'status', text: flash.status };
    return null;
});

let flashTimer = null;
watch(
    flashMessage,
    (msg) => {
        if (flashTimer) {
            clearTimeout(flashTimer);
            flashTimer = null;
        }
        if (msg) {
            flashVisible.value = true;
            flashTimer = setTimeout(() => {
                flashVisible.value = false;
                flashTimer = null;
            }, FLASH_DURATION_MS);
        } else {
            flashVisible.value = false;
        }
    },
    { immediate: true }
);

const isActive = (routeName) => {
    const path = page.url;
    const segments = path.split('/').filter(Boolean);
    if (routeName === 'dashboard') return path === '/' || path === '';
    if (routeName === 'work.works.index' || routeName === 'work.works.create') return segments[0] === 'work';
    if (routeName === 'master.top') return segments[0] === 'master' && segments.length <= 1;
    return false;
};

const isMasterChildActive = (key) => {
    const path = page.url;
    const segments = path.split('/').filter(Boolean);
    if (key === 'api-test') return segments[0] === 'api-test';
    if (segments[0] !== 'master') return false;
    return segments[1] === key;
};

const masterChildren = computed(() => {
    const list = [
        { key: 'api-test', label: 'APIテスト', href: () => route('api-test.index') },
        { key: 'work-statuses', label: '作業ステータス', href: () => route('master.index', { masterKey: 'work-statuses' }) },
        { key: 'work-priorities', label: '優先度', href: () => route('master.index', { masterKey: 'work-priorities' }) },
        { key: 'work-purposes', label: '作業目的', href: () => route('master.index', { masterKey: 'work-purposes' }) },
        { key: 'work-content-tags', label: '作業タグ', href: () => route('master.index', { masterKey: 'work-content-tags' }) },
        { key: 'repair-types', label: '修理内容', href: () => route('master.index', { masterKey: 'repair-types' }) },
        { key: 'attachment-types', label: '添付種別', href: () => route('master.index', { masterKey: 'attachment-types' }) },
        { key: 'work-activity-types', label: '操作履歴種別', href: () => route('master.index', { masterKey: 'work-activity-types' }) },
        { key: 'work-cost-categories', label: '費用カテゴリ', href: () => route('master.index', { masterKey: 'work-cost-categories' }) },
        { key: 'users', label: 'ユーザー', href: () => route('master.users.index') },
        { key: 'parts', label: '部品', href: () => route('master.parts.index') },
        { key: 'equipments', label: '設備', href: () => route('master.equipments.index') },
    ];
    return list.map(({ key, label, href }) => ({
        href: href(),
        label,
        active: isMasterChildActive(key),
    }));
});

const navItems = computed(() => {
    const master = masterChildren.value;
    return [
        { href: route('dashboard'), label: 'ダッシュボード', active: isActive('dashboard'), children: [] },
        { href: route('work.works.index'), label: '作業', active: isActive('work.works.index'), children: [] },
        {
            href: route('master.top'),
            label: 'マスタ',
            active: isActive('master.top') || master.some((c) => c.active),
            children: master,
        },
    ];
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex">
        <!-- サイドバー（PC） -->
        <aside
            class="hidden lg:fixed lg:inset-y-0 lg:flex lg:flex-col lg:transition-[width] lg:duration-200"
            :class="sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'"
        >
            <div class="flex min-h-0 flex-1 flex-col bg-slate-800 border-r border-slate-700">
                <div class="flex h-16 shrink-0 items-center justify-between gap-1 border-b border-slate-700 px-3">
                    <Link
                        :href="route('dashboard')"
                        class="flex min-w-0 flex-1 items-center gap-2 text-slate-100"
                    >
                        <ApplicationLogo class="block h-8 w-auto shrink-0 opacity-90" />
                        <span
                            class="truncate text-sm font-semibold tracking-tight transition-all duration-200"
                            :class="sidebarCollapsed ? 'lg:max-w-0 lg:opacity-0' : ''"
                        >
                            設備保全
                        </span>
                    </Link>
                    <button
                        type="button"
                        @click="toggleSidebar"
                        class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-700 hover:text-white transition-colors"
                        :title="sidebarCollapsed ? 'サイドバーを展開' : 'サイドバーを最小化'"
                    >
                        <svg
                            class="h-5 w-5 transition-transform duration-200"
                            :class="sidebarCollapsed ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                        </svg>
                    </button>
                </div>
                <div class="flex flex-1 flex-col overflow-y-auto overflow-x-hidden py-4 px-3">
                    <nav class="space-y-0.5">
                        <SidebarNavItem
                            v-for="item in navItems"
                            :key="item.label"
                            :href="item.href"
                            :label="item.label"
                            :active="item.active"
                            :children="item.children"
                            :collapsed="sidebarCollapsed"
                        />
                    </nav>
                </div>
                <div class="shrink-0 border-t border-slate-700 p-3">
                    <div class="rounded-lg bg-slate-700/50 px-3 py-2">
                        <p class="truncate text-xs font-medium text-slate-200">
                            {{ $page.props.auth.user.name }}
                        </p>
                        <p class="truncate text-xs text-slate-500">{{ $page.props.auth.user.email }}</p>
                    </div>
                    <div class="mt-2 space-y-0.5">
                        <Link
                            :href="route('profile.edit')"
                            class="block rounded-lg px-3 py-2 text-xs font-medium text-slate-400 hover:bg-slate-700 hover:text-slate-100 transition-colors"
                        >
                            プロフィール
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="block w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-400 hover:bg-slate-700 hover:text-slate-100 transition-colors"
                        >
                            ログアウト
                        </Link>
                    </div>
                </div>
            </div>
        </aside>

        <!-- モバイル用ヘッダー -->
        <div class="lg:hidden fixed top-0 left-0 right-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-sm">
            <button
                type="button"
                @click="sidebarOpen = true"
                class="rounded-lg p-2 text-slate-600 hover:bg-slate-100"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <Link :href="route('dashboard')" class="flex items-center gap-2">
                <ApplicationLogo class="block h-7 w-auto text-slate-800" />
                <span class="text-sm font-semibold text-slate-800">設備保全</span>
            </Link>
            <div class="w-10" />
        </div>

        <!-- モバイル用サイドバーオーバーレイ -->
        <div
            v-show="sidebarOpen"
            class="lg:hidden fixed inset-0 z-40"
            aria-modal="true"
        >
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
                @click="sidebarOpen = false"
            />
            <aside class="fixed inset-y-0 left-0 w-72 max-w-[85vw] flex flex-col bg-slate-800 border-r border-slate-700 shadow-xl">
                <div class="flex h-14 items-center justify-between px-4 border-b border-slate-700">
                    <span class="text-sm font-semibold text-slate-100">メニュー</span>
                    <button
                        type="button"
                        @click="sidebarOpen = false"
                        class="rounded-lg p-2 text-slate-400 hover:text-white hover:bg-slate-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto py-4 px-3">
                    <nav class="space-y-0.5">
                        <SidebarNavLink
                            :href="route('dashboard')"
                            :active="isActive('dashboard')"
                            @click="sidebarOpen = false"
                        >
                            <span>ダッシュボード</span>
                        </SidebarNavLink>
                        <SidebarNavLink
                            :href="route('work.works.index')"
                            :active="isActive('work.works.index')"
                            @click="sidebarOpen = false"
                        >
                            <span>作業</span>
                        </SidebarNavLink>
                        <SidebarNavLink
                            :href="route('master.top')"
                            :active="isActive('master.index')"
                            @click="sidebarOpen = false"
                        >
                            <span>マスタ</span>
                        </SidebarNavLink>
                        <div class="border-t border-slate-700 pt-2 mt-2 pl-3 space-y-0.5">
                            <Link
                                v-for="child in masterChildren"
                                :key="child.href"
                                :href="child.href"
                                @click="sidebarOpen = false"
                                :class="[
                                    'block py-2 text-sm',
                                    child.active ? 'text-emerald-400' : 'text-slate-400 hover:text-slate-200',
                                ]"
                            >
                                {{ child.label }}
                            </Link>
                        </div>
                    </nav>
                </div>
                <div class="border-t border-slate-700 p-3">
                    <div class="rounded-lg bg-slate-700/50 px-3 py-2 mb-2">
                        <p class="text-xs font-medium text-slate-200">{{ $page.props.auth.user.name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $page.props.auth.user.email }}</p>
                    </div>
                    <Link
                        :href="route('profile.edit')"
                        @click="sidebarOpen = false"
                        class="block rounded-lg px-3 py-2 text-xs font-medium text-slate-400 hover:bg-slate-700 hover:text-slate-100"
                    >
                        プロフィール
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        @click="sidebarOpen = false"
                        class="block w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-400 hover:bg-slate-700 hover:text-slate-100"
                    >
                        ログアウト
                    </Link>
                </div>
            </aside>
        </div>

        <!-- メインコンテンツ -->
        <div
            class="flex flex-1 flex-col transition-[margin] duration-200"
            :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
        >
            <header
                v-if="$slots.header"
                class="border-b border-slate-200 bg-white/80 backdrop-blur-sm"
            >
                <div class="px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1 pt-14 lg:pt-0">
                <div class="px-4 py-6 sm:px-6 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>

        <!-- フラッシュメッセージ（右下固定・自動フェードアウト） -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-if="flashMessage && flashVisible"
                :class="{
                    'bg-emerald-50 text-emerald-800 border-emerald-200': flashMessage.type === 'success' || flashMessage.type === 'status',
                    'bg-red-50 text-red-800 border-red-200': flashMessage.type === 'error',
                }"
                class="fixed bottom-4 right-4 z-50 max-w-sm rounded-xl border px-4 py-3 text-sm font-medium shadow-lg"
            >
                {{ flashMessage.text }}
            </div>
        </Transition>
    </div>
</template>
