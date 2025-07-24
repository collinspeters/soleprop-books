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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('transaction_id')->nullable()->index();
            $table->string('vendor_name')->nullable();
            $table->decimal('amount', 15, 4)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->date('receipt_date')->nullable();
            $table->integer('category_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'processing', 'processed', 'matched', 'failed'])->default('pending');
            $table->json('ocr_data')->nullable();
            $table->decimal('ai_category_confidence', 5, 4)->nullable();
            $table->string('created_from', 100)->default('receipt_upload');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'receipt_date']);
            $table->index(['company_id', 'vendor_name']);
            $table->index(['company_id', 'amount']);
            $table->index(['company_id', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
