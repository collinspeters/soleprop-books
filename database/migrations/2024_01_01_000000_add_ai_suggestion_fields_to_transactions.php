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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('ai_confidence', 5, 4)->nullable()->after('reconciled')->comment('AI confidence score for category suggestion (0.0000 to 1.0000)');
            $table->integer('ai_suggested_category_id')->nullable()->after('ai_confidence')->comment('AI suggested category ID');
            $table->boolean('ai_reviewed')->default(false)->after('ai_suggested_category_id')->comment('Whether AI suggestion has been reviewed');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_reviewed')->comment('When AI suggestion was reviewed');
            $table->unsignedInteger('ai_reviewed_by')->nullable()->after('ai_reviewed_at')->comment('User who reviewed the AI suggestion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'ai_confidence',
                'ai_suggested_category_id',
                'ai_reviewed',
                'ai_reviewed_at',
                'ai_reviewed_by'
            ]);
        });
    }
};