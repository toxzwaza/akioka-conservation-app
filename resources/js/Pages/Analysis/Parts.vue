<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AnalysisPeriodFilter from '@/Components/AnalysisPeriodFilter.vue';
import AnalysisSection from '@/Components/AnalysisSection.vue';
import AnalysisTableCard from '@/Components/AnalysisTableCard.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
    preset: { type: String, default: 'month' },
    partStats: { type: Array, default: () => [] },
    equipmentParts: { type: Array, default: () => [] },
});

function partDisplayName(row) {
    return row.part_no ? `${row.part_no} ${row.name || ''}`.trim() : row.name || '—';
}
</script>

<template>
    <Head title="部品使用分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-medium uppercase tracking-wider text-slate-500">部品使用分析</h2>
                    <AnalysisPeriodFilter
                        :from="from"
                        :to="to"
                        :preset="preset"
                        base-route="analysis.parts"
                    />
                </div>
            </section>

            <AnalysisSection title="部品別 使用回数・使用数量">
                <AnalysisTableCard :is-empty="!partStats.length">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50/95">
                                <tr>
                                    <th class="px-6 py-5 text-left text-xs font-medium uppercase tracking-wider text-slate-500">部品</th>
                                    <th class="px-6 py-5 text-right text-xs font-medium uppercase tracking-wider text-slate-500">使用回数</th>
                                    <th class="px-6 py-5 text-right text-xs font-medium uppercase tracking-wider text-slate-500">使用数量</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="row in partStats"
                                    :key="row.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="route('master.parts.show', row.id)"
                                            class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                        >
                                            {{ partDisplayName(row) }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.use_count }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.total_qty }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>

            <AnalysisSection title="設備別 よく使う部品">
                <AnalysisTableCard :is-empty="!equipmentParts.length">
                    <div class="max-h-[420px] overflow-y-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-5 text-left text-xs font-medium uppercase tracking-wider text-slate-500">設備</th>
                                    <th class="px-6 py-5 text-left text-xs font-medium uppercase tracking-wider text-slate-500">部品</th>
                                    <th class="px-6 py-5 text-right text-xs font-medium uppercase tracking-wider text-slate-500">使用数量</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="(row, i) in equipmentParts"
                                    :key="i"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ row.equipment_name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-800">{{ row.part_name }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.total_qty }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>
        </div>
    </AuthenticatedLayout>
</template>
