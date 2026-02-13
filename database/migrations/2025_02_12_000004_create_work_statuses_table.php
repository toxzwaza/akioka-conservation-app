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
        Schema::create('work_statuses', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('name')->comment('表示名');
            $table->unsignedInteger('sort_order')->default(0)->comment('並び順');
            $table->boolean('is_active')->default(true)->comment('有効');
            $table->timestamps();
            $table->comment('作業ステータス');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('work_statuses');
    }
};
