<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('works', function (Blueprint $table) {
            $table->id()->comment('作業ID');
            $table->string('title')->comment('作業名');

            $table->foreignId('equipment_id')->constrained('equipments')->restrictOnDelete()->comment('設備ID');
            $table->foreignId('work_status_id')->constrained('work_statuses')->restrictOnDelete()->comment('ステータスID');
            $table->foreignId('work_priority_id')->constrained('work_priorities')->restrictOnDelete()->comment('優先度ID');
            $table->foreignId('work_purpose_id')->constrained('work_purposes')->restrictOnDelete()->comment('作業目的ID');

            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete()->comment('主担当');
            $table->foreignId('additional_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('追加担当');

            $table->unsignedInteger('production_stop_minutes')->nullable()->comment('停止時間(分)');
            $table->dateTime('occurred_at')->nullable()->comment('発生日');
            $table->dateTime('started_at')->nullable()->comment('開始日時');
            $table->dateTime('completed_at')->nullable()->comment('完了日時');

            $table->text('note')->nullable()->comment('備考');

            $table->timestamps();
            $table->softDeletes()->comment('削除日時');

            $table->comment('作業');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('works');
    }
};
