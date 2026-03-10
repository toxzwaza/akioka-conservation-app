<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import SidebarNavItem from '@/Components/SidebarNavItem.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'sidebar-collapsed';
const FLASH_DURATION_MS = 5000;
const SCROLL_THRESHOLD = 10;

const page = usePage();
const lastScrollY = ref(0);
const headerVisible = ref(true);
const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const flashVisible = ref(false);

function onScroll() {
    const y = window.scrollY || document.documentElement.scrollTop;
    if (Math.abs(y - lastScrollY.value) < SCROLL_THRESHOLD) return;
    headerVisible.value = y < lastScrollY.value || y < 50;
    lastScrollY.value = y;
}

onMounted(() => {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored !== null) sidebarCollapsed.value = stored === 'true';
    window.addEventListener('scroll', onScroll, { passive: true });
});
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
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
        { key: 'parts', label: '部品', href: () => route('master.parts.index') },
        { key: 'equipments', label: '設備', href: () => route('master.equipments.index') },
        { key: 'vendors', label: '業者', href: () => route('master.vendors.index') },
        { key: 'work-statuses', label: '作業ステータス', href: () => route('master.index', { masterKey: 'work-statuses' }) },
        { key: 'work-priorities', label: '優先度', href: () => route('master.index', { masterKey: 'work-priorities' }) },
        { key: 'work-purposes', label: '作業目的', href: () => route('master.index', { masterKey: 'work-purposes' }) },
        { key: 'work-content-tags', label: '作業タグ', href: () => route('master.index', { masterKey: 'work-content-tags' }) },
        { key: 'repair-types', label: '修理内容', href: () => route('master.index', { masterKey: 'repair-types' }) },
        { key: 'attachment-types', label: '添付種別', href: () => route('master.index', { masterKey: 'attachment-types' }) },
        { key: 'work-activity-types', label: '操作履歴種別', href: () => route('master.index', { masterKey: 'work-activity-types' }) },
        { key: 'work-cost-categories', label: '費用カテゴリ', href: () => route('master.index', { masterKey: 'work-cost-categories' }) },
        { key: 'users', label: 'ユーザー', href: () => route('master.users.index') },
        { key: 'api-test', label: 'APIテスト', href: () => route('api-test.index') },
    ];
    return list.map(({ key, label, href }) => ({
        href: href(),
        label,
        active: isMasterChildActive(key),
    }));
});

// パンくずリスト（URLパスとpropsから生成）
const breadcrumbs = computed(() => {
    const path = page.url.replace(/^\//, '').replace(/\/$/, '') || '';
    const segments = path ? path.split('/') : [];
    const props = page.props;
    const items = [];

    if (!segments.length || path === '') {
        return [{ label: 'ダッシュボード', href: route('dashboard') }];
    }

    const first = segments[0];
    if (first === 'work') {
        items.push({ label: '作業', href: route('work.works.index') });
        if (segments[1] === 'works') {
            items.push({ label: '作業一覧', href: route('work.works.index') });
            if (segments[2] === 'create') {
                items.push({ label: '新規登録' });
            } else if (segments[2] && props.work) {
                items.push({ label: props.work.title || `#${segments[2]}` });
            }
        }
        return items.length ? items : [{ label: '作業', href: route('work.works.index') }];
    }

    if (first === 'master') {
        items.push({ label: 'マスタ', href: route('master.top') });
        const masterLabels = {
            parts: '部品',
            equipments: '設備',
            vendors: '業者',
            users: 'ユーザー',
            'work-statuses': '作業ステータス',
            'work-priorities': '優先度',
            'work-purposes': '作業目的',
            'work-content-tags': '作業タグ',
            'repair-types': '修理内容',
            'attachment-types': '添付種別',
            'work-activity-types': '操作履歴種別',
            'work-cost-categories': '費用カテゴリ',
        };
        const second = segments[1];
        const label = masterLabels[second] || second;
        if (second) {
            const idxHref = second === 'parts' ? route('master.parts.index')
                : second === 'equipments' ? route('master.equipments.index')
                : second === 'vendors' ? route('master.vendors.index')
                : second === 'users' ? route('master.users.index')
                : route('master.index', { masterKey: second });
            if (segments[2] === 'create') {
                items.push({ label, href: idxHref });
                items.push({ label: '新規登録' });
            } else if (segments[3] === 'edit') {
                items.push({ label, href: idxHref });
                const name = props.part?.display_name ?? props.equipment?.name ?? props.vendor?.name ?? props.user?.name;
                items.push({ label: name || `#${segments[2]}` });
                items.push({ label: '編集' });
            } else if (segments[2] && !['create', 'edit'].includes(segments[2])) {
                items.push({ label, href: idxHref });
                const name = props.part?.display_name ?? props.equipment?.name ?? props.vendor?.name ?? props.user?.name;
                items.push({ label: name || `#${segments[2]}` });
            } else {
                items.push({ label, href: idxHref });
            }
        }
        return items.length ? items : [{ label: 'マスタ', href: route('master.top') }];
    }

    if (first === 'profile') {
        return [{ label: 'プロフィール' }];
    }
    if (first === 'api-test') {
        return [{ label: 'APIテスト', href: route('api-test.index') }];
    }

    return [{ label: path || 'ダッシュボード', href: route('dashboard') }];
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
            <!-- 固定ヘッダー（上スクロールで表示、下スクロールで非表示） -->
            <header
                class="fixed top-14 left-0 right-0 z-20 lg:top-0 border-b border-slate-200 bg-white/95 backdrop-blur-sm shadow-sm transition-transform duration-300 ease-out"
                :class="[
                    sidebarCollapsed ? 'lg:left-20' : 'lg:left-64',
                    headerVisible ? 'translate-y-0' : '-translate-y-full'
                ]"
            >
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8 min-h-[65px]">
                    <Breadcrumb :items="breadcrumbs" />
                    <div class="flex items-center shrink-0">
                        <Link
                            v-if="$page.props.auth?.user?.id"
                            :href="route('master.users.show', $page.props.auth.user.id)"
                            class="flex flex-col items-end text-right hover:opacity-80 transition-opacity"
                        >
                            <p class="text-sm font-medium text-slate-800 truncate max-w-[180px] sm:max-w-[240px]">
                                {{ $page.props.auth?.user?.name ?? 'ユーザー' }}
                            </p>
                            <p class="text-xs text-slate-500 truncate max-w-[180px] sm:max-w-[240px]">
                                {{ $page.props.auth?.user?.email ?? '' }}
                            </p>
                        </Link>
                        <div
                            v-else
                            class="flex flex-col items-end text-right"
                        >
                            <p class="text-sm font-medium text-slate-800 truncate max-w-[180px] sm:max-w-[240px]">
                                {{ $page.props.auth?.user?.name ?? 'ユーザー' }}
                            </p>
                            <p class="text-xs text-slate-500 truncate max-w-[180px] sm:max-w-[240px]">
                                {{ $page.props.auth?.user?.email ?? '' }}
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 pt-[121px] lg:pt-[65px]">
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
