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
    vendorCosts: { type: Array, default: () => [] },
    vendorMonthly: { type: Object, default: () => ({}) },
    categoryVendor: { type: Array, default: () => [] },
});

function formatYen(n) {
    if (n == null) return '—';
    return Number(n).toLocaleString() + '円';
}

const vendorLabels = computed(() => props.vendorCosts.map((v) => v.vendor_name));
const vendorSeries = computed(() => props.vendorCosts.map((v) => Number(v.total)));
</script>

<template>
    <Head title="業者分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-600">業者分析</h2>
                    <AnalysisPeriodFilter
                        :from="from"
                        :to="to"
                        :preset="preset"
                        base-route="analysis.vendors"
                    />
                </div>
            </section>

            <AnalysisSection title="業者別費用">
                <AnalysisChartCard :is-empty="!vendorCosts.length">
                    <div class="min-h-[260px]">
                        <VueApexCharts
                            type="donut"
                            height="260"
                            :options="{ chart: { fontFamily: 'inherit' }, labels: vendorLabels, legend: { position: 'bottom' } }"
                            :series="vendorSeries"
                        />
                    </div>
                </AnalysisChartCard>
            </AnalysisSection>

            <AnalysisSection title="業者別 費用・作業数">
                <AnalysisTableCard :is-empty="!vendorCosts.length">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50/95">
                                <tr>
                                    <th class="px-6 py-5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">業者</th>
                                    <th class="px-6 py-5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">費用合計</th>
                                    <th class="px-6 py-5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">費用発生作業数</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="row in vendorCosts"
                                    :key="row.vendor_name"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.vendor_name }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ formatYen(row.total) }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.works_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>

            <AnalysisSection title="費用カテゴリ × 業者">
                <AnalysisTableCard :is-empty="!categoryVendor.length">
                    <div class="max-h-[420px] overflow-y-auto">
                        <table class="min-w-full">
                            <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur">
                                <tr>
                                    <th class="px-6 py-5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">費用カテゴリ</th>
                                    <th class="px-6 py-5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">業者</th>
                                    <th class="px-6 py-5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">合計</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="(row, i) in categoryVendor"
                                    :key="i"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.category_name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-800">{{ row.vendor_name }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ formatYen(row.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>
        </div>
    </AuthenticatedLayout>
</template>
