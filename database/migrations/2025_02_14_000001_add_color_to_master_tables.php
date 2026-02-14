<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 作業ステータス、優先度、作業目的、作業タグ、修理内容、添付種別、操作履歴種別、費用カテゴリ、ユーザーに色情報を追加
     */
    public function up()
    {
        $tables = [
            'work_statuses',
            'work_priorities',
            'work_purposes',
            'work_content_tags',
            'repair_types',
            'attachment_types',
            'work_activity_types',
            'work_cost_categories',
            'users',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('color', 7)->nullable()->after('name')->comment('表示色（HEX）');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $tables = [
            'work_statuses',
            'work_priorities',
            'work_purposes',
            'work_content_tags',
            'repair_types',
            'attachment_types',
            'work_activity_types',
            'work_cost_categories',
            'users',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
