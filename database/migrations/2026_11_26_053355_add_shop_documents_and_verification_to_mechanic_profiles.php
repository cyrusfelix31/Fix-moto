
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mechanic_profiles', function (Blueprint $table) {
            $table->string('shop_phone', 32)->nullable();

            $table->string('verification_status', 20)
                ->default('pending')
                ->index();
        });

        Schema::create('mechanic_documents', function (Blueprint $table) {

            $table->id();

            $table->foreignId('mechanic_profile_id')
                ->constrained('mechanic_profiles')
                ->cascadeOnDelete();

            $table->string('document_type', 50);

            $table->string('document_path');

            $table->string('original_filename');

            $table->date('expiration_date')->nullable();

            $table->string('status', 20)->default('pending');

            $table->text('rejection_reason')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'mechanic_profile_id',
                'document_type'
            ]);

        });

        // Preserve existing verified accounts.
        DB::table('mechanic_profiles')
            ->where('is_verified', true)
            ->update([
                'verification_status' => 'approved'
            ]);

        // Preserve previously rejected applications.
        DB::table('mechanic_profiles')
            ->where('is_verified', false)
            ->orderBy('id')
            ->chunkById(100, function ($profiles) {

                foreach ($profiles as $profile) {

                    $latest = DB::table('mechanic_verifications')
                        ->where('mechanic_id', $profile->user_id)
                        ->orderByDesc('reviewed_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($latest && $latest->status === 'rejected') {

                        DB::table('mechanic_profiles')
                            ->where('id', $profile->id)
                            ->update([
                                'verification_status' => 'rejected'
                            ]);

                    }
                }

            });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanic_documents');

        Schema::table('mechanic_profiles', function (Blueprint $table) {

            $table->dropIndex(['verification_status']);

            $table->dropColumn([
                'shop_phone',
                'verification_status'
            ]);

        });
    }
};
