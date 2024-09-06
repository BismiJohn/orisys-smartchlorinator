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
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropForeign(['sensor_id']);
        });

        Schema::dropIfExists('sensor_data');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Recreate the 'sensor_data' table
        Schema::create('sensor_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sensor_id');
            $table->foreign('sensor_id')
                  ->references('sensor_id')
                  ->on('sensors')
                  ->onDelete('cascade'); // or 'set null' based on the previous requirement

            $table->timestamps();
        });
    }
};
