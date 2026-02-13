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
        Schema::create('work_activities', function (Blueprint $table) {
            $table->id()->comment('履歴ID');
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete()->comment('作業ID');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('操作ユーザー');
            $table->foreignId('work_activity_type_id')->constrained('work_activity_types')->restrictOnDelete()->comment('操作種別');

            $table->text('message')->nullable()->comment('コメント');
            $table->json('meta')->nullable()->comment('差分JSON');
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');

            $table->comment('操作履歴');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('work_activities');
    }
};
