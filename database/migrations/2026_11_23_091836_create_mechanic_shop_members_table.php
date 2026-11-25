<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mechanic_shop_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mechanic_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['mechanic_profile_id', 'is_active']);
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('mechanic_shop_member_id')
                ->nullable()
                ->after('mechanic_id')
                ->constrained('mechanic_shop_members')
                ->nullOnDelete();
        });

        $profiles = DB::table('mechanic_profiles')
            ->join('users', 'users.id', '=', 'mechanic_profiles.user_id')
            ->select('mechanic_profiles.id as mechanic_profile_id', 'users.name')
            ->get();

        foreach ($profiles as $profile) {
            DB::table('mechanic_shop_members')->insert([
                'mechanic_profile_id' => $profile->mechanic_profile_id,
                'name' => $profile->name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mechanic_shop_member_id');
        });

        Schema::dropIfExists('mechanic_shop_members');
    }
};
