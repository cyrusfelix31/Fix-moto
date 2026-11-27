<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'password_setup_required')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('password_setup_required')->default(false)->after('password');
            });
        }

        if (Schema::hasTable('registration_otps') && ! Schema::hasColumn('registration_otps', 'purpose')) {
            Schema::table('registration_otps', function (Blueprint $table): void {
                $table->string('purpose', 40)->default('registration')->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('registration_otps') && Schema::hasColumn('registration_otps', 'purpose')) {
            Schema::table('registration_otps', function (Blueprint $table): void {
                $table->dropColumn('purpose');
            });
        }

        if (Schema::hasColumn('users', 'password_setup_required')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('password_setup_required');
            });
        }
    }
};
