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
        Schema::table('work_costs', function (Blueprint $table) {
            $table->string('name')->nullable()->after('work_cost_category_id')->comment('名称');
            $table->foreignId('vendor_id')->nullable()->after('amount')->constrained('vendors')->nullOnDelete()->comment('業者ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_costs', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['name', 'vendor_id']);
        });
    }
};
