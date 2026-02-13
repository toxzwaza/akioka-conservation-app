<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    public function run()
    {
        $items = [
            ['part_no' => 'P001', 'name' => 'サンプル部品A'],
            ['part_no' => 'P002', 'name' => 'サンプル部品B'],
            ['part_no' => 'P003', 'name' => 'サンプル部品C'],
        ];

        foreach ($items as $item) {
            Part::firstOrCreate(
                ['part_no' => $item['part_no']],
                array_merge($item, ['memo' => null])
            );
        }
    }
}
