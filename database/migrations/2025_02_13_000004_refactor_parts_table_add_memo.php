<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * parts テーブル: 未使用カラム削除、メモ追加
     *
     * @return void
     */
    public function up()
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn(['manufacturer', 'model_number', 'storage_location']);
            $table->text('memo')->nullable()->after('name')->comment('メモ');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('memo');
            $table->string('manufacturer')->nullable()->comment('メーカー');
            $table->string('model_number')->nullable()->comment('型式');
            $table->string('storage_location')->nullable()->comment('保管場所');
        });
    }
};
