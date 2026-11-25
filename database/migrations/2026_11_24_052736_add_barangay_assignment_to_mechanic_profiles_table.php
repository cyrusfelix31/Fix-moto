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
            $table->foreignId('barangay_id')->nullable()->after('barangay')->constrained()->nullOnDelete();
            $table->string('barangay_match_status', 20)->default('unmatched')->after('barangay_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mechanic_profiles', function (Blueprint $table) {
            $table->dropForeign(['barangay_id']);
            $table->dropColumn(['barangay_id', 'barangay_match_status']);
        });
    }
};
