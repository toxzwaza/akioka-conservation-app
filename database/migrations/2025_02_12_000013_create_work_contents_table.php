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
        Schema::create('work_contents', function (Blueprint $table) {
            $table->id()->comment('作業内容ID');
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete()->comment('作業ID');
            $table->foreignId('work_content_tag_id')->constrained('work_content_tags')->restrictOnDelete()->comment('タグID');
            $table->foreignId('repair_type_id')->constrained('repair_types')->restrictOnDelete()->comment('修理内容ID');

            $table->text('content')->comment('内容');
            $table->dateTime('started_at')->nullable()->comment('開始日時');
            $table->dateTime('ended_at')->nullable()->comment('終了日時');

            $table->timestamps();
            $table->comment('作業内容');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('work_contents');
    }
};
