<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AnalysisCard from '@/Components/AnalysisCard.vue';
import AnalysisPeriodFilter from '@/Components/AnalysisPeriodFilter.vue';
import AnalysisSection from '@/Components/AnalysisSection.vue';
import AnalysisChartCard from '@/Components/AnalysisChartCard.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    preset: { type: String, default: 'month' },
    kpi: {
        type: Object,
        default: () => ({}),
    },
    monthlyWorks: { type: Object, default: () => ({}) },
    statusCounts: { type: Array, default: () => [] },
});

const statusCountsList = computed(() => Array.isArray(props.statusCounts) ? props.statusCounts : []);
const totalWorks = computed(() =>
    statusCountsList.value.reduce((sum, s) => sum + (s.works_count || 0), 0)
);

const chartOptions = computed(() => ({
    chart: { type: 'bar', fontFamily: 'inherit' },
    plotOptions: {
        bar: {
            columnWidth: '55%',
            borderRadius: 6,
        },
    },
    dataLabels: { enabled: false },
    xaxis: {
        categories: Object.keys(props.monthlyWorks),
        labels: { style: { colors: '#64748b', fontSize: '12px' } },
    },
    yaxis: {
        labels: { style: { colors: '#64748b' } },
    },
    colors: ['#6366f1'],
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
    },
}));

const donutSeries = computed(() => statusCountsList.value.map((s) => s.works_count || 0));
const donutOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: statusCountsList.value.map((s) => s.name),
    colors: ['#64748b', '#f59e0b', '#3b82f6', '#8b5cf6', '#94a3b8', '#10b981'],
    legend: { position: 'bottom', fontSize: '13px' },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: { show: true, label: '合計', formatter: () => totalWorks.value },
                },
            },
        },
    },
}));

const links = [
    { href: 'analysis.work-volume', label: '作業量分析', desc: '作業数・ステータス・設備・担当者別' },
    { href: 'analysis.cost', label: '費用分析', desc: '月別・カテゴリ・業者・設備別' },
    { href: 'analysis.equipment', label: '設備分析', desc: '設備別負荷・停止時間・費用' },
    { href: 'analysis.users', label: '担当者分析', desc: '負荷・完了状況・平均日数' },
    { href: 'analysis.parts', label: '部品使用分析', desc: '使用頻度・設備紐づけ' },
    { href: 'analysis.vendors', label: '業者分析', desc: '業者別費用・作業数' },
    { href: 'analysis.repair-trends', label: '修理傾向分析', desc: '修理内容・タグのクロス集計' },
];

function formatNumber(n) {
    if (n == null) return '—';
    return Number(n).toLocaleString();
}
</script>

<template>
    <Head title="分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <!-- ヘッダー -->
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                        分析サマリー
                    </h2>
                    <AnalysisPeriodFilter
                    :from="from"
                    :to="to"
                    :preset="preset"
                    base-route="analysis.index"
                    />
                </div>
            </section>

            <!-- KPI カード -->
            <AnalysisSection title="主要指標">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <AnalysisCard title="作業総数" :value="formatNumber(kpi.totalWorks)" />
                    <AnalysisCard title="完了数" :value="formatNumber(kpi.completedWorks)" />
                    <AnalysisCard
                        title="平均完了日数"
                        :value="kpi.avgCompletionDays != null ? kpi.avgCompletionDays + '日' : '—'"
                    />
                    <AnalysisCard
                        title="総費用"
                        :value="formatNumber(kpi.totalCost) + '円'"
                    />
                    <AnalysisCard
                        title="稼働停止合計"
                        :value="formatNumber(kpi.totalStopMinutes) + '分'"
                    />
                    <AnalysisCard title="主担当者数" :value="formatNumber(kpi.uniqueAssignees)" />
                </div>
            </AnalysisSection>

            <!-- グラフ -->
            <div class="grid gap-6 lg:grid-cols-2">
                <AnalysisSection title="月別作業推移">
                    <AnalysisChartCard :is-empty="Object.keys(monthlyWorks).length === 0">
                        <div class="min-h-[260px]">
                            <VueApexCharts
                                type="bar"
                                height="260"
                                :options="chartOptions"
                                :series="[{ name: '作業数', data: Object.values(monthlyWorks) }]"
                            />
                        </div>
                    </AnalysisChartCard>
                </AnalysisSection>

                <AnalysisSection title="ステータス別構成">
                    <AnalysisChartCard :is-empty="totalWorks === 0">
                        <div class="min-h-[260px]">
                            <VueApexCharts
                                type="donut"
                                height="260"
                                :options="donutOptions"
                                :series="donutSeries"
                            />
                        </div>
                    </AnalysisChartCard>
                </AnalysisSection>
            </div>

            <!-- クイックリンク -->
            <AnalysisSection title="詳細分析へ">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="route(link.href)"
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
                    >
                        <p class="font-medium text-slate-800">{{ link.label }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ link.desc }}</p>
                    </Link>
                </div>
            </AnalysisSection>
        </div>
    </AuthenticatedLayout>
</template>
