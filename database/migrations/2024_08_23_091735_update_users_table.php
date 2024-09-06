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
        Schema::table('users', function (Blueprint $table) {
            // Rename the 'name' field to 'first_name'
            $table->renameColumn('name', 'first_name');

            // Add new fields 'last_name', 'mobile', and 'location'
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('mobile')->nullable()->after('last_name');
            $table->string('location')->nullable()->after('mobile');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the new fields 'last_name', 'mobile', and 'location'
            $table->dropColumn(['last_name', 'mobile', 'location']);

            // Rename 'first_name' back to 'name'
            $table->renameColumn('first_name', 'name');
        });
    }
};
