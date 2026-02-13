<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkPriority;
use App\Models\WorkPurpose;
use App\Models\WorkStatus;
use Illuminate\Database\Seeder;

class WorkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $equipment = Equipment::first();
        $workStatus = WorkStatus::first();
        $workPriority = WorkPriority::first();
        $workPurpose = WorkPurpose::first();
        $user = User::first();

        if (! $equipment || ! $workStatus || ! $workPriority || ! $workPurpose || ! $user) {
            return;
        }

        $additionalUser = User::skip(1)->first();
        $now = now();
        $today = $now->toDateString();

        $items = [
            [
                'title'                  => 'サンプル作業：定期点検',
                'equipment_id'           => $equipment->id,
                'work_status_id'         => $workStatus->id,
                'work_priority_id'       => $workPriority->id,
                'work_purpose_id'        => $workPurpose->id,
                'assigned_user_id'       => $user->id,
                'additional_user_id'     => $additionalUser?->id,
                'production_stop_minutes' => 60,
                'occurred_at'            => $now->copy()->subDays(2),
                'started_at'             => $now->copy()->subDays(2)->setTime(9, 0),
                'completed_at'           => $now->copy()->subDays(2)->setTime(11, 0),
                'note'                   => '月次定期点検を実施しました。',
            ],
            [
                'title'                  => 'サンプル作業：部品交換',
                'equipment_id'           => $equipment->id,
                'work_status_id'         => $workStatus->id,
                'work_priority_id'       => $workPriority->id,
                'work_purpose_id'        => $workPurpose->id,
                'assigned_user_id'       => $user->id,
                'additional_user_id'     => null,
                'production_stop_minutes' => 120,
                'occurred_at'            => $now->copy()->subDay(),
                'started_at'             => $now->copy()->subDay()->setTime(10, 0),
                'completed_at'           => null,
                'note'                   => '摩耗部品の交換作業。',
            ],
            [
                'title'                  => 'サンプル作業：故障修理',
                'equipment_id'           => Equipment::skip(1)->first()?->id ?? $equipment->id,
                'work_status_id'         => WorkStatus::skip(1)->first()?->id ?? $workStatus->id,
                'work_priority_id'       => WorkPriority::skip(2)->first()?->id ?? $workPriority->id,
                'work_purpose_id'        => $workPurpose->id,
                'assigned_user_id'       => $user->id,
                'additional_user_id'     => null,
                'production_stop_minutes' => 180,
                'occurred_at'            => $now,
                'started_at'             => null,
                'completed_at'           => null,
                'note'                   => '故障のため修理対応中。',
            ],
        ];

        foreach ($items as $item) {
            Work::firstOrCreate(
                [
                    'title'          => $item['title'],
                    'equipment_id'   => $item['equipment_id'],
                    'occurred_at'    => $item['occurred_at'],
                ],
                $item
            );
        }
    }
}
