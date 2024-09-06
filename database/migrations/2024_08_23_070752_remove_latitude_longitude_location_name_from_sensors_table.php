<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('sensors', function (Blueprint $table) {
            // Remove the fields 'latitude', 'longitude', and 'location_name'
            $table->dropColumn(['latitude', 'longitude', 'location_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('sensors', function (Blueprint $table) {
            // Add the fields back if you need to roll back the migration
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_name')->nullable();
        });
    }
};
