<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id()->comment('業者ID');
            $table->string('name')->comment('業者名');
            $table->boolean('is_active')->default(true)->comment('有効');
            $table->unsignedInteger('sort_order')->default(0)->comment('並び順');
            $table->timestamps();
            $table->comment('業者マスタ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
