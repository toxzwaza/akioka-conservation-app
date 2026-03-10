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
    repairTypeCounts: { type: Array, default: () => [] },
    tagCounts: { type: Array, default: () => [] },
    repairEquipment: { type: Array, default: () => [] },
    repairMonthly: { type: Object, default: () => ({}) },
    purposeRepair: { type: Array, default: () => [] },
});

const repairLabels = computed(() => props.repairTypeCounts.map((r) => r.name));
const repairSeries = computed(() => props.repairTypeCounts.map((r) => r.count));
const tagLabels = computed(() => props.tagCounts.map((t) => t.name));
const tagSeries = computed(() => props.tagCounts.map((t) => t.count));
</script>

<template>
    <Head title="修理傾向分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-medium uppercase tracking-wider text-slate-500">修理傾向分析</h2>
                    <AnalysisPeriodFilter
                        :from="from"
                        :to="to"
                        :preset="preset"
                        base-route="analysis.repair-trends"
                    />
                </div>
            </section>

            <div class="grid gap-10 lg:grid-cols-2">
                <AnalysisSection title="修理内容（RepairType）別">
                    <AnalysisChartCard :is-empty="!repairTypeCounts.length">
                        <div class="min-h-[260px]">
                            <VueApexCharts
                                type="donut"
                                height="260"
                                :options="{ chart: { fontFamily: 'inherit' }, labels: repairLabels, legend: { position: 'bottom' } }"
                                :series="repairSeries"
                            />
                        </div>
                    </AnalysisChartCard>
                </AnalysisSection>

                <AnalysisSection title="作業タグ（WorkContentTag）別">
                    <AnalysisChartCard :is-empty="!tagCounts.length">
                        <div class="min-h-[260px]">
                            <VueApexCharts
                                type="donut"
                                height="260"
                                :options="{ chart: { fontFamily: 'inherit' }, labels: tagLabels, legend: { position: 'bottom' } }"
                                :series="tagSeries"
                            />
                        </div>
                    </AnalysisChartCard>
                </AnalysisSection>
            </div>

            <AnalysisSection title="修理内容 × 設備">
                <AnalysisTableCard :is-empty="!repairEquipment.length">
                    <div class="max-h-[400px] overflow-y-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">修理内容</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">設備</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">件数</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="(row, i) in repairEquipment"
                                    :key="i"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.repair_name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-800">{{ row.equipment_name }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>

            <AnalysisSection title="作業目的 × 修理内容">
                <AnalysisTableCard :is-empty="!purposeRepair.length">
                    <div class="max-h-[400px] overflow-y-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">作業目的</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">修理内容</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">件数</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="(row, i) in purposeRepair"
                                    :key="i"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.purpose_name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-800">{{ row.repair_name }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>
        </div>
    </AuthenticatedLayout>
</template>
