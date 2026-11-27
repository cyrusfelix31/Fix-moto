<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'profile_photo_setup_required')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('profile_photo_setup_required')->default(false)->after('password_setup_required');
            });
        }

        DB::table('users')
            ->where('role', 'mechanic')
            ->where('password_setup_required', true)
            ->update(['profile_photo_setup_required' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'profile_photo_setup_required')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('profile_photo_setup_required');
            });
        }
    }
};
