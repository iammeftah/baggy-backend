<?php

// FILE NAME: XXXX_XX_XX_XXXXXX_add_return_fields_to_orders_table.php
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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_returnable')->default(true)->after('status');
            $table->boolean('has_return')->default(false)->after('is_returnable');
            $table->timestamp('return_deadline')->nullable()->after('has_return');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_returnable', 'has_return', 'return_deadline']);
        });
    }
};
