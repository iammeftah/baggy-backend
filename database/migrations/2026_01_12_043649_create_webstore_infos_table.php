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
        Schema::create('webstore_infos', function (Blueprint $table) {
            $table->id();
            $table->string('store_name');
            $table->text('store_description')->nullable();
            $table->string('email');
            $table->text('phone'); // Can store multiple phones comma-separated
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Morocco');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->text('working_hours')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default data
        DB::table('webstore_infos')->insert([
            'store_name' => 'Bags Store Morocco',
            'store_description' => 'Premium quality bags for modern women',
            'email' => 'contact@bagsstore.ma',
            'phone' => '+212 600 000 000, +212 700 000 000',
            'address' => 'Avenue Hassan II',
            'city' => 'Casablanca',
            'country' => 'Morocco',
            'latitude' => 33.5731,
            'longitude' => -7.5898,
            'instagram_url' => 'https://instagram.com/bagsstore',
            'tiktok_url' => 'https://tiktok.com/@bagsstore',
            'facebook_url' => 'https://facebook.com/bagsstore',
            'whatsapp_number' => '+212 600 000 000',
            'working_hours' => 'Mon-Sat: 9:00 AM - 8:00 PM, Sun: 10:00 AM - 6:00 PM',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webstore_infos');
    }
};
