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
        Schema::rename('motorcycles', 'vehicles');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->renameColumn('motorcycle_type', 'vehicle_type');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['motorcycle_id']);
            $table->renameColumn('motorcycle_id', 'vehicle_id');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->renameColumn('vehicle_id', 'motorcycle_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->renameColumn('vehicle_type', 'motorcycle_type');
        });

        Schema::rename('vehicles', 'motorcycles');

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign('motorcycle_id')->references('id')->on('motorcycles')->restrictOnDelete();
        });
    }
};
