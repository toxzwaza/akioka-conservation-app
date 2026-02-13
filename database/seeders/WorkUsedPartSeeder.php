<?php

namespace Database\Seeders;

use App\Models\Part;
use App\Models\Work;
use App\Models\WorkUsedPart;
use Illuminate\Database\Seeder;

class WorkUsedPartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $works = Work::orderBy('id')->limit(2)->get();
        $parts = Part::orderBy('id')->limit(3)->get();

        if ($parts->isEmpty()) {
            return;
        }

        $partIds = $parts->pluck('id')->toArray();

        foreach ($works as $index => $work) {
            $usages = [
                ['part_id' => $partIds[0], 'qty' => 2, 'note' => '点検用消耗品'],
                ['part_id' => $partIds[1], 'qty' => 1, 'note' => null],
            ];

            if ($index === 1 && isset($partIds[2])) {
                $usages = [
                    ['part_id' => $partIds[1], 'qty' => 1, 'note' => '交換部品'],
                    ['part_id' => $partIds[2], 'qty' => 0.5, 'note' => '潤滑剤'],
                ];
            }

            foreach ($usages as $u) {
                WorkUsedPart::firstOrCreate(
                    [
                        'work_id'  => $work->id,
                        'part_id'  => $u['part_id'],
                    ],
                    [
                        'work_id'  => $work->id,
                        'part_id'  => $u['part_id'],
                        'qty'      => $u['qty'],
                        'note'     => $u['note'] ?? null,
                    ]
                );
            }
        }
    }
}
