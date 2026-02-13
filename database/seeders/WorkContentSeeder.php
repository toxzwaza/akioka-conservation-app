<?php

namespace Database\Seeders;

use App\Models\RepairType;
use App\Models\Work;
use App\Models\WorkContent;
use App\Models\WorkContentTag;
use Illuminate\Database\Seeder;

class WorkContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $works = Work::orderBy('id')->limit(3)->get();
        $tag = WorkContentTag::first();
        $repairType = RepairType::first();

        if (! $tag || ! $repairType) {
            return;
        }

        $now = now();

        foreach ($works as $index => $work) {
            $contents = [
                [
                    'content'             => '外観点検・異音確認を行いました。異常なし。',
                    'started_at'          => $now->copy()->subDays(2)->setTime(9, 0),
                    'ended_at'            => $now->copy()->subDays(2)->setTime(9, 30),
                ],
                [
                    'content'             => '指定箇所の清掃・潤滑を実施。',
                    'started_at'          => $now->copy()->subDays(2)->setTime(9, 30),
                    'ended_at'            => $now->copy()->subDays(2)->setTime(10, 30),
                ],
            ];

            if ($index === 1) {
                $contents = [
                    [
                        'content'    => '旧部品の取り外し作業。',
                        'started_at' => $now->copy()->subDay()->setTime(10, 0),
                        'ended_at'   => $now->copy()->subDay()->setTime(10, 45),
                    ],
                    [
                        'content'    => '新部品の取付・調整。',
                        'started_at' => $now->copy()->subDay()->setTime(10, 45),
                        'ended_at'   => $now->copy()->subDay()->setTime(11, 30),
                    ],
                ];
            }

            if ($index === 2) {
                $contents = [
                    [
                        'content'    => '故障箇所の特定のため調査中。',
                        'started_at' => null,
                        'ended_at'   => null,
                    ],
                ];
            }

            foreach ($contents as $c) {
                WorkContent::firstOrCreate(
                    [
                        'work_id'              => $work->id,
                        'work_content_tag_id'  => $tag->id,
                        'repair_type_id'       => $repairType->id,
                        'content'              => $c['content'],
                    ],
                    array_merge($c, [
                        'work_id'             => $work->id,
                        'work_content_tag_id' => $tag->id,
                        'repair_type_id'      => $repairType->id,
                    ])
                );
            }
        }
    }
}
