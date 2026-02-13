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
        Schema::create('equipments', function (Blueprint $table) {
            $table->id()->comment('設備ID');
            $table->foreignId('parent_id')->nullable()
                ->constrained('equipments')->nullOnDelete()
                ->comment('親設備ID');

            $table->string('name')->comment('設備名');
            $table->string('model_number')->nullable()->comment('型式');
            $table->string('status')->comment('設備状態');
            $table->date('installed_at')->nullable()->comment('設置日');
            $table->string('vendor_contact')->nullable()->comment('対応業者');
            $table->string('manufacturer')->nullable()->comment('製造業者');
            $table->text('note')->nullable()->comment('備考');

            $table->timestamps();
            $table->comment('設備マスタ');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipments');
    }
};
