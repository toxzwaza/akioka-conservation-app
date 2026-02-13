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
        Schema::create('equipment_parts', function (Blueprint $table) {
            $table->id()->comment('設備部品紐付けID');
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete()->comment('設備ID');
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete()->comment('部品ID');
            $table->text('note')->nullable()->comment('メモ');
            $table->timestamps();

            $table->unique(['equipment_id', 'part_id']);
            $table->comment('設備と部品の対応');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipment_parts');
    }
};
