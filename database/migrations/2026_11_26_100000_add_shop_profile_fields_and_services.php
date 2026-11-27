<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_SERVICES = [
        'Flat Tire Repair', 'Tire Replacement', 'Battery Replacement',
        'Battery Jump-start', 'Brake Repair', 'Engine Repair',
        'Electrical Repair', 'Chain Repair and Adjustment', 'Change Oil',
        'Spark Plug Replacement', 'Preventive Maintenance',
        'General Motorcycle Repair',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable();
        });

        Schema::table('mechanic_profiles', function (Blueprint $table): void {
            $table->string('logo_path')->nullable();
        });

        Schema::table('mechanic_shop_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('mechanic_shop_members', 'phone')) {
                $table->string('phone', 32)->nullable();
            }
            if (! Schema::hasColumn('mechanic_shop_members', 'specialization')) {
                $table->string('specialization', 120)->nullable();
            }
            if (! Schema::hasColumn('mechanic_shop_members', 'years_experience')) {
                $table->unsignedTinyInteger('years_experience')->default(0);
            }
            if (! Schema::hasColumn('mechanic_shop_members', 'avatar_path')) {
                $table->string('avatar_path')->nullable();
            }
        });

        Schema::create('mechanic_shop_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mechanic_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['mechanic_profile_id', 'name']);
        });

        $profiles = DB::table('mechanic_profiles')->select('id', 'services')->get();
        foreach ($profiles as $profile) {
            foreach (self::DEFAULT_SERVICES as $service) {
                DB::table('mechanic_shop_services')->insertOrIgnore([
                    'mechanic_profile_id' => $profile->id,
                    'name' => $service,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (json_decode($profile->services ?? '[]', true) ?: [] as $service) {
                if (! in_array($service, self::DEFAULT_SERVICES, true)) {
                    DB::table('mechanic_shop_services')->insertOrIgnore([
                        'mechanic_profile_id' => $profile->id,
                        'name' => mb_substr($service, 0, 120),
                        'is_enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanic_shop_services');

        Schema::table('mechanic_shop_members', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'specialization', 'years_experience', 'avatar_path']);
        });

        Schema::table('mechanic_profiles', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
        });
    }
};
