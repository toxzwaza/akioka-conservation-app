<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AnalysisPeriodFilter from '@/Components/AnalysisPeriodFilter.vue';
import AnalysisSection from '@/Components/AnalysisSection.vue';
import AnalysisChartCard from '@/Components/AnalysisChartCard.vue';
import AnalysisTableCard from '@/Components/AnalysisTableCard.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    preset: { type: String, default: 'month' },
    monthlyCosts: { type: Object, default: () => ({}) },
    categoryCosts: { type: Array, default: () => [] },
    vendorCosts: { type: Array, default: () => [] },
    equipmentCosts: { type: Array, default: () => [] },
    topWorksByCost: { type: Array, default: () => [] },
});

function formatYen(n) {
    if (n == null) return '—';
    return Number(n).toLocaleString() + '円';
}

const categoryLabels = computed(() => props.categoryCosts.map((c) => c.name));
const categorySeries = computed(() => props.categoryCosts.map((c) => Number(c.total)));

const chartOptions = computed(() => ({
    chart: { type: 'bar', fontFamily: 'inherit' },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 6 } },
    dataLabels: { enabled: false },
    xaxis: {
        categories: Object.keys(props.monthlyCosts),
        labels: { style: { colors: '#64748b', fontSize: '12px' } },
    },
    yaxis: {
        labels: {
            style: { colors: '#64748b' },
            formatter: (v) => (v >= 10000 ? v / 10000 + '万' : v),
        },
    },
    colors: ['#10b981'],
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4, xaxis: { lines: { show: false } } },
}));
</script>

<template>
    <Head title="費用分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-medium uppercase tracking-wider text-slate-600">費用分析</h2>
                    <AnalysisPeriodFilter
                        :from="from"
                        :to="to"
                        :preset="preset"
                        base-route="analysis.cost"
                    />
                </div>
            </section>

            <AnalysisSection title="月別費用推移">
                <AnalysisChartCard :is-empty="Object.keys(monthlyCosts).length === 0">
                    <div class="min-h-[260px]">
                        <VueApexCharts
                            type="bar"
                            height="260"
                            :options="chartOptions"
                            :series="[{ name: '費用', data: Object.values(monthlyCosts).map(Number) }]"
                        />
                    </div>
                </AnalysisChartCard>
            </AnalysisSection>

            <div class="grid gap-6 lg:grid-cols-2">
                <AnalysisSection title="費用カテゴリ別">
                    <AnalysisChartCard :is-empty="!categoryCosts.length">
                        <div class="min-h-[260px]">
                            <VueApexCharts
                                type="donut"
                                height="260"
                                :options="{ chart: { fontFamily: 'inherit' }, labels: categoryLabels, legend: { position: 'bottom' } }"
                                :series="categorySeries"
                            />
                        </div>
                    </AnalysisChartCard>
                </AnalysisSection>

                <AnalysisSection title="業者別費用">
                    <AnalysisTableCard :is-empty="!vendorCosts.length">
                        <div class="max-h-[360px] overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">業者</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">合計</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr v-for="row in vendorCosts" :key="row.name" class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ formatYen(row.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AnalysisTableCard>
                </AnalysisSection>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <AnalysisSection title="設備別費用">
                    <AnalysisTableCard :is-empty="!equipmentCosts.length">
                        <div class="max-h-[360px] overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">設備</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">合計</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr v-for="row in equipmentCosts" :key="row.id" class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ formatYen(row.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AnalysisTableCard>
                </AnalysisSection>

                <AnalysisSection title="費用上位作業">
                    <AnalysisTableCard :is-empty="!topWorksByCost.length">
                        <div class="max-h-[360px] overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">作業</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">費用</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr v-for="work in topWorksByCost" :key="work.id" class="hover:bg-slate-50">
                                        <td class="px-4 py-3">
                                            <Link
                                                :href="route('work.works.show', work.id)"
                                                class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                            >
                                                {{ work.title }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ formatYen(work.work_costs_sum_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AnalysisTableCard>
                </AnalysisSection>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
