<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 作業添付資料ファイルに表示名（タグ名）を登録できるようにする
     *
     * @return void
     */
    public function up()
    {
        Schema::table('work_attachments', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('original_name')->comment('表示名（タグ名）');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('work_attachments', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
