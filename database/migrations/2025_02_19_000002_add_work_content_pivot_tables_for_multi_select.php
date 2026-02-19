<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 作業タグ・修理内容の複数選択対応
     * ピボットテーブルを追加し、既存の単一参照を移行
     */
    public function up()
    {
        Schema::create('work_content_work_content_tag', function (Blueprint $table) {
            $table->foreignId('work_content_id')->constrained('work_contents')->cascadeOnDelete();
            $table->foreignId('work_content_tag_id')->constrained('work_content_tags')->restrictOnDelete();
            $table->primary(['work_content_id', 'work_content_tag_id']);
            $table->comment('作業内容-作業タグ（多対多）');
        });

        Schema::create('work_content_repair_type', function (Blueprint $table) {
            $table->foreignId('work_content_id')->constrained('work_contents')->cascadeOnDelete();
            $table->foreignId('repair_type_id')->constrained('repair_types')->restrictOnDelete();
            $table->primary(['work_content_id', 'repair_type_id']);
            $table->comment('作業内容-修理内容（多対多）');
        });

        // 既存データをピボットへ移行
        $contents = DB::table('work_contents')->whereNotNull('work_content_tag_id')->get();
        foreach ($contents as $c) {
            DB::table('work_content_work_content_tag')->insert([
                'work_content_id' => $c->id,
                'work_content_tag_id' => $c->work_content_tag_id,
            ]);
        }
        $contents = DB::table('work_contents')->whereNotNull('repair_type_id')->get();
        foreach ($contents as $c) {
            DB::table('work_content_repair_type')->insert([
                'work_content_id' => $c->id,
                'repair_type_id' => $c->repair_type_id,
            ]);
        }

        // 旧カラムを削除
        Schema::table('work_contents', function (Blueprint $table) {
            $table->dropForeign(['work_content_tag_id']);
            $table->dropForeign(['repair_type_id']);
        });
        Schema::table('work_contents', function (Blueprint $table) {
            $table->dropColumn(['work_content_tag_id', 'repair_type_id']);
        });
    }

    public function down()
    {
        Schema::table('work_contents', function (Blueprint $table) {
            $table->foreignId('work_content_tag_id')->nullable()->after('work_id')->constrained('work_content_tags')->restrictOnDelete();
            $table->foreignId('repair_type_id')->nullable()->after('work_content_tag_id')->constrained('repair_types')->restrictOnDelete();
        });

        // ピボットの先頭1件を復元
        $tagPivots = DB::table('work_content_work_content_tag')->get()->groupBy('work_content_id');
        foreach ($tagPivots as $workContentId => $rows) {
            $first = $rows->first();
            DB::table('work_contents')->where('id', $workContentId)->update(['work_content_tag_id' => $first->work_content_tag_id]);
        }
        $repairPivots = DB::table('work_content_repair_type')->get()->groupBy('work_content_id');
        foreach ($repairPivots as $workContentId => $rows) {
            $first = $rows->first();
            DB::table('work_contents')->where('id', $workContentId)->update(['repair_type_id' => $first->repair_type_id]);
        }

        Schema::dropIfExists('work_content_repair_type');
        Schema::dropIfExists('work_content_work_content_tag');
    }
};
