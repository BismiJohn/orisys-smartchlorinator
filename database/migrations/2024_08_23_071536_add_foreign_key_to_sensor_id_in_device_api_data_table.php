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
        Schema::table('device_api_data', function (Blueprint $table) {
            // Check if the column already exists before adding it
            if (!Schema::hasColumn('device_api_data', 'sensor_id')) {
                // Add the 'sensor_id' column as an unsigned integer
                $table->unsignedBigInteger('sensor_id')->after('id');

                // Add the foreign key constraint
                $table->foreign('sensor_id')
                      ->references('sensor_id')
                      ->on('sensors')
                      ->onDelete('cascade'); // or 'set null' based on your requirement
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('device_api_data', function (Blueprint $table) {
            // Check if the column exists before attempting to drop it
            if (Schema::hasColumn('device_api_data', 'sensor_id')) {
                // Drop the foreign key constraint first
                $table->dropForeign(['sensor_id']);

                // Then drop the 'sensor_id' column
                $table->dropColumn('sensor_id');
            }
        });
    }
};
