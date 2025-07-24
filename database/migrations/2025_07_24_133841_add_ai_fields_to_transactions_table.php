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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('ai_category')->nullable()->after('category_id');
            $table->float('ai_confidence', 8, 4)->nullable()->after('ai_category');
            $table->text('ai_explanation')->nullable()->after('ai_confidence');
            
            // Add indexes for better query performance
            $table->index('ai_category');
            $table->index('ai_confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['ai_category']);
            $table->dropIndex(['ai_confidence']);
            $table->dropColumn(['ai_category', 'ai_confidence', 'ai_explanation']);
        });
    }
};
