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
        Schema::table('work_contents', function (Blueprint $table) {
            $table->unsignedInteger('display_order')->default(0)->after('work_id')->comment('表示順');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_contents', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
};
