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
    equipmentStats: { type: Array, default: () => [] },
});

function formatMinutes(mins) {
    if (mins == null || mins === 0) return '—';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0) return `${h}時間${m}分`;
    return `${m}分`;
}

function formatYen(n) {
    if (n == null || n === 0) return '—';
    return Number(n).toLocaleString() + '円';
}

function equipmentLabel(row) {
    return row.parent_name ? `${row.parent_name} › ${row.name}` : row.name;
}
</script>

<template>
    <Head title="設備分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">設備分析</h2>
                    <AnalysisPeriodFilter
                        :from="from"
                        :to="to"
                        :preset="preset"
                        base-route="analysis.equipment"
                    />
                </div>
            </section>

            <AnalysisSection title="設備別 作業数・稼働停止時間・費用">
                <AnalysisTableCard :is-empty="!equipmentStats.length">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-medium uppercase tracking-wider text-slate-600">設備</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">作業数</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">稼働停止時間</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">費用</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="row in equipmentStats"
                                    :key="row.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="route('master.equipments.show', row.id)"
                                            class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                        >
                                            {{ equipmentLabel(row) }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.works_count }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ formatMinutes(row.total_stop_minutes) }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ formatYen(row.total_cost) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>
        </div>
    </AuthenticatedLayout>
</template>
