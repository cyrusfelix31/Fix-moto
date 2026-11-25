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
        Schema::table('motorcycles', function (Blueprint $table) {
            $table->string('vehicle_category')->default('two_wheeled')->after('motorcycle_type');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->json('services')->nullable()->after('issue_category');
            $table->text('notes')->nullable()->after('problem_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['services', 'notes']);
        });

        Schema::table('motorcycles', function (Blueprint $table) {
            $table->dropColumn('vehicle_category');
        });
    }
};
