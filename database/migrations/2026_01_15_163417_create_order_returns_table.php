<?php

// FILE NAME: XXXX_XX_XX_XXXXXX_create_order_returns_table.php
// Copy the content below into your generated migration file

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
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('return_number')->unique();
            $table->enum('status', ['pending', 'approved', 'rejected', 'processing', 'completed', 'cancelled'])->default('pending');
            $table->enum('reason', ['defective', 'wrong_item', 'not_as_described', 'changed_mind', 'quality_issues', 'other']);
            $table->text('description');
            $table->decimal('refund_amount', 10, 2);
            $table->enum('refund_method', ['original_payment', 'store_credit', 'bank_transfer'])->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('processed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('return_number');
            $table->index('status');
            $table->index(['order_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
