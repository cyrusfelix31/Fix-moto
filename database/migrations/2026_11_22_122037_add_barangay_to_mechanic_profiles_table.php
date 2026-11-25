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
        Schema::table('mechanic_profiles', function (Blueprint $table) {
            $table->string('barangay', 100)->nullable()->after('service_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mechanic_profiles', function (Blueprint $table) {
            $table->dropColumn('barangay');
        });
    }
};
