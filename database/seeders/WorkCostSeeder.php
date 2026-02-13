<?php

namespace Database\Seeders;

use App\Models\Work;
use App\Models\WorkCost;
use App\Models\WorkCostCategory;
use Illuminate\Database\Seeder;

class WorkCostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $works = Work::orderBy('id')->limit(3)->get();
        $categories = WorkCostCategory::orderBy('id')->limit(2)->get();

        if ($categories->isEmpty()) {
            return;
        }

        $now = now()->toDateString();

        foreach ($works as $index => $work) {
            $costs = [
                [
                    'work_cost_category_id' => $categories[0]->id,
                    'amount'                 => 5000,
                    'vendor_name'            => '部品調達業者A',
                    'occurred_at'            => $now,
                    'note'                   => '点検用部品代',
                ],
            ];

            if ($index >= 1) {
                $costs[] = [
                    'work_cost_category_id' => $categories[1]->id,
                    'amount'                 => 15000,
                    'vendor_name'            => '外注業者B',
                    'occurred_at'            => $now,
                    'note'                   => '作業外注費',
                ];
            }

            foreach ($costs as $c) {
                WorkCost::firstOrCreate(
                    [
                        'work_id'              => $work->id,
                        'work_cost_category_id' => $c['work_cost_category_id'],
                        'amount'               => $c['amount'],
                        'occurred_at'          => $c['occurred_at'],
                    ],
                    array_merge($c, ['work_id' => $work->id])
                );
            }
        }
    }
}
