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
        Schema::create('work_attachments', function (Blueprint $table) {
            $table->id()->comment('添付ID');
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete()->comment('作業ID');
            $table->foreignId('work_content_id')->nullable()->constrained('work_contents')->nullOnDelete()->comment('作業内容ID');
            $table->foreignId('attachment_type_id')->constrained('attachment_types')->restrictOnDelete()->comment('添付種別');

            $table->string('path')->comment('保存パス');
            $table->string('original_name')->nullable()->comment('元ファイル名');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete()->comment('アップロード者');

            $table->timestamps();
            $table->comment('添付ファイル');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('work_attachments');
    }
};
