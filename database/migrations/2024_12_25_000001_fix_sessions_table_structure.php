<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if sessions table exists and has the wrong structure
        if (Schema::hasTable('sessions')) {
            // Get current table structure
            $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'sessions' AND table_schema = 'public'");
            $columnTypes = collect($columns)->pluck('data_type', 'column_name')->toArray();
            
            // Check if user_id column has wrong type (should be bigint, not varchar)
            if (isset($columnTypes['user_id']) && $columnTypes['user_id'] !== 'bigint') {
                echo "Fixing sessions table structure - user_id column type is: " . $columnTypes['user_id'] . "\n";
                
                // Drop and recreate the table with correct structure
                Schema::drop('sessions');
            }
        }
        
        // Create sessions table with correct structure
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
            
            echo "Sessions table created with correct structure\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop the sessions table in rollback as it's a core table
        // Schema::dropIfExists('sessions');
    }
};
