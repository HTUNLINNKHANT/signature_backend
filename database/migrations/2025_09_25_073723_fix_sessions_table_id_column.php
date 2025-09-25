<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if sessions table exists and if id column needs to be changed
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                // Ensure id column is string type (Laravel sessions require string IDs)
                $table->string('id')->change();
            });
        }
    }

    public function down()
    {
        // Revert id column back to bigInteger if needed
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->bigInteger('id')->change();
            });
        }
    }
};
