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
        Schema::create('sensors', function (Blueprint $table) {
            $table->bigIncrements('sensor_id');
            $table->string('sensor_name');
            $table->unsignedBigInteger('sensor_type');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('calibration_frequency');
            $table->unsignedBigInteger('maintainor');
            $table->date('installation_date');
            $table->date('warranty_date');
            $table->date('last_calibration_date')->nullable();

            $table->enum('status', ['Active', 'Inactive']);
            $table->timestamps();

            $table->foreign('location_id')->references('location_id')->on('locations')->onDelete('cascade');
            $table->foreign('calibration_frequency')->references('id')->on('calibration_intervals')->onDelete('cascade');
            $table->foreign('maintainor')->references('team_id')->on('service_teams')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
