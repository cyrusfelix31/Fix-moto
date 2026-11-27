<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_picture')->nullable();
        });

        Schema::table('mechanic_shop_members', function (Blueprint $table) {
            $table->string('profile_picture')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('specialization')->nullable();
            $table->unsignedSmallInteger('years_of_experience')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mechanic_shop_members', function (Blueprint $table) {
            $table->dropColumn([
                'profile_picture',
                'phone',
                'specialization',
                'years_of_experience',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_picture');
        });
    }
};