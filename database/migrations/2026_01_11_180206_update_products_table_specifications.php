<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop specifications foreign key if exists
            // and change specifications to text field
            if (Schema::hasColumn('products', 'specifications')) {
                $table->text('specifications')->nullable()->change();
            } else {
                $table->text('specifications')->nullable()->after('description');
            }
        });

        // Drop product_specifications table if it exists
        Schema::dropIfExists('product_specifications');
    }

    public function down(): void
    {
        // Recreate product_specifications table
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('spec_key', 100);
            $table->string('spec_value', 255);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('specifications');
        });
    }
};
