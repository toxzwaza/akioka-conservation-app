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
        Schema::create('work_used_parts', function (Blueprint $table) {
            $table->id()->comment('使用部品ID');
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete()->comment('作業ID');
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete()->comment('部品ID');

            $table->decimal('qty', 10, 2)->default(1)->comment('使用数量');
            $table->text('note')->nullable()->comment('備考');

            $table->timestamps();
            $table->comment('使用部品');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('work_used_parts');
    }
};
