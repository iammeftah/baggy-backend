<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action'); // created, updated, deleted, status_changed, etc.
            $table->string('entity_type'); // Order, Product, Category, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable(); // Store old/new values, amounts, etc.
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
        });

        // Add updated_by to critical tables
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('status_changed_at')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['updated_by_admin_id']);
            $table->dropColumn(['updated_by_admin_id', 'status_changed_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['created_by_admin_id']);
            $table->dropForeign(['updated_by_admin_id']);
            $table->dropColumn(['created_by_admin_id', 'updated_by_admin_id']);
        });

        Schema::dropIfExists('admin_activities');
    }
};
