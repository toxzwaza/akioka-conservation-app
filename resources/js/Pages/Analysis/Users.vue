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
    userStats: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="担当者分析" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-8">
            <section>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">担当者分析</h2>
                    <AnalysisPeriodFilter
                    :from="from"
                    :to="to"
                    :preset="preset"
                    base-route="analysis.users"
                />
            </div>

            <AnalysisSection title="担当者別 作業数・完了数・平均完了日数">
                <AnalysisTableCard :is-empty="!userStats.length">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">担当者</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">作業数</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">完了数</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">平均完了日数</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="row in userStats"
                                    :key="row.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="route('master.users.show', row.id)"
                                            class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                        >
                                            {{ row.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.works_count }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">{{ row.completed_count ?? 0 }}</td>
                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600">
                                        {{ row.avg_completion_days != null ? row.avg_completion_days + '日' : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AnalysisTableCard>
            </AnalysisSection>
        </section>
        </div>
    </AuthenticatedLayout>
</template>
