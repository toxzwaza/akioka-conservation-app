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
        Schema::table('part_user_display_names', function (Blueprint $table) {
            $table->string('display_s_name')->nullable()->after('display_name')->comment('ユーザーが設定した型番・品番');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('part_user_display_names', function (Blueprint $table) {
            $table->dropColumn('display_s_name');
        });
    }
};
