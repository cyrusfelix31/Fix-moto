<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mechanic_profiles', function (Blueprint $table): void {
            $table->string('verification_status', 32)->default('pending_verification')->change();
        });

        DB::table('mechanic_profiles')->where('verification_status', 'pending')->update(['verification_status' => 'pending_verification']);

        DB::table('mechanic_profiles')->where('verification_status', 'approved')->orderBy('id')->chunkById(100, function ($profiles): void {
            $requiredTypes = ['valid_id', 'business_permit', 'mayors_permit', 'bir_certificate'];

            foreach ($profiles as $profile) {
                $documents = DB::table('mechanic_documents')
                    ->where('mechanic_profile_id', $profile->id)
                    ->get()
                    ->keyBy('document_type');

                $complete = true;
                foreach ($requiredTypes as $type) {
                    $document = $documents->get($type);
                    if (!$document || $document->status !== 'approved') {
                        $complete = false;
                        break;
                    }

                    if ($document->expiration_date && $document->expiration_date < now()->toDateString()) {
                        $complete = false;
                        break;
                    }

                    if (in_array($type, ['business_permit', 'mayors_permit'], true) && !$document->expiration_date) {
                        $complete = false;
                        break;
                    }
                }

                DB::table('mechanic_profiles')->where('id', $profile->id)->update([
                    'verification_status' => $complete ? 'fully_verified' : 'pending_verification',
                    'is_verified' => $complete,
                    'is_online' => $complete ? $profile->is_online : false,
                ]);
            }
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        DB::table('mechanic_profiles')->where('verification_status', 'pending_verification')->update(['verification_status' => 'pending']);
        DB::table('mechanic_profiles')->where('verification_status', 'fully_verified')->update(['verification_status' => 'approved']);

        Schema::table('mechanic_profiles', function (Blueprint $table): void {
            $table->string('verification_status', 20)->default('pending')->change();
        });
    }
};
