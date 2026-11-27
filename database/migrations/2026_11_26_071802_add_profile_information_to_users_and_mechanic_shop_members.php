<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'profile_picture')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('profile_picture')->nullable();
            });
        }

        foreach ([
            'profile_picture' => fn (Blueprint $table) => $table->string('profile_picture')->nullable(),
            'phone' => fn (Blueprint $table) => $table->string('phone', 32)->nullable(),
            'specialization' => fn (Blueprint $table) => $table->string('specialization')->nullable(),
            'years_of_experience' => fn (Blueprint $table) => $table->unsignedSmallInteger('years_of_experience')->nullable(),
        ] as $column => $addColumn) {
            if (! Schema::hasColumn('mechanic_shop_members', $column)) {
                Schema::table('mechanic_shop_members', $addColumn);
            }
        }
    }

    public function down(): void
    {
        foreach (['profile_picture', 'years_of_experience'] as $column) {
            if (Schema::hasColumn('mechanic_shop_members', $column)) {
                Schema::table('mechanic_shop_members', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('users', 'profile_picture')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('profile_picture');
            });
        }
    }
};
