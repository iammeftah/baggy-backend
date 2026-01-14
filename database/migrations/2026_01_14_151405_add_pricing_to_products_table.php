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
        Schema::table('products', function (Blueprint $table) {
            // Add buying_price and selling_price columns
            $table->decimal('buying_price', 10, 2)->default(0)->after('price');
            $table->decimal('selling_price', 10, 2)->default(0)->after('buying_price');

            // Optional: Add profit margin (calculated field for reference)
            $table->decimal('profit_margin', 5, 2)->default(0)->after('selling_price')->comment('Percentage');
        });

        // Migrate existing price to selling_price (assume 40% profit margin)
        DB::statement('UPDATE products SET selling_price = price, buying_price = ROUND(price * 0.6, 2) WHERE selling_price = 0');

        // Calculate initial profit margins
        DB::statement('UPDATE products SET profit_margin = CASE WHEN buying_price > 0 THEN ROUND(((selling_price - buying_price) / buying_price * 100), 2) ELSE 0 END');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['buying_price', 'selling_price', 'profit_margin']);
        });
    }
};
