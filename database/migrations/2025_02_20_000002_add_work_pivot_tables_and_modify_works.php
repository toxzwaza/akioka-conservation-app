<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 作業-作業目的、作業-追加担当、作業-設備のピボットテーブル作成
     * works テーブルから不要カラムを削除
     */
    public function up(): void
    {
        // 1. 作業-作業目的 ピボット
        Schema::create('work_work_purpose', function (Blueprint $table) {
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete();
            $table->foreignId('work_purpose_id')->constrained('work_purposes')->restrictOnDelete();
            $table->primary(['work_id', 'work_purpose_id']);
            $table->comment('作業-作業目的（多対多）');
        });

        // 2. 作業-追加担当 ピボット
        Schema::create('work_additional_user', function (Blueprint $table) {
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->comment('並び順');
            $table->primary(['work_id', 'user_id']);
            $table->comment('作業-追加担当（多対多）');
        });

        // 3. 作業-設備 ピボット
        Schema::create('work_equipment', function (Blueprint $table) {
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->comment('並び順');
            $table->primary(['work_id', 'equipment_id']);
            $table->comment('作業-設備（多対多）');
        });

        // 4. 既存データをピボットへ移行
        $works = DB::table('works')->get();
        foreach ($works as $work) {
            if (! empty($work->work_purpose_id)) {
                DB::table('work_work_purpose')->insertOrIgnore([
                    'work_id' => $work->id,
                    'work_purpose_id' => $work->work_purpose_id,
                ]);
            }
            if (! empty($work->additional_user_id)) {
                DB::table('work_additional_user')->insertOrIgnore([
                    'work_id' => $work->id,
                    'user_id' => $work->additional_user_id,
                    'sort_order' => 0,
                ]);
            }
            if (! empty($work->equipment_id)) {
                DB::table('work_equipment')->insertOrIgnore([
                    'work_id' => $work->id,
                    'equipment_id' => $work->equipment_id,
                    'sort_order' => 0,
                ]);
            }
        }

        // 5. works から FK を解除してカラム削除
        Schema::table('works', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
            $table->dropForeign(['work_purpose_id']);
            $table->dropForeign(['additional_user_id']);
        });
        Schema::table('works', function (Blueprint $table) {
            $table->dropColumn(['equipment_id', 'work_purpose_id', 'additional_user_id', 'started_at']);
        });

        // 6. work_priority_id を nullable に
        Schema::table('works', function (Blueprint $table) {
            $table->dropForeign(['work_priority_id']);
        });
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE works MODIFY work_priority_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('ALTER TABLE works RENAME COLUMN work_priority_id TO work_priority_id_old');
            Schema::table('works', function (Blueprint $table) {
                $table->unsignedBigInteger('work_priority_id')->nullable()->after('work_status_id');
            });
            DB::statement('UPDATE works SET work_priority_id = work_priority_id_old WHERE work_priority_id_old IS NOT NULL');
            Schema::table('works', function (Blueprint $table) {
                $table->dropColumn('work_priority_id_old');
            });
        }
        Schema::table('works', function (Blueprint $table) {
            $table->foreign('work_priority_id')->references('id')->on('work_priorities')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // work_priority_id を required に戻す（簡略化: MySQL のみ）
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE works MODIFY work_priority_id BIGINT UNSIGNED NOT NULL');
        }

        // works にカラムを復元
        Schema::table('works', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('title')->constrained('equipments')->restrictOnDelete();
            $table->foreignId('work_purpose_id')->nullable()->after('work_priority_id')->constrained('work_purposes')->restrictOnDelete();
            $table->foreignId('additional_user_id')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable()->after('occurred_at');
        });

        // ピボットから先頭1件を復元
        $equipmentPivots = DB::table('work_equipment')->orderBy('sort_order')->get()->groupBy('work_id');
        foreach ($equipmentPivots as $workId => $rows) {
            $first = $rows->first();
            DB::table('works')->where('id', $workId)->update(['equipment_id' => $first->equipment_id]);
        }
        $purposePivots = DB::table('work_work_purpose')->get()->groupBy('work_id');
        foreach ($purposePivots as $workId => $rows) {
            $first = $rows->first();
            DB::table('works')->where('id', $workId)->update(['work_purpose_id' => $first->work_purpose_id]);
        }
        $userPivots = DB::table('work_additional_user')->orderBy('sort_order')->get()->groupBy('work_id');
        foreach ($userPivots as $workId => $rows) {
            $first = $rows->first();
            DB::table('works')->where('id', $workId)->update(['additional_user_id' => $first->user_id]);
        }

        Schema::dropIfExists('work_equipment');
        Schema::dropIfExists('work_additional_user');
        Schema::dropIfExists('work_work_purpose');
    }
};
