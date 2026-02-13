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
        Schema::create('work_costs', function (Blueprint $table) {
            $table->id()->comment('費用ID');
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete()->comment('作業ID');
            $table->foreignId('work_cost_category_id')->constrained('work_cost_categories')->restrictOnDelete()->comment('費用カテゴリ');

            $table->unsignedInteger('amount')->comment('金額');
            $table->string('vendor_name')->nullable()->comment('業者名');
            $table->date('occurred_at')->nullable()->comment('発生日');
            $table->text('note')->nullable()->comment('備考');
            $table->string('file_path')->nullable()->comment('証憑ファイル');

            $table->timestamps();
            $table->comment('作業費用');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('work_costs');
    }
};
