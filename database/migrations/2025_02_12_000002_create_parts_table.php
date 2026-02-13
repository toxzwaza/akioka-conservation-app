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
        Schema::create('parts', function (Blueprint $table) {
            $table->id()->comment('部品ID');
            $table->string('external_id')->nullable()->comment('外部API連携用ID');
            $table->string('part_no')->unique()->comment('部品番号');
            $table->string('name')->comment('部品名称');
            $table->string('manufacturer')->nullable()->comment('メーカー');
            $table->string('model_number')->nullable()->comment('型式');
            $table->string('storage_location')->nullable()->comment('保管場所');
            $table->timestamps();
            $table->comment('部品マスタ');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('parts');
    }
};
