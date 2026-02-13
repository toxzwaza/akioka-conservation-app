<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $defaults = [
            'status' => '稼働中',
            'model_number' => null,
            'installed_at' => null,
            'vendor_contact' => null,
            'manufacturer' => null,
            'note' => null,
        ];

        // 親なし設備（ルート）
        $rootNames = [
            'APS', 'FBOX', 'エプロンショット', 'ハンガーショット', 'フラン', 'ループショット',
            '仕上げ', '出荷検査', '塗装場', '砂処理', '電気炉',
        ];
        foreach ($rootNames as $name) {
            Equipment::firstOrCreate(
                ['name' => $name, 'parent_id' => null],
                array_merge($defaults, ['name' => $name])
            );
        }

        // APS を親とする設備
        $aps = Equipment::where('name', 'APS')->whereNull('parent_id')->first();
        if ($aps) {
            foreach (['造形ライン', 'その他'] as $name) {
                Equipment::firstOrCreate(
                    ['name' => $name, 'parent_id' => $aps->id],
                    array_merge($defaults, ['name' => $name, 'parent_id' => $aps->id])
                );
            }
        }

        // 電気炉 を親とする設備
        $denkiro = Equipment::where('name', '電気炉')->whereNull('parent_id')->first();
        if ($denkiro) {
            foreach (['No.1電気炉', '溶湯搬送装置', 'A電気炉', 'その他'] as $name) {
                Equipment::firstOrCreate(
                    ['name' => $name, 'parent_id' => $denkiro->id],
                    array_merge($defaults, ['name' => $name, 'parent_id' => $denkiro->id])
                );
            }
        }
    }
}
