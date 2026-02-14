<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    statusCounts: {
        type: Array,
        default: () => [],
    },
    myAssignedWorks: {
        type: Array,
        default: () => [],
    },
    recentWorks: {
        type: Array,
        default: () => [],
    },
});

// null/undefined 対策（サーバーからのデータ形式に依存しない）
const statusCountsList = computed(() => Array.isArray(props.statusCounts) ? props.statusCounts : []);
const myAssignedWorksList = computed(() => Array.isArray(props.myAssignedWorks) ? props.myAssignedWorks : []);
const recentWorksList = computed(() => Array.isArray(props.recentWorks) ? props.recentWorks : []);

// ステータスカード用のカラーパレット（sort_order に対応）
const statusColors = [
    'bg-slate-100 border-slate-200 text-slate-700', // 未着手
    'bg-amber-50 border-amber-200 text-amber-800',  // 処理中
    'bg-blue-50 border-blue-200 text-blue-800',     // 確認中
    'bg-violet-50 border-violet-200 text-violet-800', // 施工予定
    'bg-slate-50 border-slate-200 text-slate-600',  // 保留
    'bg-emerald-50 border-emerald-200 text-emerald-800', // 完了
];

const totalWorks = computed(() =>
    statusCountsList.value.reduce((sum, s) => sum + (s.works_count || 0), 0)
);

// ApexCharts 用データ
const chartSeries = computed(() =>
    statusCountsList.value.map((s) => s.works_count || 0)
);

const chartOptions = computed(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'inherit',
    },
    labels: statusCountsList.value.map((s) => s.name),
    colors: ['#64748b', '#f59e0b', '#3b82f6', '#8b5cf6', '#94a3b8', '#10b981'],
    legend: {
        position: 'bottom',
        fontSize: '13px',
        labels: {
            colors: '#475569',
        },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: '合計',
                        fontSize: '14px',
                        color: '#64748b',
                        formatter: () => totalWorks.value,
                    },
                },
            },
        },
    },
    dataLabels: {
        enabled: true,
        // ApexCharts の pie/donut では val は既にパーセンテージ(0-100)で渡される
        formatter: (val) => (typeof val === 'number' ? Math.round(val) + '%' : '0%'),
    },
    stroke: {
        width: 2,
        colors: ['#fff'],
    },
}));

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
}
</script>

<template>
    <Head title="ダッシュボード" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">ダッシュボード</h1>
                <Link :href="route('work.works.create')">
                    <PrimaryButton>作業新規登録</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-full space-y-8">
            <!-- セクション1: ステータス別作業件数 -->
            <section>
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">
                    ステータス別作業件数
                </h2>
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- ステータスカード -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2">
                        <div
                            v-for="(status, index) in statusCountsList"
                            :key="status.id"
                            :class="[
                                statusColors[index % statusColors.length],
                                'rounded-xl border p-4 transition-shadow hover:shadow-md',
                            ]"
                        >
                            <p class="text-xs font-medium opacity-80">{{ status.name }}</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums">
                                {{ status.works_count ?? 0 }}
                            </p>
                        </div>
                    </div>
                    <!-- 円グラフ -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1"
                    >
                        <div v-if="totalWorks > 0" class="min-h-[260px]">
                            <VueApexCharts
                                type="donut"
                                height="260"
                                :options="chartOptions"
                                :series="chartSeries"
                            />
                        </div>
                        <div
                            v-else
                            class="flex min-h-[260px] items-center justify-center text-slate-400"
                        >
                            データがありません
                        </div>
                    </div>
                </div>
            </section>

            <!-- セクション2: 自分の担当作業 -->
            <section>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                        自分の担当作業
                        <span class="ml-2 font-normal normal-case text-slate-600">
                            （未着手・処理中を優先）
                        </span>
                    </h2>
                    <Link
                        :href="route('work.works.index')"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        作業一覧へ →
                    </Link>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        作業名
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        設備
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        ステータス
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        優先度
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        登録日
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="work in myAssignedWorksList"
                                    :key="work.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="route('work.works.show', work.id)"
                                            class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                        >
                                            {{ work.title }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ work.equipment?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ work.work_status?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ work.work_priority?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ formatDate(work.created_at) }}
                                    </td>
                                </tr>
                                <tr v-if="!myAssignedWorksList.length">
                                    <td
                                        colspan="5"
                                        class="px-4 py-8 text-center text-sm text-slate-500"
                                    >
                                        担当作業はありません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- セクション3: 直近の作業一覧 -->
            <section>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                        直近の作業一覧
                    </h2>
                    <Link
                        :href="route('work.works.index')"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        作業一覧へ →
                    </Link>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        ID
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        作業名
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        設備
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        ステータス
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        主担当
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600"
                                    >
                                        登録日
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="work in recentWorksList"
                                    :key="work.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ work.id }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="route('work.works.show', work.id)"
                                            class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                        >
                                            {{ work.title }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ work.equipment?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ work.work_status?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ work.assigned_user?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        {{ formatDate(work.created_at) }}
                                    </td>
                                </tr>
                                <tr v-if="!recentWorksList.length">
                                    <td
                                        colspan="6"
                                        class="px-4 py-8 text-center text-sm text-slate-500"
                                    >
                                        登録された作業はありません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
