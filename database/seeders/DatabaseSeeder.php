<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UserSeeder::class,
            EquipmentSeeder::class,
            WorkStatusSeeder::class,
            WorkPrioritySeeder::class,
            WorkPurposeSeeder::class,
            WorkContentTagSeeder::class,
            RepairTypeSeeder::class,
            AttachmentTypeSeeder::class,
            WorkActivityTypeSeeder::class,
            // PartSeeder::class,
            WorkCostCategorySeeder::class,
            // WorkSeeder::class,
            // WorkContentSeeder::class,
            // WorkUsedPartSeeder::class,
            // WorkCostSeeder::class,
        ]);
    }
}
