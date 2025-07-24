<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiReportSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add AI settings to the settings table if it exists
        if (Schema::hasTable('settings')) {
            // Insert AI-related settings
            DB::table('settings')->insert([
                [
                    'company_id' => 1,
                    'key' => 'ai.reports.enabled',
                    'value' => 'false',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'company_id' => 1,
                    'key' => 'ai.reports.provider',
                    'value' => 'openai',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'company_id' => 1,
                    'key' => 'ai.reports.model',
                    'value' => 'gpt-3.5-turbo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove AI settings
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'like', 'ai.reports.%')->delete();
        }
    }
}