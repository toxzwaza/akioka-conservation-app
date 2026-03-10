<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['name' => '部品調達業者A', 'sort_order' => 1],
            ['name' => '外注業者B', 'sort_order' => 2],
            ['name' => 'メンテナンス業者C', 'sort_order' => 3],
        ];

        foreach ($items as $item) {
            Vendor::firstOrCreate(
                ['name' => $item['name']],
                ['sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }
    }
}
