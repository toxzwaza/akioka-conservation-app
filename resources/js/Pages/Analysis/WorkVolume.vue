<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AnalysisPeriodFilter from '@/Components/AnalysisPeriodFilter.vue';
import AnalysisSection from '@/Components/AnalysisSection.vue';
import AnalysisChartCard from '@/Components/AnalysisChartCard.vue';
import AnalysisTableCard from '@/Components/AnalysisTableCard.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    preset: { type: String, default: 'month' },
    monthlyWorks: { type: Object, default: () => ({}) },
    statusMonthly: { type: Object, default: () => ({}) },
    equipmentCounts: { type: Array, default: () => [] },
    userCounts: { type: Array, default: () => [] },
    purposeCounts: { type: Array, default: () => [] },
    priorityCounts: { type: Array, default: () => [] },
});

const barOptions = {
    chart: { type: 'bar', fontFamily: 'inherit' },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 6 } },
    dataLabels: { enabled: false },
    xaxis: { categories: [], labels: { style: { colors: '#64748b', fontSize: '12px' } } },
    yaxis: { labels: { style: { colors: '#64748b' } } },
    colors: ['#6366f1'],
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4, xaxis: { lines: { show: false } } },
};

const monthlyChartOptions = computed(() => ({
    ...barOptions,
    xaxis: { ...barOptions.xaxis, categories: Object.keys(props.monthlyWorks) },
}));

const purposeLabels = computed(() => props.purposeCounts.map((p) => p.name));
const purposeSeries = computed(() => props.purposeCounts.map((p) => p.works_count));
const priorityLabels = computed(() => props.priorityCounts.map((p) => p.name));
const prioritySeries = computed(() => props.priorityCounts.map((p) => p.works_count));
</script>

<template>
    <Head title="作業量分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">作業量分析</h2>
                    <AnalysisPeriodFilter
                        :from="from"
                        :to="to"
                        :preset="preset"
                        base-route="analysis.work-volume"
                    />
                </div>
            </section>

            <AnalysisSection title="月別作業推移">
                <AnalysisChartCard :is-empty="Object.keys(monthlyWorks).length === 0">
                    <div class="min-h-[260px]">
                        <VueApexCharts
                            type="bar"
                            height="260"
                            :options="monthlyChartOptions"
                            :series="[{ name: '作業数', data: Object.values(monthlyWorks) }]"
                        />
                    </div>
                </AnalysisChartCard>
            </AnalysisSection>

            <div class="grid gap-6 lg:grid-cols-2">
                <AnalysisSection title="設備別作業数">
                    <AnalysisTableCard :is-empty="!equipmentCounts.length">
                        <div class="max-h-[360px] overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">設備</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">件数</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr
                                        v-for="row in equipmentCounts"
                                        :key="row.id"
                                        class="hover:bg-slate-50"
                                    >
                                        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.works_count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AnalysisTableCard>
                </AnalysisSection>

                <AnalysisSection title="担当者別作業数">
                    <AnalysisTableCard :is-empty="!userCounts.length">
                        <div class="max-h-[360px] overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">担当者</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">件数</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr
                                        v-for="row in userCounts"
                                        :key="row.id"
                                        class="hover:bg-slate-50"
                                    >
                                        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.works_count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AnalysisTableCard>
                </AnalysisSection>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <AnalysisSection title="作業目的別">
                    <AnalysisChartCard :is-empty="!purposeCounts.length">
                        <div class="min-h-[260px]">
                            <VueApexCharts
                                type="donut"
                                height="260"
                                :options="{ chart: { fontFamily: 'inherit' }, labels: purposeLabels, legend: { position: 'bottom' } }"
                                :series="purposeSeries"
                            />
                        </div>
                    </AnalysisChartCard>
                </AnalysisSection>

                <AnalysisSection title="優先度別">
                    <AnalysisChartCard :is-empty="!priorityCounts.length">
                        <div class="min-h-[260px]">
                            <VueApexCharts
                                type="donut"
                                height="260"
                                :options="{ chart: { fontFamily: 'inherit' }, labels: priorityLabels, legend: { position: 'bottom' } }"
                                :series="prioritySeries"
                            />
                        </div>
                    </AnalysisChartCard>
                </AnalysisSection>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
